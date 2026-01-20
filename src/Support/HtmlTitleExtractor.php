<?php

namespace BookStackMarkdownImporter\Support;

use DOMDocument;
use DOMXPath;

class HtmlTitleExtractor
{
    /**
     * @return array{title: ?string, html: string}
     */
    public function extract(string $html): array
    {
        if (trim($html) === '') {
            return ['title' => null, 'html' => $html];
        }

        $html = $this->normalizeEncoding($html);
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if ($loaded === false) {
            libxml_use_internal_errors($internalErrors);
            return ['title' => null, 'html' => $html];
        }
        $this->removeProcessingInstructions($doc);

        $xpath = new DOMXPath($doc);
        $title = null;
        $nodes = $xpath->query('//h1');
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node) {
                $title = trim($node->textContent ?? '');
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $htmlOutput = $this->stripXmlDeclaration($this->getInnerHtml($doc));
        libxml_use_internal_errors($internalErrors);

        return ['title' => $title ?: null, 'html' => $htmlOutput];
    }

    protected function removeProcessingInstructions(DOMDocument $doc): void
    {
        $nodes = [];
        foreach ($doc->childNodes as $child) {
            $nodes[] = $child;
        }
        foreach ($nodes as $child) {
            if ($child->nodeType === XML_PI_NODE) {
                $doc->removeChild($child);
            }
        }
    }

    protected function stripXmlDeclaration(string $html): string
    {
        return preg_replace('/^<\\?xml[^>]*\\?>/i', '', $html) ?? $html;
    }

    protected function normalizeEncoding(string $html): string
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        }

        return $html;
    }

    protected function getInnerHtml(DOMDocument $doc): string
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body) {
            $html = '';
            foreach ($body->childNodes as $child) {
                $html .= $doc->saveHTML($child);
            }
            return $html;
        }

        $html = '';
        foreach ($doc->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return $html;
    }
}
