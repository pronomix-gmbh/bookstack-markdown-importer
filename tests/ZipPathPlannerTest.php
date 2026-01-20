<?php

namespace BookStackMarkdownImporter\Tests;

use BookStackMarkdownImporter\Support\ZipPathPlanner;
use PHPUnit\Framework\TestCase;

class ZipPathPlannerTest extends TestCase
{
    public function test_orders_paths_and_maps_chapters(): void
    {
        $planner = new ZipPathPlanner();
        $paths = [
            'b.md',
            'folder2/z.md',
            'folder1/b.md',
            'a.md',
            'folder1/a.md',
        ];

        $plan = $planner->plan($paths, true);
        $orderedPaths = array_map(fn(array $item) => $item['path'], $plan);
        $chapters = array_map(fn(array $item) => $item['chapter'], $plan);

        $this->assertSame([
            'a.md',
            'b.md',
            'folder1/a.md',
            'folder1/b.md',
            'folder2/z.md',
        ], $orderedPaths);

        $this->assertSame([
            null,
            null,
            'folder1',
            'folder1',
            'folder2',
        ], $chapters);
    }

    public function test_does_not_map_chapters_when_disabled(): void
    {
        $planner = new ZipPathPlanner();
        $paths = ['folder/a.md', 'b.md'];

        $plan = $planner->plan($paths, false);

        $this->assertSame([null, null], array_map(fn(array $item) => $item['chapter'], $plan));
    }
}
