<?php

declare(strict_types=1);

namespace Tests\Support\Files;

use PHPUnit\Framework\Assert;

trait ReadsFiles
{
    /**
     * Reads a file the test expects to exist. A missing or unreadable file fails here, instead of
     * turning into an empty string that fails a later assertion with a misleading message.
     */
    protected static function readFile(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            Assert::fail("Cannot read {$path}");
        }

        return $contents;
    }
}
