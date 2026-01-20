<?php

namespace BookStackMarkdownImporter\Support;

class ZipPathPlanner
{
    /**
     * @param string[] $paths
     * @return array<int, array{path: string, chapter: ?string}>
     */
    public function plan(array $paths, bool $createChaptersFromFolders): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            $clean = str_replace('\\', '/', $path);
            $clean = ltrim($clean, './');
            if ($clean === '') {
                continue;
            }
            $normalized[] = $clean;
        }

        usort($normalized, function (string $a, string $b): int {
            return strcmp(strtolower($a), strtolower($b));
        });

        $plan = [];
        foreach ($normalized as $path) {
            $chapter = null;
            if ($createChaptersFromFolders) {
                $firstSegment = explode('/', $path, 2)[0] ?? '';
                if ($firstSegment !== '' && str_contains($path, '/')) {
                    $chapter = $firstSegment;
                }
            }

            $plan[] = [
                'path' => $path,
                'chapter' => $chapter,
            ];
        }

        return $plan;
    }
}
