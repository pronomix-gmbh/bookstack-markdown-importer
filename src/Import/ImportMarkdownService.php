<?php

namespace BookStackMarkdownImporter\Import;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Repos\ChapterRepo;
use BookStack\Entities\Repos\PageRepo;
use BookStackMarkdownImporter\Support\ContainerNameIndex;
use BookStackMarkdownImporter\Support\HtmlSanitizer;
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
        protected ZipMarkdownReader $zipReader,
        protected ZipPathPlanner $zipPlanner,
        protected NameCollisionResolver $nameResolver
    ) {
    }

    public function import(Book $book, UploadedFile $file, bool $createChaptersFromFolders): ImportResult
    {
        $result = new ImportResult();
        $nameIndex = new ContainerNameIndex();
        $chapterMap = [];

        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = (string) Str::uuid();
        $relativeDir = 'tmp/imports/' . $uuid;
        $fileName = 'upload' . ($extension ? '.' . $extension : '');

        File::ensureDirectoryExists(storage_path('app/' . $relativeDir));
        $relativePath = $file->storeAs($relativeDir, $fileName, 'local');
        if (!$relativePath) {
            throw new ImportException('Failed to store uploaded file.');
        }
        $disk = Storage::disk('local');
        if (!$disk->exists($relativePath)) {
            throw new ImportException('Uploaded file could not be stored on the server.');
        }
        $fullPath = $disk->path($relativePath);

        try {
            if ($extension === 'zip') {
                if (!config('bookstack-markdown-importer.allow_zip', true)) {
                    throw new ImportException('ZIP imports are disabled.');
                }

                $maxUploadMb = (int) config('bookstack-markdown-importer.max_upload_mb', 20);
                $maxBytes = max(1, $maxUploadMb) * 1024 * 1024;

                $entries = $this->zipReader->read($fullPath, $maxBytes);
                if (count($entries) === 0) {
                    throw new ImportException('No Markdown files were found in the ZIP.');
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
                        $chapter = $chapterMap[$chapterName] ?? null;
                        if ($chapter === null) {
                            $chapter = $this->createChapter($book, $chapterName, $nameIndex, $result);
                            $chapterMap[$chapterName] = $chapter;
                        }
                        $container = $chapter;
                    }

                    $this->importMarkdownContent($container, $path, $entries[$path], $nameIndex, $result);
                }
            } else {
                try {
                    $contents = File::get($fullPath);
                } catch (Throwable) {
                    throw new ImportException('Unable to read uploaded Markdown file.');
                }
                $this->importMarkdownContent($book, $file->getClientOriginalName(), $contents, $nameIndex, $result);
            }
        } finally {
            File::deleteDirectory(storage_path('app/' . $relativeDir));
        }

        return $result;
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
            Log::warning('Markdown import entry failed', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
            $result->addFailure($path, $exception->getMessage());
        }
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
            'summary' => 'Imported from Markdown',
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

    protected function defaultTitleFromPath(string $path): string
    {
        $base = pathinfo($path, PATHINFO_FILENAME);
        return $base !== '' ? $base : 'Untitled';
    }

    protected function normalizeMarkdown(string $contents): string
    {
        $normalized = str_replace("\r\n", "\n", $contents);
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = mb_convert_encoding($normalized, 'UTF-8', 'UTF-8');

        return $normalized;
    }
}
