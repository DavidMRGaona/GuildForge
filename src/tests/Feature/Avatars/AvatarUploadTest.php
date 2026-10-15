<?php

declare(strict_types=1);

namespace Tests\Feature\Avatars;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use finfo;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Images\ImageFixtures;
use Tests\TestCase;

/**
 * Avatars uploaded from the profile or the admin panel are stored with the extension of
 * their bytes; animated ones keep every frame and are marked "-animated" in their name,
 * which is all the frontend has to ask for animated delivery.
 */
final class AvatarUploadTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        Notification::fake();
        config(['images.optimization.enabled' => true]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function animatedAvatars(): array
    {
        return [
            'GIF' => [ImageFixtures::gif(2), 'avatar.gif', 'gif'],
            'GIF without loop block' => [ImageFixtures::gif(2, loop: false), 'avatar.gif', 'gif'],
            'GIF sent with a .jpg name' => [ImageFixtures::gif(2), 'avatar.jpg', 'gif'],
            'APNG' => [ImageFixtures::png(2), 'avatar.png', 'png'],
            'WebP' => [ImageFixtures::webp(2), 'avatar.webp', 'webp'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function staticAvatars(): array
    {
        return [
            'JPEG' => [ImageFixtures::jpeg(), 'avatar.jpg', 'jpg'],
            'PNG' => [ImageFixtures::png(), 'avatar.png', 'png'],
            // Re-encoded as the type the upload claims: the name follows the stored bytes
            'PNG sent with a .jpg name' => [ImageFixtures::png(), 'avatar.jpg', 'jpg'],
            'GIF with one frame' => [ImageFixtures::gif(1), 'avatar.gif', 'gif'],
        ];
    }

    #[DataProvider('animatedAvatars')]
    public function test_an_animated_avatar_uploaded_from_the_profile_keeps_its_frames_and_is_marked(string $contents, string $fileName, string $extension): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->createWithContent($fileName, $contents),
        ])->assertSessionHasNoErrors();

        $path = (string) $user->refresh()->avatar_public_id;
        $this->assertMatchesRegularExpression('#^users/'.$user->id.'/'.self::UUID.'-animated\.'.$extension.'$#', $path);
        $this->assertSame($contents, Storage::disk('images')->get($path));
    }

    #[DataProvider('staticAvatars')]
    public function test_a_static_avatar_uploaded_from_the_profile_gets_the_extension_of_its_bytes(string $contents, string $fileName, string $extension): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->createWithContent($fileName, $contents),
        ])->assertSessionHasNoErrors();

        $path = (string) $user->refresh()->avatar_public_id;
        $this->assertMatchesRegularExpression('#^users/'.$user->id.'/'.self::UUID.'\.'.$extension.'$#', $path);
        $this->assertStoredAs($extension, $path);
    }

    #[DataProvider('animatedAvatars')]
    public function test_an_animated_avatar_uploaded_from_the_panel_keeps_its_frames_and_is_marked(string $contents, string $fileName, string $extension): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $user = UserModel::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['avatar_public_id' => UploadedFile::fake()->createWithContent($fileName, $contents)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = (string) $user->refresh()->avatar_public_id;
        $this->assertMatchesRegularExpression('#^avatars/\d{4}/\d{2}/'.self::UUID.'-animated\.'.$extension.'$#', $path);
        $this->assertSame($contents, Storage::disk('images')->get($path));
    }

    #[DataProvider('staticAvatars')]
    public function test_a_static_avatar_uploaded_from_the_panel_gets_the_extension_of_its_bytes(string $contents, string $fileName, string $extension): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $user = UserModel::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['avatar_public_id' => UploadedFile::fake()->createWithContent($fileName, $contents)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = (string) $user->refresh()->avatar_public_id;
        $this->assertMatchesRegularExpression('#^avatars/\d{4}/\d{2}/'.self::UUID.'\.'.$extension.'$#', $path);
        $this->assertStoredAs($extension, $path);
    }

    private function assertStoredAs(string $extension, string $path): void
    {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer((string) Storage::disk('images')->get($path));
        $this->assertSame($extension, ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'][$mimeType] ?? $mimeType);
    }
}
