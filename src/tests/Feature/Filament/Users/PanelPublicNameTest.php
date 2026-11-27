<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public name can change from the panel but never go back to empty (core#855): an account
 * without one keeps choosing it itself, on the site.
 */
final class PanelPublicNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
        Notification::fake();
    }

    public function test_the_public_name_can_be_changed(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->edit($user, 'Lady Ana')->assertHasNoFormErrors();

        $this->assertSame('Lady Ana', $user->refresh()->display_name);
    }

    public function test_an_account_with_a_public_name_cannot_be_left_without_it(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->edit($user, '')->assertHasFormErrors(['display_name' => 'required']);
        $this->edit($user, null)->assertHasFormErrors(['display_name' => 'required']);

        $this->assertSame('Ana', $user->refresh()->display_name);
    }

    public function test_an_account_without_a_public_name_can_still_be_saved_without_one(): void
    {
        $user = UserModel::factory()->create(['display_name' => null]);

        $this->edit($user, null)->assertHasNoFormErrors();

        $this->assertNull($user->refresh()->display_name);
    }

    /**
     * @return Testable<EditUser>
     */
    private function edit(UserModel $user, ?string $publicName): Testable
    {
        return Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['display_name' => $publicName])
            ->call('save');
    }
}
