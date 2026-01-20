<?php

use BookStackMarkdownImporter\Controllers\ImportMarkdownController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/books/{book}/markdown-import', [ImportMarkdownController::class, 'showForm'])
        ->name('bookstack-markdown-importer.show');

    Route::post('/books/{book}/markdown-import', [ImportMarkdownController::class, 'handleImport'])
        ->name('bookstack-markdown-importer.import');
});
