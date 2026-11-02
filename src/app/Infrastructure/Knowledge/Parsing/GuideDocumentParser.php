<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Parsing;

use App\Application\Knowledge\DTOs\ParsedGuideDTO;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideDocumentException;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;

final class GuideDocumentParser
{
    private const int DEFAULT_ORDER = 100;

    public function parse(string $path, string $contents): ParsedGuideDTO
    {
        try {
            $result = (new FrontMatterParser(new SymfonyYamlFrontMatterParser))->parse($contents);
        } catch (CommonMarkException $e) {
            throw $this->fail($path, 'front matter', 'invalid YAML ('.$e->getMessage().')');
        }

        $data = $result->getFrontMatter();
        if (! is_array($data) || $data === []) {
            throw $this->fail($path, 'front matter', 'missing or empty front matter block');
        }

        $audienceValue = $this->requiredString($path, $data, 'audience');
        $audience = GuideAudience::tryFrom($audienceValue);
        if ($audience === null) {
            throw $this->fail($path, 'audience', "must be 'site' or 'panel', got '{$audienceValue}'");
        }

        try {
            $conditions = GuideConditions::fromArray($this->conditionsArray($path, $data));
        } catch (InvalidGuideDocumentException $e) {
            throw $this->fail($path, 'conditions', $e->getMessage());
        }

        return new ParsedGuideDTO(
            id: $this->guideId($path, 'id', $this->requiredString($path, $data, 'id')),
            previousIds: $this->idList($path, $data, 'previous_ids'),
            title: $this->requiredString($path, $data, 'title'),
            summary: $this->requiredString($path, $data, 'summary'),
            categoryId: $this->requiredString($path, $data, 'category'),
            audience: $audience,
            order: $this->order($path, $data),
            keywords: $this->stringList($path, $data, 'keywords'),
            related: $this->idList($path, $data, 'related'),
            conditions: $conditions,
            featured: $this->featured($path, $data),
            body: $result->getContent(),
            contentHash: sha1($contents),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function requiredString(string $path, array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw $this->fail($path, $field, 'is required and must be a non-empty string');
        }

        return trim($value);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function order(string $path, array $data): int
    {
        if (! array_key_exists('order', $data)) {
            return self::DEFAULT_ORDER;
        }
        if (! is_int($data['order'])) {
            throw $this->fail($path, 'order', 'must be an integer');
        }

        return $data['order'];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function featured(string $path, array $data): bool
    {
        if (! array_key_exists('featured', $data)) {
            return false;
        }
        if (! is_bool($data['featured'])) {
            throw $this->fail($path, 'featured', 'must be true or false');
        }

        return $data['featured'];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function conditionsArray(string $path, array $data): array
    {
        $conditions = $data['conditions'] ?? [];
        if (! is_array($conditions)) {
            throw $this->fail($path, 'conditions', 'must be a map');
        }

        return $conditions;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private function stringList(string $path, array $data, string $field): array
    {
        $value = $data[$field] ?? [];
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->fail($path, $field, 'must be a list of strings');
        }
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw $this->fail($path, $field, 'must be a list of strings');
            }
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<GuideId>
     */
    private function idList(string $path, array $data, string $field): array
    {
        return array_map(
            fn (string $id): GuideId => $this->guideId($path, $field, $id),
            $this->stringList($path, $data, $field),
        );
    }

    private function guideId(string $path, string $field, string $value): GuideId
    {
        try {
            return GuideId::fromString($value);
        } catch (InvalidGuideIdException $e) {
            throw $this->fail($path, $field, $e->getMessage());
        }
    }

    private function fail(string $path, string $field, string $reason): InvalidGuideDocumentException
    {
        return InvalidGuideDocumentException::because(sprintf("%s: '%s' %s", $path, $field, $reason));
    }
}
