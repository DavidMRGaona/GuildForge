<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images\ImageFixtures;
use Tests\TestCase;

final class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updating_the_profile_is_throttled(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('profile.update'), [
                'name' => $user->name,
                'email' => "new{$attempt}@example.com",
            ])->assertRedirect();
        }

        $this->post(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new7@example.com',
        ])->assertTooManyRequests();
    }

    public function test_the_profile_has_its_own_limit_apart_from_resending_the_verification_link(): void
    {
        Notification::fake();
        $user = UserModel::factory()->unverified()->create();
        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect();
        }
        $this->post(route('verification.send'))->assertTooManyRequests();

        $this->post(route('profile.update'), [
            'name' => $user->name,
            'email' => 'corrected@example.com',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_saving_the_profile_with_the_pending_email_keeps_the_change_without_a_new_link(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['pending_email' => 'new@example.com']);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'Renamed',
            'email' => 'new@example.com',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('new@example.com', $user->pending_email);
        Notification::assertNothingSent();
    }

    public function test_saving_the_profile_with_the_current_email_cancels_the_pending_change(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['pending_email' => 'new@example.com']);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_asking_for_another_email_replaces_the_pending_change(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['pending_email' => 'new@example.com']);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => 'other@example.com',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('other@example.com', $user->refresh()->pending_email);
        Notification::assertSentTo($user, VerifyPendingEmailNotification::class);
    }

    public function test_uploading_an_avatar_alone_keeps_the_account_fields_and_the_pending_email(): void
    {
        Notification::fake();
        Storage::fake('images');
        $user = UserModel::factory()->create([
            'name' => 'Original name',
            'display_name' => 'Original display',
            'pending_email' => 'new@example.com',
        ]);
        $email = $user->email;

        $this->actingAs($user)->post(route('profile.update'), [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.jpg', ImageFixtures::jpeg()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar_public_id);
        $this->assertSame('Original name', $user->name);
        $this->assertSame('Original display', $user->display_name);
        $this->assertSame($email, $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        Notification::assertNothingSent();
    }

    public function test_uploading_an_avatar_alone_deletes_the_previous_one(): void
    {
        Notification::fake();
        Storage::fake('images');
        Storage::disk('images')->put('users/old-avatar.jpg', 'old');
        $user = UserModel::factory()->create(['avatar_public_id' => 'users/old-avatar.jpg']);

        $this->actingAs($user)->post(route('profile.update'), [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.jpg', ImageFixtures::jpeg()),
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('users/old-avatar.jpg', $user->refresh()->avatar_public_id);
        Storage::disk('images')->assertMissing('users/old-avatar.jpg');
    }

    public function test_a_profile_update_needs_the_account_fields_or_an_avatar(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), [])
            ->assertSessionHasErrors(['name']);
    }

    public function test_the_account_fields_still_need_the_email(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), ['name' => 'Renamed'])
            ->assertSessionHasErrors(['email']);
    }
}
