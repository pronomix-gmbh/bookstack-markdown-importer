<?php

namespace BookStackMarkdownImporter\Support;

use DOMDocument;
use DOMXPath;

class HtmlActionInjector
{
    public function injectBookAction(string $html, string $actionHtml): ?string
    {
        if (str_contains($html, 'data-import-markdown')) {
            return $html;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if ($loaded === false) {
            libxml_use_internal_errors($internalErrors);
            return null;
        }

        $xpath = new DOMXPath($doc);
        $query = "//div[contains(concat(' ', normalize-space(@class), ' '), ' tri-layout-right ')]" .
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' actions ')]" .
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' icon-list ')]";
        $actionLists = $xpath->query($query);

        if ($actionLists === false || $actionLists->length === 0) {
            libxml_use_internal_errors($internalErrors);
            return null;
        }

        $target = $actionLists->item(0);
        if ($target === null) {
            libxml_use_internal_errors($internalErrors);
            return null;
        }

        $fragment = $doc->createDocumentFragment();
        if (!$fragment->appendXML($actionHtml)) {
            libxml_use_internal_errors($internalErrors);
            return null;
        }

        $inserted = false;
        foreach ($target->childNodes as $child) {
            if ($child->nodeName === 'hr') {
                $target->insertBefore($fragment, $child);
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $target->appendChild($fragment);
        }

        $updatedHtml = $doc->saveHTML();
        libxml_use_internal_errors($internalErrors);

        return $updatedHtml;
    }
}
