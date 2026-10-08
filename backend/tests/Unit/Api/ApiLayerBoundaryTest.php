<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ApiLayerBoundaryTest extends TestCase
{
    public function test_api_layer_does_not_contain_halal_rule_engine_logic(): void
    {
        $paths = [
            dirname(__DIR__, 3).'/app/Http/Controllers/Api',
            dirname(__DIR__, 3).'/app/Http/Resources',
        ];

        foreach ($paths as $path) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                $this->assertStringNotContainsString('VerdictCalculator', $contents, $file->getPathname());
                $this->assertStringNotContainsString('RuleEngine', $contents, $file->getPathname());
            }
        }
    }
}
