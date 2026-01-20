<?php

namespace BookStackMarkdownImporter\Tests;

use BookStackMarkdownImporter\Support\NameCollisionResolver;
use PHPUnit\Framework\TestCase;

class NameCollisionResolverTest extends TestCase
{
    public function test_returns_name_when_unique(): void
    {
        $resolver = new NameCollisionResolver();

        $result = $resolver->resolve('Sample', fn(string $name) => false);

        $this->assertSame('Sample', $result);
    }

    public function test_appends_incrementing_suffix(): void
    {
        $resolver = new NameCollisionResolver();
        $existing = ['test', 'test (2)', 'test (3)'];

        $result = $resolver->resolve('Test', function (string $name) use ($existing): bool {
            return in_array(strtolower($name), $existing, true);
        });

        $this->assertSame('Test (4)', $result);
    }
}
