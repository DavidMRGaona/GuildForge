<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCodes;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Draws an SVG logo as a PNG, since GD reads no SVG: the sanitized logo of a QR code's PNG
 * output, and the SVG mail logo (MailLogoService), which mail clients do not show.
 * rsvg-convert (librsvg) runs in a process of its own, so a hostile or heavy SVG cannot take
 * the PHP worker down with it: a clean environment (no application secrets reach a parser of
 * untrusted input), a time and memory cap, and the SVG on stdin, which leaves no base path to
 * resolve a relative reference against. The images install it; the CI installs librsvg2-bin.
 */
final readonly class RsvgConvertRasterizer
{
    public const int MAX_DIMENSION = 2048;

    private const int TIMEOUT_SECONDS = 10;

    /** Address space of the process; generous for glibc's arenas, a cap for runaway filters */
    private const int MEMORY_LIMIT_KIB = 1_048_576;

    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(
        private string $binary = 'rsvg-convert',
    ) {}

    /**
     * @return string PNG bytes, the SVG scaled to fit width x height with its proportion kept
     *
     * @throws RuntimeException when rsvg-convert is missing, fails or runs out of time
     */
    public function rasterize(string $svg, int $width, int $height): string
    {
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new RuntimeException(sprintf('A rasterized logo must be 1 to %d pixels a side', self::MAX_DIMENSION));
        }

        $process = new Process([
            '/usr/bin/env', '-i', '/bin/sh', '-c',
            'ulimit -v "$1" && exec "$2" --format=png --width="$3" --height="$4" --keep-aspect-ratio',
            'sh', (string) self::MEMORY_LIMIT_KIB, $this->executable(), (string) $width, (string) $height,
        ]);
        $process->setInput($svg);
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $process->run();

        $png = $process->getOutput();
        if (! $process->isSuccessful() || ! str_starts_with($png, self::PNG_SIGNATURE)) {
            throw new RuntimeException(sprintf(
                'rsvg-convert could not draw the SVG logo (exit %s): %s',
                $process->getExitCode() ?? '?',
                mb_substr(trim($process->getErrorOutput()), 0, 300),
            ));
        }

        return $png;
    }

    private function executable(): string
    {
        $path = str_contains($this->binary, '/') ? $this->binary : (new ExecutableFinder)->find($this->binary);

        if ($path === null || ! is_file($path) || ! is_executable($path)) {
            throw new RuntimeException(sprintf('rsvg-convert is not installed (looked for %s)', $this->binary));
        }

        return $path;
    }
}
