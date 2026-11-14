<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SiteBlocks;

use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteBlockKeyTest extends TestCase
{
    public function test_a_key_is_its_owner_and_a_name(): void
    {
        $key = SiteBlockKey::tryFrom('event-registrations.sign_up');

        $this->assertInstanceOf(SiteBlockKey::class, $key);
        $this->assertSame('event-registrations.sign_up', $key->value);
        $this->assertSame('event-registrations', $key->owner());
        $this->assertSame('sign_up', $key->name());
    }

    public function test_core_keys_belong_to_the_core(): void
    {
        $key = SiteBlockKey::tryFrom('core.upcoming_events');

        $this->assertInstanceOf(SiteBlockKey::class, $key);
        $this->assertTrue($key->isCore());
        $this->assertFalse(SiteBlockKey::tryFrom('newsletter.subscribe')?->isCore() ?? true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            'no owner' => ['subscribe'],
            'three segments' => ['newsletter.forms.subscribe'],
            'empty name' => ['newsletter.'],
            'empty owner' => ['.subscribe'],
            'upper case' => ['Newsletter.subscribe'],
            'spaces' => ['news letter.subscribe'],
            'owner ending in a hyphen' => ['newsletter-.subscribe'],
            'name ending in an underscore' => ['newsletter.subscribe_'],
            'too long' => ['newsletter.'.str_repeat('a', SiteBlockKey::MAX_LENGTH)],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys_are_refused(string $key): void
    {
        $this->assertNull(SiteBlockKey::tryFrom($key));
    }
}
