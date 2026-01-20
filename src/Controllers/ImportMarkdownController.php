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

        $this->setPageTitle('Import Markdown');

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
            'file' => 'required|file|max:' . $maxUploadKb,
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower($request->file('file')->guessExtension() ?? '');
        }

        if (!in_array($extension, ['md', 'markdown', 'zip'], true)) {
            return redirect($bookModel->getUrl('/markdown-import'))
                ->withInput($request->except('file'))
                ->withErrors(['file' => 'Only Markdown (.md/.markdown) and ZIP files are supported.']);
        }

        if ($extension === 'zip' && !$allowZip) {
            return redirect($bookModel->getUrl('/markdown-import'))
                ->withInput($request->except('file'))
                ->withErrors(['file' => 'ZIP imports are disabled by configuration.']);
        }

        $createChapters = $request->boolean('create_chapters', (bool) config('bookstack-markdown-importer.create_chapters_from_folders_default', true));
        if ($createChapters && !userCan(Permission::ChapterCreate, $bookModel)) {
            $createChapters = false;
            session()->flash('warning', 'You do not have permission to create chapters. Pages will be imported into the book root.');
        }

        try {
            $result = $this->importer->import($bookModel, $request->file('file'), $createChapters);
        } catch (ImportException $exception) {
            Log::warning('Markdown import failed', [
                'user_id' => user()->id ?? null,
                'book_id' => $bookModel->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect($bookModel->getUrl('/markdown-import'))
                ->withInput($request->except('file'))
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $summaryParts = [
            sprintf('%d page(s) created', $result->pagesCreated),
        ];
        if ($result->chaptersCreated > 0) {
            $summaryParts[] = sprintf('%d chapter(s) created', $result->chaptersCreated);
        }

        $successMessage = 'Import complete: ' . implode(', ', $summaryParts) . '.';
        session()->flash('success', $successMessage);

        if ($result->hasFailures()) {
            $preview = implode('; ', array_slice($result->failures, 0, 6));
            $suffix = count($result->failures) > 6 ? ' and more.' : '.';
            session()->flash('warning', 'Some files failed to import: ' . $preview . $suffix);
        }

        Log::info('Markdown import completed', [
            'user_id' => user()->id ?? null,
            'book_id' => $bookModel->id,
            'pages_created' => $result->pagesCreated,
            'chapters_created' => $result->chaptersCreated,
            'failures' => $result->failures,
        ]);

        return redirect($bookModel->getUrl('/markdown-import'));
    }
}
