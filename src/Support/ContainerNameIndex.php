<?php

namespace BookStackMarkdownImporter\Support;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;

class ContainerNameIndex
{
    /** @var array<string, array<string, bool>> */
    protected array $pageNamesByContainer = [];
    /** @var array<int, array<string, bool>> */
    protected array $chapterNamesByBook = [];

    public function pageNameExists(Book|Chapter $container, string $name): bool
    {
        $key = $this->getContainerKey($container);
        if (!isset($this->pageNamesByContainer[$key])) {
            $this->pageNamesByContainer[$key] = $this->loadPageNames($container);
        }

        return isset($this->pageNamesByContainer[$key][strtolower($name)]);
    }

    public function addPageName(Book|Chapter $container, string $name): void
    {
        $key = $this->getContainerKey($container);
        if (!isset($this->pageNamesByContainer[$key])) {
            $this->pageNamesByContainer[$key] = [];
        }

        $this->pageNamesByContainer[$key][strtolower($name)] = true;
    }

    public function chapterNameExists(Book $book, string $name): bool
    {
        $bookId = $book->id;
        if (!isset($this->chapterNamesByBook[$bookId])) {
            $this->chapterNamesByBook[$bookId] = $this->loadChapterNames($book);
        }

        return isset($this->chapterNamesByBook[$bookId][strtolower($name)]);
    }

    public function addChapterName(Book $book, string $name): void
    {
        $bookId = $book->id;
        if (!isset($this->chapterNamesByBook[$bookId])) {
            $this->chapterNamesByBook[$bookId] = [];
        }

        $this->chapterNamesByBook[$bookId][strtolower($name)] = true;
    }

    protected function getContainerKey(Book|Chapter $container): string
    {
        if ($container instanceof Chapter) {
            return 'chapter:' . $container->id;
        }

        return 'book:' . $container->id;
    }

    /**
     * @return array<string, bool>
     */
    protected function loadPageNames(Book|Chapter $container): array
    {
        $query = Page::query();
        if ($container instanceof Chapter) {
            $query->where('chapter_id', $container->id);
        } else {
            $query->where('book_id', $container->id)->whereNull('chapter_id');
        }

        $names = $query->pluck('name')->all();
        $lookup = [];
        foreach ($names as $name) {
            $lookup[strtolower($name)] = true;
        }

        return $lookup;
    }

    /**
     * @return array<string, bool>
     */
    protected function loadChapterNames(Book $book): array
    {
        $names = Chapter::query()->where('book_id', $book->id)->pluck('name')->all();
        $lookup = [];
        foreach ($names as $name) {
            $lookup[strtolower($name)] = true;
        }

        return $lookup;
    }
}
