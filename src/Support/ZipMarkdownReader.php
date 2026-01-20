<?php

namespace BookStackMarkdownImporter\Support;

use BookStackMarkdownImporter\Import\ImportException;
use ZipArchive;

class ZipMarkdownReader
{
    /**
     * @return array<string, string>
     */
    public function read(string $zipPath, int $maxTotalBytes): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ImportException(trans('bookstack-markdown-importer::messages.error_zip_support_missing'));
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new ImportException($this->formatOpenError($opened));
        }

        $entries = [];
        $totalBytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $rawName = $stat['name'] ?? '';
            if ($rawName === '' || str_contains($rawName, "\0")) {
                continue;
            }

            $name = str_replace('\\', '/', $rawName);
            if (str_starts_with($name, '/') || str_contains($name, '../') || str_contains($name, '..\\')) {
                continue;
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            $name = ltrim($name, './');
            if (str_starts_with($name, '__MACOSX/')) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, ['md', 'markdown'], true)) {
                continue;
            }

            $size = (int) ($stat['size'] ?? 0);
            $totalBytes += $size;
            if ($totalBytes > $maxTotalBytes) {
                $zip->close();
                throw new ImportException(trans('bookstack-markdown-importer::messages.error_zip_too_large'));
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            $entries[$name] = $contents;
        }

        $zip->close();

        return $entries;
    }

    protected function formatOpenError(int $errorCode): string
    {
        $map = [
            ZipArchive::ER_INCONS => trans('bookstack-markdown-importer::messages.zip_error_inconsistent'),
            ZipArchive::ER_INVAL => trans('bookstack-markdown-importer::messages.zip_error_invalid'),
            ZipArchive::ER_MEMORY => trans('bookstack-markdown-importer::messages.zip_error_memory'),
            ZipArchive::ER_NOENT => trans('bookstack-markdown-importer::messages.zip_error_noent'),
            ZipArchive::ER_NOZIP => trans('bookstack-markdown-importer::messages.zip_error_nozip'),
            ZipArchive::ER_OPEN => trans('bookstack-markdown-importer::messages.zip_error_open'),
            ZipArchive::ER_READ => trans('bookstack-markdown-importer::messages.zip_error_read'),
            ZipArchive::ER_SEEK => trans('bookstack-markdown-importer::messages.zip_error_seek'),
        ];

        return $map[$errorCode] ?? trans('bookstack-markdown-importer::messages.error_zip_open');
    }
}
