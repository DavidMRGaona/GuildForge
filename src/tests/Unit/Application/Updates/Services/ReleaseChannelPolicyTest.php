<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Updates\Services;

use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use PHPUnit\Framework\TestCase;

final class ReleaseChannelPolicyTest extends TestCase
{
    public function test_includes_prereleases_when_installed_version_is_prerelease(): void
    {
        $policy = new ReleaseChannelPolicy(allowPrereleases: false);

        $this->assertTrue($policy->includesPrereleasesFor(ModuleVersion::fromString('1.0.8-beta')));
    }

    public function test_excludes_prereleases_for_stable_install_unless_allowed(): void
    {
        $stable = ModuleVersion::fromString('1.0.8');

        $this->assertFalse((new ReleaseChannelPolicy(allowPrereleases: false))->includesPrereleasesFor($stable));
        $this->assertTrue((new ReleaseChannelPolicy(allowPrereleases: true))->includesPrereleasesFor($stable));
    }
}
