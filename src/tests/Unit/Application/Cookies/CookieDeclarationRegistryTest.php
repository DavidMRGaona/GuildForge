<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cookies;

use App\Application\Cookies\DTOs\DeclaredCookieDTO;
use App\Application\Cookies\Services\CookieDeclarationRegistryInterface;
use App\Domain\Cookies\Enums\CookieType;
use App\Infrastructure\Cookies\CookieDeclarationRegistry;
use Tests\TestCase;

final class CookieDeclarationRegistryTest extends TestCase
{
    public function test_declared_cookies_are_listed_in_declaration_order(): void
    {
        $registry = new CookieDeclarationRegistry;
        $first = $this->cookie('first_cookie');
        $second = $this->cookie('second_cookie');

        $registry->declare($first);
        $registry->declare($second);

        $this->assertSame([$first, $second], $registry->all());
    }

    public function test_declaring_the_same_name_again_replaces_the_earlier_entry(): void
    {
        $registry = new CookieDeclarationRegistry;
        $registry->declare($this->cookie('first_cookie', 'Old purpose'));
        $registry->declare($this->cookie('second_cookie'));
        $registry->declare($this->cookie('first_cookie', 'New purpose'));

        $this->assertCount(2, $registry->all());
        $this->assertSame('New purpose', $registry->all()[0]->purpose);
    }

    public function test_a_cookie_is_first_party_unless_said_otherwise(): void
    {
        $this->assertSame(CookieType::FirstParty, $this->cookie('first_cookie')->type);
    }

    public function test_the_application_shares_one_registry(): void
    {
        $this->app->make(CookieDeclarationRegistryInterface::class)->declare($this->cookie('shared_cookie'));

        $this->assertSame(
            ['shared_cookie'],
            array_map(
                static fn (DeclaredCookieDTO $cookie): string => $cookie->name,
                $this->app->make(CookieDeclarationRegistryInterface::class)->all(),
            ),
        );
    }

    private function cookie(string $name, string $purpose = 'Remembers something'): DeclaredCookieDTO
    {
        return new DeclaredCookieDTO(
            name: $name,
            categorySlug: 'preferences',
            provider: 'Example guild',
            purpose: $purpose,
            duration: '90 días',
        );
    }
}
