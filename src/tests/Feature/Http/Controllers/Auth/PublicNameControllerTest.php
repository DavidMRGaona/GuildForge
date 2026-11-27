<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Domain\Events\UserProfileUpdated;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * «Elige tu nombre público» (core#855): an account without a public name chooses one, once. The
 * suggestion is only the first word of the account name, never a surname, and nothing is saved
 * until the person confirms.
 */
final class PublicNameControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_screen_suggests_only_the_first_word_of_the_account_name(): void
    {
        $this->actingAs($this->namelessUser('Ana Example Sample'));

        $this->get(route('public-name.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ChoosePublicName')
                ->where('suggestion', 'Ana'));
    }

    public function test_showing_the_screen_saves_nothing(): void
    {
        $user = $this->namelessUser('Ana Example');
        $this->actingAs($user);

        $this->get(route('public-name.show'))->assertOk();

        $this->assertNull($user->fresh()?->display_name);
    }

    public function test_choosing_saves_the_public_name_and_moves_the_profile_address(): void
    {
        Event::fake([UserProfileUpdated::class]);
        $user = $this->namelessUser('Ana Example');
        $this->actingAs($user);

        $this->post(route('public-name.store'), ['display_name' => 'Lady Ana'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', __('auth.public_name.saved'));

        $this->assertSame('Lady Ana', $user->fresh()?->display_name);
        $this->assertSame('lady-ana', UserProfileModel::query()->findOrFail($user->id)->handle);
        Event::assertDispatched(UserProfileUpdated::class, static fn (UserProfileUpdated $event): bool => $event->userId === (string) $user->id);
    }

    public function test_choosing_trims_the_name(): void
    {
        $user = $this->namelessUser('Ana Example');
        $this->actingAs($user);

        $this->post(route('public-name.store'), ['display_name' => '  Ana  ']);

        $this->assertSame('Ana', $user->fresh()?->display_name);
    }

    public function test_after_choosing_the_page_asked_for_opens(): void
    {
        $this->actingAs($this->namelessUser('Ana Example'));
        $this->withSession(['url.intended' => url('/perfil')]);

        $this->post(route('public-name.store'), ['display_name' => 'Ana'])
            ->assertRedirect(url('/perfil'));
    }

    public function test_an_empty_public_name_is_refused(): void
    {
        $user = $this->namelessUser('Ana Example');
        $this->actingAs($user);

        foreach (['', '   ', null] as $value) {
            $this->post(route('public-name.store'), ['display_name' => $value])
                ->assertSessionHasErrors('display_name');
        }

        $this->assertNull($user->fresh()?->display_name);
    }

    public function test_a_public_name_longer_than_the_column_is_refused(): void
    {
        $this->actingAs($this->namelessUser('Ana Example'));

        $this->post(route('public-name.store'), ['display_name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('display_name');
    }

    public function test_an_account_that_already_has_one_is_sent_on_and_keeps_it(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->actingAs($user);

        $this->get(route('public-name.show'))->assertRedirect(route('home'));
        $this->post(route('public-name.store'), ['display_name' => 'Otra'])->assertRedirect(route('home'));

        $this->assertSame('Ana', $user->fresh()?->display_name);
    }

    public function test_guests_cannot_open_the_screen(): void
    {
        $this->get(route('public-name.show'))->assertRedirect(route('login'));
        $this->post(route('public-name.store'), ['display_name' => 'Ana'])->assertRedirect(route('login'));
    }

    private function namelessUser(string $name): UserModel
    {
        return UserModel::factory()->create(['name' => $name, 'display_name' => null]);
    }
}
