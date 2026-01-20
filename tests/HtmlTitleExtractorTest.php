<?php

namespace BookStackMarkdownImporter\Tests;

use BookStackMarkdownImporter\Support\HtmlTitleExtractor;
use PHPUnit\Framework\TestCase;

class HtmlTitleExtractorTest extends TestCase
{
    public function test_extracts_h1_title_and_removes_it(): void
    {
        $extractor = new HtmlTitleExtractor();
        $html = '<h1>Hallo Welt</h1><p>Inhalt</p>';

        $result = $extractor->extract($html);

        $this->assertSame('Hallo Welt', $result['title']);
        $this->assertStringContainsString('<p>Inhalt</p>', $result['html']);
        $this->assertStringNotContainsString('<h1>', $result['html']);
    }
}
