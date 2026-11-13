<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use PHPUnit\Framework\TestCase;

final class ModuleSetFingerprintTest extends TestCase
{
    public function test_the_same_modules_in_any_order_give_the_same_fingerprint(): void
    {
        $one = ModuleSetFingerprint::of([$this->module('forum', '1.0.0'), $this->module('surveys', '1.6.0')]);
        $other = ModuleSetFingerprint::of([$this->module('surveys', '1.6.0'), $this->module('forum', '1.0.0')]);

        $this->assertTrue($one->equals($other));
        $this->assertSame($one->value, $other->value);
    }

    public function test_another_version_gives_another_fingerprint(): void
    {
        $before = ModuleSetFingerprint::of([$this->module('forum', '1.0.0')]);
        $after = ModuleSetFingerprint::of([$this->module('forum', '1.0.1')]);

        $this->assertFalse($before->equals($after));
    }

    public function test_another_module_gives_another_fingerprint(): void
    {
        $before = ModuleSetFingerprint::of([$this->module('surveys', '1.6.0')]);
        $after = ModuleSetFingerprint::of([$this->module('surveys', '1.6.0'), $this->module('forum', '1.0.0')]);

        $this->assertFalse($before->equals($after));
    }

    public function test_no_modules_have_a_fingerprint_of_their_own(): void
    {
        $none = ModuleSetFingerprint::of([]);

        $this->assertTrue($none->equals(ModuleSetFingerprint::of([])));
        $this->assertFalse($none->equals(ModuleSetFingerprint::of([$this->module('forum', '1.0.0')])));
    }

    public function test_a_stored_value_reads_back_as_the_same_fingerprint(): void
    {
        $fingerprint = ModuleSetFingerprint::of([$this->module('forum', '1.0.0')]);

        $this->assertTrue(ModuleSetFingerprint::fromString($fingerprint->value)->equals($fingerprint));
    }

    private function module(string $name, string $version): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: $name,
            description: '',
            version: ModuleVersion::fromString($version),
            author: '',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
        );
    }
}
