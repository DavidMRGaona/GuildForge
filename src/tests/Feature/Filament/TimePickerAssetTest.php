<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Static JS is served as immutable for a year: the script URL must change with its
 * content, or admins keep running the version their browser cached.
 */
final class TimePickerAssetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string SCRIPT = 'js/admin/time-picker.js';

    private ?string $publicPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    protected function tearDown(): void
    {
        if ($this->publicPath !== null) {
            File::deleteDirectory($this->publicPath);
        }

        parent::tearDown();
    }

    public function test_the_script_url_carries_a_version_derived_from_its_content(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee($this->scriptUrl((string) md5_file(public_path(self::SCRIPT))), false);
    }

    public function test_changing_the_script_changes_its_url(): void
    {
        $this->publicPath = sys_get_temp_dir().'/time-picker-asset-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->publicPath.'/js/admin');
        File::put($this->publicPath.'/'.self::SCRIPT, '// a changed time picker');
        $this->app->usePublicPath($this->publicPath);
        $this->withoutVite();

        $this->get('/admin')
            ->assertOk()
            ->assertSee($this->scriptUrl(md5('// a changed time picker')), false);
    }

    public function test_the_panel_still_loads_when_the_script_is_missing(): void
    {
        $this->publicPath = sys_get_temp_dir().'/time-picker-asset-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->publicPath);
        $this->app->usePublicPath($this->publicPath);
        $this->withoutVite();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('<script src="'.asset(self::SCRIPT).'"></script>', false);
    }

    private function scriptUrl(string $hash): string
    {
        return asset(self::SCRIPT).'?v='.substr($hash, 0, 12);
    }
}
