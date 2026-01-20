<?php

namespace BookStackMarkdownImporter\Controllers;

use BookStack\Entities\Queries\BookQueries;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use BookStackMarkdownImporter\Import\ImportException;
use BookStackMarkdownImporter\Import\ImportMarkdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImportMarkdownController extends Controller
{
    public function __construct(
        protected BookQueries $bookQueries,
        protected ImportMarkdownService $importer
    ) {
    }

    public function showForm(string $book): mixed
    {
        $bookModel = $this->bookQueries->findVisibleBySlugOrFail($book);
        $this->checkOwnablePermission(Permission::BookUpdate, $bookModel, $bookModel->getUrl());
        $this->checkOwnablePermission(Permission::PageCreate, $bookModel, $bookModel->getUrl());

        $this->setPageTitle(trans('bookstack-markdown-importer::messages.import'));

        return view('bookstack-markdown-importer::import', [
            'book' => $bookModel,
            'createChaptersDefault' => (bool) config('bookstack-markdown-importer.create_chapters_from_folders_default', true),
        ]);
    }

    public function handleImport(Request $request, string $book): RedirectResponse
    {
        $bookModel = $this->bookQueries->findVisibleBySlugOrFail($book);
        $this->checkOwnablePermission(Permission::BookUpdate, $bookModel, $bookModel->getUrl());
        $this->checkOwnablePermission(Permission::PageCreate, $bookModel, $bookModel->getUrl());

        $allowZip = (bool) config('bookstack-markdown-importer.allow_zip', true);
        $maxUploadMb = (int) config('bookstack-markdown-importer.max_upload_mb', 20);
        $maxUploadKb = max(1, $maxUploadMb * 1024);

        $request->validate([
            'file' => 'required|array',
            'file.*' => 'file|max:' . $maxUploadKb,
        ]);

        $filesInput = $request->file('file');
        $files = is_array($filesInput) ? $filesInput : [$filesInput];
        $validFiles = [];
        $validationErrors = [];

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            if (!$file->isValid()) {
                $validationErrors[] = trans('bookstack-markdown-importer::messages.error_upload_failed');
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if ($extension === '') {
                $extension = strtolower($file->guessExtension() ?? '');
            }

            $displayName = $file->getClientOriginalName() ?: trans('bookstack-markdown-importer::messages.uploaded_file');
            if (!in_array($extension, ['md', 'markdown', 'txt', 'html', 'htm', 'zip'], true)) {
                $validationErrors[] = trans('bookstack-markdown-importer::messages.error_invalid_type', ['name' => $displayName]);
                continue;
            }

            if ($extension === 'zip' && !$allowZip) {
                $validationErrors[] = trans('bookstack-markdown-importer::messages.error_zip_disabled', ['name' => $displayName]);
                continue;
            }

            $validFiles[] = $file;
        }

        if (count($validationErrors) > 0) {
            return redirect($bookModel->getUrl('/markdown-import'))
                ->withInput($request->except('file'))
                ->withErrors($validationErrors);
        }

        if (count($validFiles) === 0) {
            return redirect($bookModel->getUrl('/markdown-import'))
                ->withInput($request->except('file'))
                ->withErrors([trans('bookstack-markdown-importer::messages.error_no_valid_files')]);
        }

        $createChapters = $request->boolean('create_chapters', (bool) config('bookstack-markdown-importer.create_chapters_from_folders_default', true));
        if ($createChapters && !userCan(Permission::ChapterCreate, $bookModel)) {
            $createChapters = false;
            session()->flash('warning', trans('bookstack-markdown-importer::messages.warning_no_chapter_permission'));
        }

        try {
            $result = $this->importer->importFiles($bookModel, $validFiles, $createChapters);
        } catch (ImportException $exception) {
            Log::warning('Import fehlgeschlagen', [
                'user_id' => user()->id ?? null,
                'book_id' => $bookModel->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect($bookModel->getUrl('/markdown-import'))
                ->withInput($request->except('file'))
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $summaryParts = [
            trans_choice('bookstack-markdown-importer::messages.summary_pages', $result->pagesCreated, ['count' => $result->pagesCreated]),
        ];
        if ($result->chaptersCreated > 0) {
            $summaryParts[] = trans_choice('bookstack-markdown-importer::messages.summary_chapters', $result->chaptersCreated, ['count' => $result->chaptersCreated]);
        }

        $successMessage = trans('bookstack-markdown-importer::messages.success_complete', [
            'summary' => implode(', ', $summaryParts),
        ]);
        session()->flash('success', $successMessage);

        if ($result->hasFailures()) {
            $preview = implode('; ', array_slice($result->failures, 0, 6));
            $suffix = count($result->failures) > 6
                ? ' ' . trans('bookstack-markdown-importer::messages.and_more') . '.'
                : '.';
            session()->flash('warning', trans('bookstack-markdown-importer::messages.warning_partial', [
                'details' => $preview . $suffix,
            ]));
        }

        Log::info('Import abgeschlossen', [
            'user_id' => user()->id ?? null,
            'book_id' => $bookModel->id,
            'pages_created' => $result->pagesCreated,
            'chapters_created' => $result->chaptersCreated,
            'failures' => $result->failures,
        ]);

        return redirect($bookModel->getUrl('/markdown-import'));
    }
}
