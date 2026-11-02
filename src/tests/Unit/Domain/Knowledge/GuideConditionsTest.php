<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Knowledge;

use App\Domain\Knowledge\Exceptions\InvalidGuideDocumentException;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use PHPUnit\Framework\TestCase;

final class GuideConditionsTest extends TestCase
{
    public function test_negated_capabilities_are_split_from_normal_ones(): void
    {
        $c = GuideConditions::fromArray(['capabilities' => ['a.b', '!c.d']]);

        $this->assertSame(['a.b'], $c->capabilities);
        $this->assertSame(['c.d'], $c->negatedCapabilities);
        $this->assertSame(['a.b', 'c.d'], $c->capabilityKeys());
    }

    public function test_it_round_trips_through_to_array(): void
    {
        $original = [
            'capabilities' => ['a.b', '!c.d'],
            'association_member' => false,
            'modules' => ['event-registrations'],
            'permissions' => ['all' => ['x.y'], 'any' => ['z.w']],
        ];

        $c = GuideConditions::fromArray($original);

        $this->assertEquals($original, $c->toArray());
        $this->assertEquals($c, GuideConditions::fromArray($c->toArray()));
    }

    public function test_it_rejects_permissions_without_all_or_any(): void
    {
        $this->expectException(InvalidGuideDocumentException::class);

        GuideConditions::fromArray(['permissions' => ['x']]);
    }

    public function test_it_rejects_a_non_boolean_association_member(): void
    {
        $this->expectException(InvalidGuideDocumentException::class);

        GuideConditions::fromArray(['association_member' => 'yes']);
    }

    public function test_it_rejects_non_list_of_strings(): void
    {
        $this->expectException(InvalidGuideDocumentException::class);

        GuideConditions::fromArray(['modules' => 'event-registrations']);
    }

    public function test_it_rejects_unknown_keys(): void
    {
        $this->expectException(InvalidGuideDocumentException::class);

        GuideConditions::fromArray(['roles' => ['admin']]);
    }

    public function test_empty_conditions_are_allowed(): void
    {
        $c = GuideConditions::fromArray([]);

        $this->assertSame([], $c->capabilities);
        $this->assertNull($c->associationMember);
        $this->assertSame([], $c->toArray());
    }
}
