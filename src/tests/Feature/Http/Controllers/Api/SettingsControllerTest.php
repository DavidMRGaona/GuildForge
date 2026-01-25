<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_location_returns_json_response(): void
    {
        SettingModel::create(['key' => 'location_name', 'value' => 'Test HQ']);
        SettingModel::create(['key' => 'location_address', 'value' => 'Test Address, City']);
        SettingModel::create(['key' => 'location_lat', 'value' => '42.5956']);
        SettingModel::create(['key' => 'location_lng', 'value' => '-8.7644']);
        SettingModel::create(['key' => 'location_zoom', 'value' => '15']);

        $response = $this->getJson('/api/settings/location');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_location_returns_correct_structure(): void
    {
        SettingModel::create(['key' => 'location_name', 'value' => 'Test HQ']);
        SettingModel::create(['key' => 'location_address', 'value' => 'Test Address, City']);
        SettingModel::create(['key' => 'location_lat', 'value' => '42.5956']);
        SettingModel::create(['key' => 'location_lng', 'value' => '-8.7644']);
        SettingModel::create(['key' => 'location_zoom', 'value' => '15']);

        $response = $this->getJson('/api/settings/location');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'name',
            'address',
            'lat',
            'lng',
            'zoom',
        ]);
    }

    public function test_location_returns_default_values_from_seeder(): void
    {
        // Seed default location settings
        SettingModel::create(['key' => 'location_name', 'value' => 'Test HQ']);
        SettingModel::create(['key' => 'location_address', 'value' => 'Test Address, City']);
        SettingModel::create(['key' => 'location_lat', 'value' => '42.5956']);
        SettingModel::create(['key' => 'location_lng', 'value' => '-8.7644']);
        SettingModel::create(['key' => 'location_zoom', 'value' => '15']);

        $response = $this->getJson('/api/settings/location');

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Test HQ',
            'address' => 'Test Address, City',
            'lat' => 42.5956,
            'lng' => -8.7644,
            'zoom' => 15,
        ]);
    }

    public function test_location_includes_cache_headers(): void
    {
        SettingModel::create(['key' => 'location_name', 'value' => 'Test HQ']);
        SettingModel::create(['key' => 'location_address', 'value' => 'Test Address, City']);
        SettingModel::create(['key' => 'location_lat', 'value' => '42.5956']);
        SettingModel::create(['key' => 'location_lng', 'value' => '-8.7644']);
        SettingModel::create(['key' => 'location_zoom', 'value' => '15']);

        $response = $this->getJson('/api/settings/location');

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control', 'max-age=3600, public');
    }
}
