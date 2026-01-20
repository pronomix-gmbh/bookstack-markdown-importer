<?php

namespace BookStackMarkdownImporter\Import;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Repos\ChapterRepo;
use BookStack\Entities\Repos\PageRepo;
use BookStackMarkdownImporter\Support\ContainerNameIndex;
use BookStackMarkdownImporter\Support\HtmlSanitizer;
use BookStackMarkdownImporter\Support\HtmlTitleExtractor;
use BookStackMarkdownImporter\Support\MarkdownConverter;
use BookStackMarkdownImporter\Support\MarkdownTitleExtractor;
use BookStackMarkdownImporter\Support\NameCollisionResolver;
use BookStackMarkdownImporter\Support\ZipMarkdownReader;
use BookStackMarkdownImporter\Support\ZipPathPlanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImportMarkdownService
{
    public function __construct(
        protected PageRepo $pageRepo,
        protected ChapterRepo $chapterRepo,
        protected MarkdownTitleExtractor $titleExtractor,
        protected MarkdownConverter $markdownConverter,
        protected HtmlSanitizer $htmlSanitizer,
        protected HtmlTitleExtractor $htmlTitleExtractor,
        protected ZipMarkdownReader $zipReader,
        protected ZipPathPlanner $zipPlanner,
        protected NameCollisionResolver $nameResolver
    ) {
    }

    public function import(Book $book, UploadedFile $file, bool $createChaptersFromFolders): ImportResult
    {
        return $this->importFiles($book, [$file], $createChaptersFromFolders);
    }

    /**
     * @param UploadedFile[] $files
     */
    public function importFiles(Book $book, array $files, bool $createChaptersFromFolders): ImportResult
    {
        $result = new ImportResult();
        $nameIndex = new ContainerNameIndex();
        $chapterMap = $this->loadExistingChapters($book);

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $this->importSingleFile($book, $file, $createChaptersFromFolders, $nameIndex, $chapterMap, $result);
        }

        return $result;
    }

    /**
     * @param array<string, Chapter> $chapterMap
     */
    protected function importSingleFile(Book $book, UploadedFile $file, bool $createChaptersFromFolders, ContainerNameIndex $nameIndex, array &$chapterMap, ImportResult $result): void
    {
        $extension = $this->detectExtension($file);
        $uuid = (string) Str::uuid();
        $relativeDir = 'tmp/imports/' . $uuid;
        $fileName = 'upload' . ($extension ? '.' . $extension : '');
        $displayName = $file->getClientOriginalName() ?: trans('bookstack-markdown-importer::messages.uploaded_file');

        File::ensureDirectoryExists(storage_path('app/' . $relativeDir));

        try {
            $relativePath = $file->storeAs($relativeDir, $fileName, 'local');
            if (!$relativePath) {
                throw new ImportException(trans('bookstack-markdown-importer::messages.error_store_failed'));
            }

            $disk = Storage::disk('local');
            if (!$disk->exists($relativePath)) {
                throw new ImportException(trans('bookstack-markdown-importer::messages.error_store_missing'));
            }

            $fullPath = $disk->path($relativePath);

            if ($extension === 'zip') {
                if (!config('bookstack-markdown-importer.allow_zip', true)) {
                    throw new ImportException(trans('bookstack-markdown-importer::messages.error_zip_disabled', ['name' => $displayName]));
                }

                $maxUploadMb = (int) config('bookstack-markdown-importer.max_upload_mb', 20);
                $maxBytes = max(1, $maxUploadMb) * 1024 * 1024;

                $entries = $this->zipReader->read($fullPath, $maxBytes);
                if (count($entries) === 0) {
                    throw new ImportException(trans('bookstack-markdown-importer::messages.error_zip_no_files'));
                }

                $plan = $this->zipPlanner->plan(array_keys($entries), $createChaptersFromFolders);
                foreach ($plan as $item) {
                    $path = $item['path'];
                    if (!isset($entries[$path])) {
                        continue;
                    }

                    $container = $book;
                    $chapterName = $item['chapter'];
                    if ($chapterName !== null) {
                        $container = $this->getOrCreateChapter($book, $chapterName, $nameIndex, $chapterMap, $result);
                    }

                    $this->importZipContent($container, $path, $entries[$path], $nameIndex, $result);
                }
            } elseif (in_array($extension, ['html', 'htm'], true)) {
                try {
                    $rawHtml = File::get($fullPath);
                } catch (Throwable) {
                    throw new ImportException(trans('bookstack-markdown-importer::messages.error_html_read'));
                }

                $this->importHtmlContent($book, $displayName, $rawHtml, $nameIndex, $result);
            } else {
                try {
                    $contents = File::get($fullPath);
                } catch (Throwable) {
                    throw new ImportException(trans('bookstack-markdown-importer::messages.error_file_read'));
                }
                $this->importMarkdownContent($book, $displayName, $contents, $nameIndex, $result);
            }
        } catch (Throwable $exception) {
            Log::warning('Datei-Import fehlgeschlagen', [
                'name' => $displayName,
                'error' => $exception->getMessage(),
            ]);
            $result->addFailure($displayName, $exception->getMessage());
        } finally {
            File::deleteDirectory(storage_path('app/' . $relativeDir));
        }
    }

    protected function importMarkdownContent(Book|Chapter $container, string $path, string $contents, ContainerNameIndex $nameIndex, ImportResult $result): void
    {
        try {
            $markdown = $this->normalizeMarkdown($contents);
            $extracted = $this->titleExtractor->extract($markdown);

            $title = $extracted['title'] ?: $this->defaultTitleFromPath($path);
            $bodyMarkdown = $extracted['markdown'];

            $html = $this->markdownConverter->convert($bodyMarkdown);
            $html = $this->htmlSanitizer->sanitize($html);

            $this->createPage($container, $title, $html, $nameIndex);
            $result->pagesCreated++;
        } catch (Throwable $exception) {
            Log::warning('Inhalt konnte nicht importiert werden', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
            $result->addFailure($path, $exception->getMessage());
        }
    }

    protected function importHtmlContent(Book|Chapter $container, string $path, string $contents, ContainerNameIndex $nameIndex, ImportResult $result): void
    {
        try {
            $sanitizedHtml = $this->htmlSanitizer->sanitize($contents);
            $extracted = $this->htmlTitleExtractor->extract($sanitizedHtml);
            $title = $extracted['title'] ?: $this->defaultTitleFromPath($path);
            $html = $extracted['html'];

            $this->createPage($container, $title, $html, $nameIndex);
            $result->pagesCreated++;
        } catch (Throwable $exception) {
            Log::warning('HTML-Inhalt konnte nicht importiert werden', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
            $result->addFailure($path, $exception->getMessage());
        }
    }

    protected function importZipContent(Book|Chapter $container, string $path, string $contents, ContainerNameIndex $nameIndex, ImportResult $result): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['html', 'htm'], true)) {
            $this->importHtmlContent($container, $path, $contents, $nameIndex, $result);
            return;
        }

        $this->importMarkdownContent($container, $path, $contents, $nameIndex, $result);
    }

    protected function createPage(Book|Chapter $container, string $title, string $html, ContainerNameIndex $nameIndex): void
    {
        $uniqueName = $this->nameResolver->resolve($title, function (string $candidate) use ($container, $nameIndex): bool {
            return $nameIndex->pageNameExists($container, $candidate);
        });

        $draft = $this->pageRepo->getNewDraftPage($container);
        $this->pageRepo->publishDraft($draft, [
            'name' => $uniqueName,
            'html' => $html,
            'summary' => trans('bookstack-markdown-importer::messages.summary_imported'),
        ]);

        $nameIndex->addPageName($container, $uniqueName);
    }

    protected function createChapter(Book $book, string $name, ContainerNameIndex $nameIndex, ImportResult $result): Chapter
    {
        $uniqueName = $this->nameResolver->resolve($name, function (string $candidate) use ($book, $nameIndex): bool {
            return $nameIndex->chapterNameExists($book, $candidate);
        });

        $chapter = $this->chapterRepo->create(['name' => $uniqueName], $book);
        $nameIndex->addChapterName($book, $uniqueName);
        $result->chaptersCreated++;

        return $chapter;
    }

    /**
     * @param array<string, Chapter> $chapterMap
     */
    protected function getOrCreateChapter(Book $book, string $name, ContainerNameIndex $nameIndex, array &$chapterMap, ImportResult $result): Chapter
    {
        $key = strtolower($name);
        if (isset($chapterMap[$key])) {
            return $chapterMap[$key];
        }

        $chapter = $this->createChapter($book, $name, $nameIndex, $result);
        $chapterMap[$key] = $chapter;

        return $chapter;
    }

    /**
     * @return array<string, Chapter>
     */
    protected function loadExistingChapters(Book $book): array
    {
        $map = [];
        $chapters = $book->chapters()->get(['id', 'name']);
        foreach ($chapters as $chapter) {
            $map[strtolower($chapter->name)] = $chapter;
        }

        return $map;
    }

    protected function detectExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower($file->guessExtension() ?? '');
        }
        if ($extension === '') {
            $extension = strtolower(pathinfo($file->getClientOriginalName() ?: '', PATHINFO_EXTENSION));
        }

        return $extension;
    }

    protected function defaultTitleFromPath(string $path): string
    {
        $base = pathinfo($path, PATHINFO_FILENAME);
        return $base !== '' ? $base : trans('bookstack-markdown-importer::messages.default_title');
    }

    protected function normalizeMarkdown(string $contents): string
    {
        $normalized = str_replace("\r\n", "\n", $contents);
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = mb_convert_encoding($normalized, 'UTF-8', 'UTF-8');

        return $normalized;
    }
}
