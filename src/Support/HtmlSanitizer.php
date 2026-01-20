<?php

namespace BookStackMarkdownImporter\Support;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

class HtmlSanitizer
{
    protected HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $cachePath = storage_path('app/tmp/htmlpurifier');
        File::ensureDirectoryExists($cachePath);
        $config->set('Cache.SerializerPath', $cachePath);

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

    public function sanitize(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
