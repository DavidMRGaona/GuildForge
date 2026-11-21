<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

/**
 * Encoding and separator of a CSV text: UTF-8 (with or without BOM) when it is valid UTF-8, else
 * Windows-1252 (what Excel in Spanish exports); `;`, `,` or a tab, whichever the header uses most
 * outside quotes (`;` first on a tie, `,` when it has none).
 */
final readonly class CsvDialect
{
    public const string UTF8 = 'UTF-8';

    public const string WINDOWS_1252 = 'Windows-1252';

    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /** In order of preference on a tie. */
    private const array DELIMITERS = [';', ',', "\t"];

    private const string DEFAULT_DELIMITER = ',';

    public function __construct(
        public string $encoding,
        public string $delimiter,
    ) {}

    public static function detect(string $content): self
    {
        $text = str_starts_with($content, self::UTF8_BOM) ? substr($content, strlen(self::UTF8_BOM)) : $content;
        $encoding = mb_check_encoding($text, self::UTF8) ? self::UTF8 : self::WINDOWS_1252;

        // Separators, quotes and line breaks are the same bytes in both encodings.
        return new self($encoding, self::delimiterOf(self::headerLine($text)));
    }

    /**
     * Whether some record has more fields than the cap, empty ones included, counted before openspout
     * builds the row. Quoted fields may hold separators and line breaks; «""» toggles twice.
     */
    public function hasRecordWiderThan(string $content, int $cap): bool
    {
        $length = strlen($content);
        $stops = $this->delimiter."\"\r\n";
        $position = 0;
        $fields = 1;
        $quoted = false;

        while ($position < $length) {
            $position += strcspn($content, $quoted ? '"' : $stops, $position);

            if ($position >= $length) {
                break;
            }

            $character = $content[$position];

            if ($character === '"') {
                $quoted = ! $quoted;
            } elseif ($character === $this->delimiter) {
                if (++$fields > $cap) {
                    return true;
                }
            } else {
                $fields = 1;
            }

            $position++;
        }

        return false;
    }

    private static function headerLine(string $text): string
    {
        return preg_match('/^.*[^\s;,"].*$/m', $text, $match) === 1 ? $match[0] : '';
    }

    private static function delimiterOf(string $line): string
    {
        $counts = array_fill_keys(self::DELIMITERS, 0);
        $quoted = false;

        foreach (str_split($line) as $character) {
            if ($character === '"') {
                $quoted = ! $quoted;
            } elseif (! $quoted && isset($counts[$character])) {
                $counts[$character]++;
            }
        }

        $best = self::DEFAULT_DELIMITER;
        $bestCount = 0;

        foreach ($counts as $delimiter => $count) {
            if ($count > $bestCount) {
                $best = (string) $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
