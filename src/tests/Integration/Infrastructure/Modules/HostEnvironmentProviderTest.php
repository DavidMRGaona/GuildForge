<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules;

use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\HostEnvironmentProvider;
use Mockery;
use Tests\TestCase;

final class HostEnvironmentProviderTest extends TestCase
{
    public function test_it_normalizes_composer_pretty_versions(): void
    {
        $this->assertSame('3.3.56', HostEnvironmentProvider::normalizeVersion('v3.3.56')?->value());
        $this->assertSame('12.69.3', HostEnvironmentProvider::normalizeVersion('12.69.3')?->value());
        $this->assertSame('4.0.0', HostEnvironmentProvider::normalizeVersion('v4.0.0-beta1')?->value());
    }

    public function test_a_non_numeric_version_is_unknown(): void
    {
        $this->assertNull(HostEnvironmentProvider::normalizeVersion('dev-main'));
        $this->assertNull(HostEnvironmentProvider::normalizeVersion(null));
    }

    public function test_it_describes_the_running_host_once_per_process(): void
    {
        $core = Mockery::mock(CoreVersionServiceInterface::class);
        $core->shouldReceive('getCurrentVersion')->once()->andReturn(new ModuleVersion(2, 6, 0));
        $provider = new HostEnvironmentProvider($core);

        $host = $provider->current();

        $this->assertSame('2.6.0', $host->core->value());
        $this->assertSame(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION, $host->php->value());
        $this->assertSame(12, $host->laravel->major);
        $this->assertSame(3, $host->filament?->major);
        $this->assertContains('json', $host->extensions);
        $this->assertSame($host, $provider->current());
    }

    public function test_it_is_a_singleton_in_the_container(): void
    {
        $this->assertSame(app(HostEnvironmentProviderInterface::class), app(HostEnvironmentProviderInterface::class));
    }
}
