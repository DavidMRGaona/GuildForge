<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;

/**
 * Encoding and separator of a CSV text. Encoding: UTF-8 (with or without BOM) when the whole text
 * is valid UTF-8, else Windows-1252 (what Excel in Spanish exports). Separator: the one Excel's
 * `sep=` first line names; else, among `;`, `,` and tab (in that order of preference), the first
 * that splits the header into several fields and every one of the next records into as many,
 * read with CsvRecords' quote rules; else the one giving the header most fields; else `,`.
 */
final readonly class CsvDialect
{
    public const string UTF8 = 'UTF-8';

    public const string WINDOWS_1252 = 'Windows-1252';

    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /** In order of preference. */
    private const array DELIMITERS = [';', ',', "\t"];

    private const string DEFAULT_DELIMITER = ',';

    /** Records with text looked at to choose the separator, the header included. */
    private const int SAMPLE_RECORDS = 10;

    /** Records looked at in all, blank ones included, so blank lines cost nothing here. */
    private const int MAX_SCANNED_RECORDS = 1000;

    /** Excel's first line naming the separator, which it hides and does not count as a row. */
    private const string SEPARATOR_LINE = '/\Asep=([^\r\n"])(?:\r\n|\n|\r|\z)/';

    public function __construct(
        public string $encoding,
        public string $delimiter,
        public bool $hasSeparatorLine = false,
    ) {}

    public static function detect(string $content): self
    {
        $text = self::withoutBom($content);
        $encoding = mb_check_encoding($text, self::UTF8) ? self::UTF8 : self::WINDOWS_1252;

        // Separators, quotes and line breaks are the same bytes in both encodings.
        if (preg_match(self::SEPARATOR_LINE, $text, $match) === 1) {
            return new self($encoding, $match[1], hasSeparatorLine: true);
        }

        return new self($encoding, self::delimiterOf($text));
    }

    /**
     * The text in UTF-8, without BOM or `sep=` line. Windows-1252 bytes it does not define become «?».
     */
    public function toUtf8(string $content): string
    {
        $text = self::withoutBom($content);

        if ($this->hasSeparatorLine) {
            $text = (string) preg_replace(self::SEPARATOR_LINE, '', $text, 1);
        }

        return $this->encoding === self::UTF8 ? $text : mb_convert_encoding($text, self::UTF8, self::WINDOWS_1252);
    }

    private static function withoutBom(string $content): string
    {
        return str_starts_with($content, self::UTF8_BOM) ? substr($content, strlen(self::UTF8_BOM)) : $content;
    }

    private static function delimiterOf(string $text): string
    {
        $widest = self::DEFAULT_DELIMITER;
        $widestCount = 1;

        foreach (self::DELIMITERS as $delimiter) {
            $counts = self::fieldCounts($text, $delimiter);
            $header = $counts[0] ?? 0;

            if ($header < 2) {
                continue;
            }

            if (count(array_unique($counts)) === 1) {
                return $delimiter;
            }

            if ($header > $widestCount) {
                $widest = $delimiter;
                $widestCount = $header;
            }
        }

        return $widest;
    }

    /**
     * Fields of the first records with text, read with this separator.
     *
     * @return list<int>
     */
    private static function fieldCounts(string $text, string $delimiter): array
    {
        $counts = [];
        $scanned = 0;

        try {
            foreach (CsvRecords::read($text, $delimiter, XlsxPreflight::MAX_SHEET_COLUMNS, XlsxPreflight::MAX_SHEET_COLUMNS) as $fields) {
                if (++$scanned > self::MAX_SCANNED_RECORDS) {
                    break;
                }

                if (trim(implode('', $fields)) === '') {
                    continue;
                }

                $counts[] = count($fields);

                if (count($counts) >= self::SAMPLE_RECORDS) {
                    break;
                }
            }
        } catch (SpreadsheetReadException) {
            // A record too wide with this separator: it wins, so reading refuses the file as too wide
            // instead of taking it as a single column.
            $counts[] = PHP_INT_MAX;
        }

        return $counts;
    }
}
