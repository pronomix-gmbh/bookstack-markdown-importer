<?php

namespace BookStackMarkdownImporter\Import;

class ImportResult
{
    public int $pagesCreated = 0;
    public int $chaptersCreated = 0;
    /** @var string[] */
    public array $failures = [];

    public function addFailure(string $label, string $message): void
    {
        $this->failures[] = $label . ': ' . $message;
    }

    public function hasFailures(): bool
    {
        return count($this->failures) > 0;
    }
}
