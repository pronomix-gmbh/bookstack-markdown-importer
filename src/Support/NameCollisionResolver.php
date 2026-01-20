<?php

namespace BookStackMarkdownImporter\Support;

class NameCollisionResolver
{
    /**
     * @param callable(string): bool $exists
     */
    public function resolve(string $desired, callable $exists): string
    {
        $base = trim($desired);
        if ($base === '') {
            $base = 'Untitled';
        }

        if (!$exists($base)) {
            return $base;
        }

        $counter = 2;
        while (true) {
            $candidate = $base . ' (' . $counter . ')';
            if (!$exists($candidate)) {
                return $candidate;
            }
            $counter++;
        }
    }
}
