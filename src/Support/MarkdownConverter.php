<?php

namespace BookStackMarkdownImporter\Support;

use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter as LeagueMarkdownConverter;

class MarkdownConverter
{
    protected LeagueMarkdownConverter $converter;

    public function __construct()
    {
        $config = [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());

        $customEnvironment = Theme::dispatch(ThemeEvents::COMMONMARK_ENVIRONMENT_CONFIGURE, $environment);
        if ($customEnvironment instanceof Environment) {
            $environment = $customEnvironment;
        }

        if (method_exists($environment, 'mergeConfig')) {
            $environment->mergeConfig($config);
        }

        $this->converter = new LeagueMarkdownConverter($environment);
    }

    public function convert(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
