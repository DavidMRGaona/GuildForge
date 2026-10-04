<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\CoreUpdatesPage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class CoreUpdatesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_page_is_forbidden_for_editors(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());

        $this->get(CoreUpdatesPage::getUrl())->assertForbidden();
    }

    public function test_page_is_accessible_by_admins(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(CoreUpdatesPage::getUrl())->assertOk();
    }
}
