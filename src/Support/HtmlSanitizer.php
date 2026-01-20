<?php

namespace BookStackMarkdownImporter\Support;

use DOMDocument;
use DOMXPath;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

class HtmlSanitizer
{
    protected HTMLPurifier $purifier;
    /**
     * Optional tag replacements applied before sanitizing.
     *
     * @var array<string, string>
     */
    protected array $tagFallbacks = [];
    /**
     * @var string[]
     */
    protected array $allowedTags = [
        'p', 'div', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'em', 'b', 'i', 'u', 's', 'del',
        'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'section', 'article', 'header', 'footer', 'nav', 'aside', 'main',
        'figure', 'figcaption',
        'sup', 'sub', 'small', 'mark', 'kbd',
        'a', 'img', 'span',
    ];
    /**
     * @var string[]
     */
    protected array $inlineFallbackTags = [
        'abbr', 'cite', 'q', 'time', 'var', 'samp', 'bdi', 'bdo', 'data', 'dfn',
        'ruby', 'rt', 'rp', 'wbr',
    ];
    /**
     * Tags that should be removed with their content.
     *
     * @var string[]
     */
    protected array $dropContentTags = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'noscript',
    ];

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $cachePath = storage_path('app/tmp/htmlpurifier');
        File::ensureDirectoryExists($cachePath);
        $config->set('Cache.SerializerPath', $cachePath);
        $this->configureHtml5Definitions($config);

        $config->set('HTML.Allowed', implode(',', [
            'p', 'div', 'br', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'em', 'b', 'i', 'u', 's', 'del',
            'blockquote', 'pre', 'code',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'th[colspan|rowspan]', 'td[colspan|rowspan]',
            'section', 'article', 'header', 'footer', 'nav', 'aside', 'main',
            'figure', 'figcaption',
            'sup', 'sub', 'small', 'mark', 'kbd',
            'a[href|title|rel|target]',
            'img[src|alt|title]',
            'span',
        ]));
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);
        $config->set('HTML.Nofollow', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedClasses', []);
        $config->set('Attr.EnableID', false);

        $this->purifier = new HTMLPurifier($config);
    }

    protected function configureHtml5Definitions(HTMLPurifier_Config $config): void
    {
        $config->set('HTML.DefinitionID', 'bookstack-html5');
        $config->set('HTML.DefinitionRev', 1);

        $definition = null;
        if (method_exists($config, 'maybeGetRawHTMLDefinition')) {
            $definition = $config->maybeGetRawHTMLDefinition();
        } elseif (method_exists($config, 'getHTMLDefinition')) {
            $definition = $config->getHTMLDefinition();
        }

        if (!$definition || !method_exists($definition, 'addElement')) {
            return;
        }

        $definition->addElement('section', 'Block', 'Flow', 'Common');
        $definition->addElement('article', 'Block', 'Flow', 'Common');
        $definition->addElement('header', 'Block', 'Flow', 'Common');
        $definition->addElement('footer', 'Block', 'Flow', 'Common');
        $definition->addElement('nav', 'Block', 'Flow', 'Common');
        $definition->addElement('aside', 'Block', 'Flow', 'Common');
        $definition->addElement('main', 'Block', 'Flow', 'Common');
        $definition->addElement('figure', 'Block', 'Flow', 'Common');
        $definition->addElement('figcaption', 'Block', 'Flow', 'Common');
    }

    public function sanitize(string $html): string
    {
        $html = $this->normalizeTags($html);
        $html = $this->unwrapUnsupportedTags($html);
        return $this->purifier->purify($html);
    }

    protected function normalizeTags(string $html): string
    {
        if ($html === '' || empty($this->tagFallbacks)) {
            return $html;
        }

        $pattern = '#<(\\/)?(' . implode('|', array_keys($this->tagFallbacks)) . ')\\b#i';
        $result = preg_replace_callback($pattern, function (array $matches): string {
            $closing = $matches[1] ?? '';
            $tag = strtolower($matches[2] ?? '');
            $replacement = $this->tagFallbacks[$tag] ?? $tag;

            return '<' . $closing . $replacement;
        }, $html);

        return $result ?? $html;
    }

    protected function unwrapUnsupportedTags(string $html): string
    {
        if ($html === '' || empty($this->allowedTags)) {
            return $html;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if ($loaded === false) {
            libxml_use_internal_errors($internalErrors);
            return $html;
        }

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//*');
        if ($nodes) {
            $tagSet = array_fill_keys($this->allowedTags, true);
            $dropSet = array_fill_keys($this->dropContentTags, true);
            $inlineSet = array_fill_keys($this->inlineFallbackTags, true);
            $nodeList = [];
            foreach ($nodes as $node) {
                $nodeList[] = $node;
            }
            foreach ($nodeList as $node) {
                $tag = strtolower($node->nodeName);
                if (isset($tagSet[$tag])) {
                    continue;
                }
                if (isset($dropSet[$tag])) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                    continue;
                }
                $fallback = isset($inlineSet[$tag]) ? 'span' : 'div';
                $this->replaceNodeTag($node, $fallback);
            }
        }

        $output = $this->getInnerHtml($doc);
        libxml_use_internal_errors($internalErrors);

        return $output;
    }

    protected function unwrapNode(\DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    protected function replaceNodeTag(\DOMNode $node, string $tag): void
    {
        $doc = $node->ownerDocument;
        $parent = $node->parentNode;
        if (!$doc || !$parent) {
            return;
        }

        $replacement = $doc->createElement($tag);
        while ($node->firstChild) {
            $replacement->appendChild($node->firstChild);
        }

        $parent->replaceChild($replacement, $node);
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
