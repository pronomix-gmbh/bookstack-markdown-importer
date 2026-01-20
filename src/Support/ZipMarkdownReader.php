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
            throw new ImportException('ZIP support is not available. Please enable the PHP zip extension.');
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
                throw new ImportException('ZIP contents exceed the maximum allowed size.');
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
            ZipArchive::ER_INCONS => 'ZIP archive is inconsistent.',
            ZipArchive::ER_INVAL => 'ZIP archive has an invalid argument.',
            ZipArchive::ER_MEMORY => 'ZIP archive could not be opened due to memory limits.',
            ZipArchive::ER_NOENT => 'ZIP archive file could not be found.',
            ZipArchive::ER_NOZIP => 'Uploaded file is not a valid ZIP archive.',
            ZipArchive::ER_OPEN => 'ZIP archive could not be opened.',
            ZipArchive::ER_READ => 'ZIP archive could not be read.',
            ZipArchive::ER_SEEK => 'ZIP archive could not be read (seek error).',
        ];

        $message = $map[$errorCode] ?? 'Unable to open ZIP file.';
        return $message;
    }
}
