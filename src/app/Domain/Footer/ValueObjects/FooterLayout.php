<?php

declare(strict_types=1);

namespace App\Domain\Footer\ValueObjects;

use InvalidArgumentException;

/**
 * The configurable footer (core#697): up to four columns of site blocks, stored in the settings row
 * `footer_layout` as `{"version":1,"columns":[{"blocks":[{"key","title","settings"}]}]}`.
 *
 * Reading never fails: anything unreadable (no row, another version, a malformed entry) is left out,
 * and a layout without any block is empty, so the site keeps the fixed footer it always had.
 */
final readonly class FooterLayout
{
    public const int VERSION = 1;

    public const int MAX_COLUMNS = 4;

    /** Per column: a footer is a summary, not a page */
    public const int MAX_BLOCKS_PER_COLUMN = 6;

    /**
     * @param  list<FooterColumn>  $columns
     */
    private function __construct(
        public array $columns,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param  list<FooterColumn>  $columns
     *
     * @throws InvalidArgumentException with more than MAX_COLUMNS columns or MAX_BLOCKS_PER_COLUMN blocks in one
     */
    public static function of(array $columns): self
    {
        if (count($columns) > self::MAX_COLUMNS) {
            throw new InvalidArgumentException(sprintf('A footer has at most %d columns.', self::MAX_COLUMNS));
        }

        foreach ($columns as $column) {
            if (count($column->blocks) > self::MAX_BLOCKS_PER_COLUMN) {
                throw new InvalidArgumentException(sprintf('A footer column has at most %d blocks.', self::MAX_BLOCKS_PER_COLUMN));
            }
        }

        return new self($columns);
    }

    /**
     * The stored layout, decoded. Columns and blocks beyond the limits are cut.
     */
    public static function fromArray(mixed $data): self
    {
        if (! is_array($data) || ($data['version'] ?? null) !== self::VERSION || ! is_array($data['columns'] ?? null)) {
            return self::empty();
        }

        $columns = [];

        foreach ($data['columns'] as $column) {
            if (! is_array($column)) {
                continue;
            }

            $columns[] = new FooterColumn(array_slice(self::blocksOf($column['blocks'] ?? null), 0, self::MAX_BLOCKS_PER_COLUMN));

            if (count($columns) === self::MAX_COLUMNS) {
                break;
            }
        }

        return new self($columns);
    }

    /**
     * Whether no column holds a block: the site then draws its fixed footer.
     */
    public function isEmpty(): bool
    {
        return array_all($this->columns, fn ($column) => $column->blocks === []);
    }

    /**
     * @return array{version: int, columns: list<array{blocks: list<array{key: string, title: string|null, settings: array<string, mixed>}>}>}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'columns' => array_map(static fn (FooterColumn $column): array => $column->toArray(), $this->columns),
        ];
    }

    /**
     * @return list<FooterBlock>
     */
    private static function blocksOf(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['key'] ?? null) || $block['key'] === '') {
                continue;
            }

            $title = $block['title'] ?? null;
            $settings = $block['settings'] ?? null;

            $result[] = new FooterBlock(
                $block['key'],
                is_string($title) ? $title : null,
                is_array($settings) ? self::stringKeyed($settings) : [],
            );
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $settings
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $settings): array
    {
        return array_filter($settings, is_string(...), ARRAY_FILTER_USE_KEY);
    }
}
