<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Authorization\ValueObjects\PermissionKey;
use App\Domain\Authorization\ValueObjects\RoleName;
use App\Domain\Exceptions\InvalidHexColorException;
use App\Domain\Exceptions\InvalidSlugException;
use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use App\Domain\Modules\Exceptions\InvalidModuleVersionException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\ValueObjects\HexColor;
use App\Domain\ValueObjects\Slug;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PCRE's `$` also matches before a final newline, so "abc\n" used to pass patterns that
 * promise a strict format. Each value object ends its pattern in `\z`.
 */
final class ValueObjectsTrailingNewlineTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): mixed, class-string<\Throwable>}>
     */
    public static function valuesWithATrailingNewline(): iterable
    {
        yield 'slug' => [fn () => new Slug("my-slug\n"), InvalidSlugException::class];
        yield 'hex colour, six digits' => [fn () => new HexColor("#A1B2C3\n"), InvalidHexColorException::class];
        yield 'hex colour, three digits' => [fn () => new HexColor("#abc\n"), InvalidHexColorException::class];
        yield 'role name' => [fn () => new RoleName("editor\n"), InvalidArgumentException::class];
        yield 'permission key' => [fn () => new PermissionKey("events.view\n"), InvalidArgumentException::class];
        yield 'module permission key' => [fn () => new PermissionKey("tables:tables.view\n"), InvalidArgumentException::class];
        yield 'module name' => [fn () => new ModuleName("game-tables\n"), InvalidModuleNameException::class];
        yield 'module version' => [fn () => ModuleVersion::fromString("1.2.3\n"), InvalidModuleVersionException::class];
        yield 'module version with a pre-release' => [fn () => ModuleVersion::fromString("1.2.3-beta.1\n"), InvalidModuleVersionException::class];
        yield 'guide id' => [fn () => GuideId::fromString("core.site.events\n"), InvalidGuideIdException::class];
    }

    /**
     * @param  Closure(): mixed  $build
     * @param  class-string<\Throwable>  $exception
     */
    #[DataProvider('valuesWithATrailingNewline')]
    public function test_a_value_with_a_trailing_newline_is_rejected(Closure $build, string $exception): void
    {
        $this->expectException($exception);

        $build();
    }

    public function test_the_same_values_without_the_newline_are_accepted(): void
    {
        $this->assertSame('my-slug', (string) new Slug('my-slug'));
        $this->assertSame('#A1B2C3', (string) new HexColor('#A1B2C3'));
        $this->assertSame('editor', (string) new RoleName('editor'));
        $this->assertSame('events.view', (string) new PermissionKey('events.view'));
        $this->assertSame('game-tables', (string) new ModuleName('game-tables'));
        $this->assertSame('1.2.3', (string) ModuleVersion::fromString('1.2.3'));
        $this->assertSame('core.site.events', GuideId::fromString('core.site.events')->value());
    }
}
