<?php

namespace BookStackMarkdownImporter\Tests;

use BookStackMarkdownImporter\Support\MarkdownTitleExtractor;
use PHPUnit\Framework\TestCase;

class MarkdownTitleExtractorTest extends TestCase
{
    public function test_extracts_first_heading_as_title(): void
    {
        $extractor = new MarkdownTitleExtractor();
        $markdown = "# My Title\n\nBody text\n";

        $result = $extractor->extract($markdown);

        $this->assertSame('My Title', $result['title']);
        $this->assertSame("Body text\n", $result['markdown']);
    }

    public function test_handles_missing_heading(): void
    {
        $extractor = new MarkdownTitleExtractor();
        $markdown = "Intro line\n# Later Title\n";

        $result = $extractor->extract($markdown);

        $this->assertNull($result['title']);
        $this->assertSame($markdown, $result['markdown']);
    }

    public function test_strips_trailing_hashes_from_title(): void
    {
        $extractor = new MarkdownTitleExtractor();
        $markdown = "# Title Here #\nContent";

        $result = $extractor->extract($markdown);

        $this->assertSame('Title Here', $result['title']);
        $this->assertSame('Content', $result['markdown']);
    }
}
