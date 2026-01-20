<?php

namespace BookStackMarkdownImporter\Support;

class MarkdownTitleExtractor
{
    /**
     * Extract a title from the first Markdown heading and return the cleaned body.
     *
     * @return array{title: ?string, markdown: string}
     */
    public function extract(string $markdown): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown);
        if ($lines === false) {
            return ['title' => null, 'markdown' => $markdown];
        }

        $title = null;
        $bodyLines = $lines;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $trimmed = ltrim($trimmed, "\xEF\xBB\xBF");
            if (preg_match('/^#\s+(.+)$/', $trimmed, $matches)) {
                $rawTitle = trim($matches[1]);
                $rawTitle = preg_replace('/\s+#*$/', '', $rawTitle);
                $title = trim($rawTitle);
                $bodyLines = array_slice($lines, $index + 1);
            }
            break;
        }

        $body = implode("\n", $bodyLines);
        $body = ltrim($body, "\r\n\n");

        return ['title' => $title ?: null, 'markdown' => $body];
    }
}
