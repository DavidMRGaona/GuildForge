<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Http\Middleware\BlockSiteWritesWhenImpersonatingReadOnly;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Profiles\FakeProfileStat;
use Tests\TestCase;

/**
 * The owner edits their profile from the profile itself (core#743): `PUT /miembros/{handle}` and the
 * banner routes, only for the owner (nobody else, administrators included), signed in, throttled and
 * refused by a read-only impersonation.
 */
final class PublicProfileEditControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var MediaFileReleaserInterface&MockInterface */
    private MediaFileReleaserInterface $releaser;

    /**
     * Every request starts afresh, as in production: the profile rows are bound scoped.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @return TestResponse<Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->releaser = Mockery::spy(MediaFileReleaserInterface::class);
        $this->app->instance(MediaFileReleaserInterface::class, $this->releaser);
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'UTC'));
    }

    public function test_the_owner_saves_everything_in_one_request_and_lands_on_the_new_address(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put('/miembros/ana-prueba', $this->payload([
                'handle' => 'ana-rol',
                'visibility' => 'public',
                'bio' => 'Juego a rol desde 1998.',
                'sections' => [['key' => 'core.about', 'enabled' => false]],
            ]))
            ->assertRedirect('/miembros/ana-rol')
            ->assertSessionHas('success', 'Perfil guardado.');

        $profile = $this->profileOf($owner);
        $this->assertSame('ana-rol', $profile->handle);
        $this->assertTrue($profile->handle_chosen);
        $this->assertSame(ProfileVisibility::Public, $profile->visibility);
        $this->assertSame('Juego a rol desde 1998.', $profile->bio);
        $this->assertSame([['key' => 'core.about', 'enabled' => false]], $profile->layout->toArray()['sections']);

        // The old address still leads there
        $this->get('/miembros/ana-prueba')->assertRedirect('/miembros/ana-rol');
    }

    public function test_a_guest_is_sent_to_sign_in_and_nothing_is_saved(): void
    {
        $owner = $this->owner();

        $this->put('/miembros/ana-prueba', $this->payload(['bio' => 'Intruso']))->assertRedirect(route('login'));
        $this->post('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')])->assertRedirect(route('login'));
        $this->getJson('/miembros/ana-prueba/disponibilidad?direccion=otra')->assertUnauthorized();

        $this->assertNull($this->profileOf($owner)->bio);
    }

    public function test_nobody_but_the_owner_edits_it_not_even_an_administrator(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        UserProfileModel::query()->whereKey($owner->id)->update(['banner_public_id' => 'profiles/banners/2026/10/a.jpg']);
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $admin = UserModel::factory()->admin()->create();

        foreach ([$other, $admin] as $intruder) {
            $this->actingAs($intruder)->put('/miembros/ana-prueba', $this->payload(['bio' => 'Intruso']))->assertNotFound();
            $this->actingAs($intruder)->post('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')])->assertNotFound();
            $this->actingAs($intruder)->delete('/miembros/ana-prueba/banner')->assertNotFound();
            $this->actingAs($intruder)->getJson('/miembros/ana-prueba/disponibilidad?direccion=otra')->assertNotFound();
        }

        $profile = $this->profileOf($owner);
        $this->assertNull($profile->bio);
        $this->assertSame('profiles/banners/2026/10/a.jpg', $profile->banner_public_id);
        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_an_old_or_differently_written_address_is_not_where_the_owner_edits(): void
    {
        $owner = $this->owner();
        $owner->update(['display_name' => 'Ana Otra']);

        $this->actingAs($owner)->put('/miembros/ana-prueba', $this->payload(['handle' => 'ana-otra']))->assertNotFound();
        $this->actingAs($owner)->put('/miembros/ANA-OTRA', $this->payload(['handle' => 'ana-otra']))->assertNotFound();
        $this->actingAs($owner)->put('/miembros/ana-otra', $this->payload(['handle' => 'ana-otra']))->assertRedirect('/miembros/ana-otra');
    }

    public function test_each_field_is_validated_and_nothing_is_saved_when_one_fails(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->putJson('/miembros/ana-prueba', [
                'handle' => 'a',
                'visibility' => 'everybody',
                'bio' => str_repeat('ñ', 501),
                'show_membership' => 'quizá',
                'sections' => [['key' => 'core.about', 'enabled' => 'sí'], ['key' => 'forged.section', 'enabled' => true]],
                'stats' => 'none',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['handle', 'visibility', 'bio', 'show_membership', 'sections.0.enabled', 'sections.1.key', 'stats']);

        $this->assertNull($this->profileOf($owner)->bio);
    }

    public function test_the_text_may_have_five_hundred_characters_counted_as_characters(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->putJson('/miembros/ana-prueba', $this->payload(['bio' => str_repeat('ñ', 500)]))->assertRedirect();

        $this->assertSame(500, mb_strlen((string) $this->profileOf($owner)->bio));
    }

    public function test_only_sections_and_stats_offered_now_are_accepted(): void
    {
        $owner = $this->owner();
        $this->registerStat('core.unavailable', available: false);
        $this->registerStat('core.counted', available: true);

        $this->actingAs($owner)
            ->putJson('/miembros/ana-prueba', $this->payload(['stats' => [['key' => 'core.unavailable', 'enabled' => true]]]))
            ->assertJsonValidationErrors(['stats.0.key']);

        $this->actingAs($owner)
            ->putJson('/miembros/ana-prueba', $this->payload(['stats' => [['key' => 'core.counted', 'enabled' => false], ['key' => 'core.counted', 'enabled' => true]]]))
            ->assertJsonValidationErrors(['stats.1.key']);

        $this->actingAs($owner)
            ->putJson('/miembros/ana-prueba', $this->payload(['stats' => [['key' => 'core.counted', 'enabled' => false]]]))
            ->assertRedirect();
        $this->assertSame([['key' => 'core.counted', 'enabled' => false]], $this->profileOf($owner)->layout->toArray()['stats']);
    }

    public function test_the_membership_badge_can_only_be_shown_by_an_association_member(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->putJson('/miembros/ana-prueba', $this->payload(['show_membership' => true]))
            ->assertJsonValidationErrors(['show_membership']);
        $this->assertFalse($this->profileOf($owner)->show_membership);

        app(AuthorizationServiceInterface::class)->assignRole($owner, AssociationMembershipServiceInterface::DEFAULT_ROLE);

        $this->actingAs($owner)->putJson('/miembros/ana-prueba', $this->payload(['show_membership' => true]))->assertRedirect();
        $this->assertTrue($this->profileOf($owner)->show_membership);
    }

    public function test_an_address_taken_and_a_reserved_word_get_the_same_answer(): void
    {
        $owner = $this->owner();
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $other->update(['display_name' => 'Berta Nueva']);

        $taken = $this->actingAs($owner)->putJson('/miembros/ana-prueba', $this->payload(['handle' => 'berta-nueva']))->assertUnprocessable();
        $held = $this->actingAs($owner)->putJson('/miembros/ana-prueba', $this->payload(['handle' => 'berta-prueba']))->assertUnprocessable();
        $reserved = $this->actingAs($owner)->putJson('/miembros/ana-prueba', $this->payload(['handle' => 'admin']))->assertUnprocessable();

        $this->assertSame(['handle' => ['Esta dirección no está disponible.']], $taken->json('errors'));
        $this->assertSame($taken->json(), $held->json());
        $this->assertSame($taken->json(), $reserved->json());
        $this->assertSame('ana-prueba', $this->profileOf($owner)->handle);
    }

    public function test_a_current_address_that_became_a_reserved_word_still_saves_the_rest(): void
    {
        $owner = $this->owner();
        UserProfileModel::query()->whereKey($owner->id)->update(['handle' => 'staff']);

        $this->actingAs($owner)
            ->put('/miembros/staff', $this->payload(['handle' => 'staff', 'bio' => 'Se guarda']))
            ->assertRedirect('/miembros/staff')
            ->assertSessionHasNoErrors();

        $this->assertSame('Se guarda', $this->profileOf($owner)->bio);

        // Where the save leads opens for real
        $this->actingAs($owner)->get('/miembros/staff')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('profile.header.handle', 'staff')->where('editor.handle', 'staff'));
    }

    public function test_the_address_changes_once_every_thirty_days(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->put('/miembros/ana-prueba', $this->payload(['handle' => 'ana-rol']))->assertRedirect('/miembros/ana-rol');

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $this->actingAs($owner)
            ->putJson('/miembros/ana-rol', $this->payload(['handle' => 'ana-dados', 'bio' => 'No se guarda']))
            ->assertJsonValidationErrors(['handle' => 'Podrás cambiar tu dirección de nuevo a partir del 15/12/2026.']);
        $this->assertSame('ana-rol', $this->profileOf($owner)->handle);
        $this->assertNull($this->profileOf($owner)->bio);

        Carbon::setTestNow(Carbon::parse('2026-12-15 10:00:00', 'UTC'));
        $this->actingAs($owner)->put('/miembros/ana-rol', $this->payload(['handle' => 'ana-dados']))->assertRedirect('/miembros/ana-dados');
    }

    public function test_the_owner_asks_whether_an_address_is_available(): void
    {
        $owner = $this->owner();
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $other->update(['display_name' => 'Berta Nueva']);
        $this->actingAs($owner);

        $this->assertAvailability('ana-prueba', 'current');
        $this->assertAvailability('ana-dados', 'available');
        $this->assertAvailability('berta-nueva', 'unavailable');
        $this->assertAvailability('berta-prueba', 'unavailable');
        $this->assertAvailability('admin', 'unavailable');
        $this->assertAvailability('a', 'malformed');
        $this->getJson('/miembros/ana-prueba/disponibilidad')->assertOk()->assertExactJson(['status' => 'malformed']);
        $this->getJson('/miembros/ana-prueba/disponibilidad?direccion[]=x')->assertOk()->assertExactJson(['status' => 'malformed']);
    }

    public function test_asking_for_addresses_is_throttled(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/miembros/ana-prueba/disponibilidad?direccion=ana-'.$i)->assertOk();
        }

        $this->getJson('/miembros/ana-prueba/disponibilidad?direccion=ana-otra')->assertTooManyRequests();
    }

    public function test_saving_is_throttled(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        for ($i = 0; $i < 10; $i++) {
            $this->putJson('/miembros/ana-prueba', $this->payload(['bio' => 'Intento '.$i]))->assertRedirect();
        }

        $this->putJson('/miembros/ana-prueba', $this->payload(['bio' => 'Uno de más']))->assertTooManyRequests();
        $this->assertSame('Intento 9', $this->profileOf($owner)->bio);
    }

    public function test_banners_are_throttled(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        $this->actingAs($owner);

        for ($i = 0; $i < 6; $i++) {
            $this->delete('/miembros/ana-prueba/banner')->assertRedirect();
        }

        $this->post('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')])->assertTooManyRequests();
        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_the_owner_uploads_and_removes_a_banner(): void
    {
        Storage::fake('images');
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('banner.webp', 1500, 500)])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Banner actualizado.');

        $stored = $this->profileOf($owner)->banner_public_id;
        $this->assertMatchesRegularExpression('#^profiles/banners/2026/11/[0-9a-f-]{36}\.webp$#', (string) $stored);
        Storage::disk('images')->assertExists((string) $stored);

        $this->actingAs($owner)->delete('/miembros/ana-prueba/banner')->assertRedirect()->assertSessionHas('success', 'Banner quitado.');
        $this->assertNull($this->profileOf($owner)->banner_public_id);
        $this->releaser->shouldHaveReceived('release')->once()->with($stored);
    }

    public function test_a_banner_must_be_a_jpeg_png_or_webp_image_of_five_megabytes_at_most(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->postJson('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.gif')])->assertJsonValidationErrors(['banner']);
        $this->postJson('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])->assertJsonValidationErrors(['banner']);
        $this->postJson('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')->size(5121)])->assertJsonValidationErrors(['banner']);
        $this->postJson('/miembros/ana-prueba/banner', [])->assertJsonValidationErrors(['banner']);

        $this->assertSame([], Storage::disk('images')->allFiles());
        $this->assertNull($this->profileOf($owner)->banner_public_id);

        $this->post('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')->size(5120)])->assertSessionHasNoErrors();
        $this->assertNotNull($this->profileOf($owner)->banner_public_id);
    }

    public function test_a_banner_is_judged_by_its_bytes_never_by_its_name(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        $this->actingAs($owner);
        // Drawn by browsers but able to carry scripts, and other things dressed as a JPEG. A real upload,
        // not a fake one: a fake file reports the type its name says
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';

        $this->postJson('/miembros/ana-prueba/banner', ['banner' => $this->uploaded('b.svg', $svg)])->assertJsonValidationErrors(['banner']);
        $this->postJson('/miembros/ana-prueba/banner', ['banner' => $this->uploaded('b.jpg', $svg)])->assertJsonValidationErrors(['banner']);
        $this->postJson('/miembros/ana-prueba/banner', ['banner' => $this->uploaded('b.jpg', "<?php echo 'hola';\n")])->assertJsonValidationErrors(['banner']);

        $this->assertSame([], Storage::disk('images')->allFiles());
        $this->assertNull($this->profileOf($owner)->banner_public_id);
    }

    public function test_a_read_only_impersonation_saves_nothing(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        UserProfileModel::query()->whereKey($owner->id)->update(['banner_public_id' => 'profiles/banners/2026/10/a.jpg']);
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        app(ImpersonationServiceInterface::class)->start($admin, $owner, ImpersonationMode::ReadOnly);

        foreach ([
            $this->putJson('/miembros/ana-prueba', $this->payload(['bio' => 'Mirando'])),
            $this->postJson('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')]),
            $this->deleteJson('/miembros/ana-prueba/banner'),
        ] as $response) {
            $response->assertForbidden()
                ->assertJsonPath('error', BlockSiteWritesWhenImpersonatingReadOnly::JSON_ERROR_CODE)
                ->assertHeader(BlockSiteWritesWhenImpersonatingReadOnly::JSON_ERROR_HEADER, '1');
        }

        // Asking only reads
        $this->getJson('/miembros/ana-prueba/disponibilidad?direccion=ana-rol')->assertOk()->assertExactJson(['status' => 'available']);

        $profile = $this->profileOf($owner);
        $this->assertNull($profile->bio);
        $this->assertSame('profiles/banners/2026/10/a.jpg', $profile->banner_public_id);
        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_a_simulated_impersonation_runs_the_saves_and_keeps_none_of_them(): void
    {
        Storage::fake('images');
        $owner = $this->owner();
        UserProfileModel::query()->whereKey($owner->id)->update(['banner_public_id' => 'profiles/banners/2026/10/a.jpg']);
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        app(ImpersonationServiceInterface::class)->start($admin, $owner, ImpersonationMode::Simulate);

        $this->put('/miembros/ana-prueba', $this->payload(['handle' => 'ana-rol', 'bio' => 'Simulado']))->assertRedirect('/miembros/ana-rol');
        $this->post('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')])->assertRedirect();
        $this->delete('/miembros/ana-prueba/banner')->assertRedirect();

        $profile = $this->profileOf($owner);
        $this->assertSame('ana-prueba', $profile->handle);
        $this->assertNull($profile->bio);
        $this->assertFalse($profile->handle_chosen);
        $this->assertSame('profiles/banners/2026/10/a.jpg', $profile->banner_public_id);
        $this->assertNull(UserProfileHandleModel::query()->find(ProfileHandle::hashOf('ana-rol')));
        $this->assertSame([], Storage::disk('images')->allFiles());
    }

    public function test_the_owner_gets_the_editor_on_the_profile_and_nobody_else_does(): void
    {
        $owner = $this->owner(['avatar_public_id' => 'users/a.jpg']);
        UserProfileModel::query()->whereKey($owner->id)->update(['bio' => 'Hola', 'visibility' => 'public']);

        $this->actingAs($owner)->get('/miembros/ana-prueba')->assertInertia(fn (Assert $page): Assert => $page
            ->where('editor.handle', 'ana-prueba')
            ->where('editor.visibility', 'public')
            ->where('editor.bio', 'Hola')
            ->where('editor.bannerPublicId', null)
            ->where('editor.showMembership', false)
            ->where('editor.canShowMembership', false)
            ->where('editor.nextHandleChangeAt', null)
            ->where('editor.sections.0.key', 'core.about')
            ->where('editor.sections.0.label', 'Sobre mí')
            ->where('editor.sections.0.description', 'Lo que cuentas de ti, con los enlaces que quieras compartir.')
            ->where('editor.sections.0.enabled', true)
            ->where('editor.sections.0.region', 'aside')
            ->where('editor.stats', [])
            ->where('editor.limits.bio', 500)
            ->where('editor.limits.handleChangeDays', 30)
            ->where('editor.limits.bannerKilobytes', 5120)
            ->where('editor.urls.update', route('profiles.update', ['handle' => 'ana-prueba']))
            ->where('editor.urls.availability', route('profiles.handle-availability', ['handle' => 'ana-prueba']))
            ->where('editor.urls.banner', route('profiles.banner.store', ['handle' => 'ana-prueba']))
            ->where('accountUrl', route('profile.show')));

        $this->actingAs(UserModel::factory()->create())->get('/miembros/ana-prueba')
            ->assertInertia(fn (Assert $page): Assert => $page->where('editor', null)->where('accountUrl', null));
        $this->actingAs(UserModel::factory()->admin()->create())->get('/miembros/ana-prueba')
            ->assertInertia(fn (Assert $page): Assert => $page->where('editor', null));
    }

    public function test_an_owner_who_must_verify_the_email_gets_a_notice_instead_of_the_editor(): void
    {
        Storage::fake('images');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
        // The clock is frozen today: the cutoff the migration wrote at the real time may come after it
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $owner = $this->owner(['email_verified_at' => null]);

        $this->actingAs($owner)->get('/miembros/ana-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('editor', null)
                ->where('notices.verifyEmail', true)
                ->where('verifyEmailUrl', route('verification.notice')));

        // The routes refuse too, whatever the page draws
        $this->actingAs($owner)->put('/miembros/ana-prueba', $this->payload(['bio' => 'Sin verificar']))->assertRedirect(route('verification.notice'));
        $this->actingAs($owner)->putJson('/miembros/ana-prueba', $this->payload(['bio' => 'Sin verificar']))
            ->assertForbidden()
            ->assertJsonPath('error', 'email_verification_required');
        $this->actingAs($owner)->postJson('/miembros/ana-prueba/banner', ['banner' => UploadedFile::fake()->image('b.jpg')])->assertForbidden();
        $this->actingAs($owner)->getJson('/miembros/ana-prueba/disponibilidad?direccion=ana-rol')->assertForbidden();

        $this->assertNull($this->profileOf($owner)->bio);
        $this->assertSame([], Storage::disk('images')->allFiles());

        $owner->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($owner)->get('/miembros/ana-prueba')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('editor.handle', 'ana-prueba')
                ->where('notices.verifyEmail', false)
                ->where('verifyEmailUrl', null));
    }

    public function test_the_editor_tells_when_the_address_can_change_again_in_the_display_zone(): void
    {
        config(['app.display_timezone' => 'Europe/Madrid']);
        $owner = $this->owner();
        $this->actingAs($owner)->put('/miembros/ana-prueba', $this->payload(['handle' => 'ana-rol']));

        $this->actingAs($owner)->get('/miembros/ana-rol')
            ->assertInertia(fn (Assert $page): Assert => $page->where('editor.nextHandleChangeAt', '2026-12-15T11:00:00+01:00'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function owner(array $attributes = []): UserModel
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba', ...$attributes]);
        $this->assertSame('ana-prueba', $this->profileOf($owner)->handle);

        return $owner;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'handle' => 'ana-prueba',
            'visibility' => 'members',
            'bio' => null,
            'show_membership' => false,
            'sections' => [['key' => 'core.about', 'enabled' => true]],
            'stats' => [],
            ...$overrides,
        ];
    }

    private function registerStat(string $key, bool $available): void
    {
        $id = 'test-stat-'.$key;
        $this->app->bind($id, static fn (): FakeProfileStat => new class($key, $available) extends FakeProfileStat
        {
            public function __construct(private readonly string $statKey, private readonly bool $available) {}

            public function key(): string
            {
                return $this->statKey;
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        });
        app(ProfileStatRegistryInterface::class)->register($id);
    }

    private function assertAvailability(string $handle, string $status): void
    {
        $this->getJson('/miembros/ana-prueba/disponibilidad?direccion='.urlencode($handle))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['status' => $status]);
    }

    /**
     * A file as a browser sends it: its type is read from its bytes, never from its name.
     */
    private function uploaded(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'banner');
        $this->assertIsString($path);
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return new UploadedFile($path, $name, null, null, true);
    }

    private function profileOf(UserModel $user): UserProfileModel
    {
        return UserProfileModel::query()->findOrFail($user->id);
    }
}
