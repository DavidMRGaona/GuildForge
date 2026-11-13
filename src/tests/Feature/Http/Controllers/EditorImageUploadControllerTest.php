<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Content\Services\EditorImageUploadAuthorizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Images inserted from the web rich editor (core#573).
 */
final class EditorImageUploadControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string URL = '/editor/imagenes/articulos';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
    }

    public function test_guests_cannot_upload(): void
    {
        $this->postJson(self::URL, ['image' => UploadedFile::fake()->image('a.png')])->assertUnauthorized();
    }

    public function test_it_refuses_by_default_whoever_may_not_create_articles(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->postJson(self::URL, ['image' => UploadedFile::fake()->image('a.png')])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_an_authorized_person_stores_the_image_in_the_articles_editor_folder(): void
    {
        $response = $this->actingAs(UserModel::factory()->admin()->create())
            ->postJson(self::URL, ['image' => UploadedFile::fake()->image('Mi foto.PNG', 40, 30)])
            ->assertCreated();

        $id = $response->json('id');
        $this->assertIsString($id);
        $this->assertMatchesRegularExpression(
            '~^'.preg_quote(EditorImageOrigin::Articles->directory(now()), '~').'/[0-9a-f-]{36}\.png$~',
            $id,
        );
        Storage::disk('images')->assertExists($id);
        $this->assertSame(Storage::disk('images')->url($id), $response->json('url'));
        $this->assertSame(EditorImageOrigin::Articles, EditorImageOrigin::ofPath($id));
    }

    public function test_the_authorization_is_whatever_the_container_binds(): void
    {
        $this->app->instance(EditorImageUploadAuthorizerInterface::class, new class implements EditorImageUploadAuthorizerInterface
        {
            public function canUpload(UserModel $user, EditorImageOrigin $origin): bool
            {
                return $origin === EditorImageOrigin::Articles;
            }
        });

        $this->actingAs(UserModel::factory()->create())
            ->postJson(self::URL, ['image' => UploadedFile::fake()->image('a.webp')])
            ->assertCreated();
    }

    public function test_it_validates_type_and_size_on_the_server(): void
    {
        $this->app->instance(EditorImageUploadAuthorizerInterface::class, new class implements EditorImageUploadAuthorizerInterface
        {
            public function canUpload(UserModel $user, EditorImageOrigin $origin): bool
            {
                return true;
            }
        });
        $this->actingAs(UserModel::factory()->create());

        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(self::URL, ['image' => UploadedFile::fake()->create('a.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(self::URL, ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson(self::URL, ['image' => UploadedFile::fake()->image('big.png')->size(2049)])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        // A script named as an image
        // (a real file: the fake one reports the type from its name)
        $script = tempnam(sys_get_temp_dir(), 'editor');
        file_put_contents($script, '<?php echo 1;');
        $this->postJson(self::URL, ['image' => new UploadedFile($script, 'x.png', 'image/png', null, true)])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_uploads_have_their_own_limit_per_account(): void
    {
        $this->app->instance(EditorImageUploadAuthorizerInterface::class, new class implements EditorImageUploadAuthorizerInterface
        {
            public function canUpload(UserModel $user, EditorImageOrigin $origin): bool
            {
                return true;
            }
        });
        $this->actingAs(UserModel::factory()->create());

        for ($i = 0; $i < 20; $i++) {
            $this->postJson(self::URL, ['image' => UploadedFile::fake()->image('a.png')])->assertCreated();
        }

        $this->postJson(self::URL, ['image' => UploadedFile::fake()->image('a.png')])->assertTooManyRequests();
    }
}
