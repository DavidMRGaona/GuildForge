<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * «Solo mirar» never lets a site submission reach its controller: the whole request is
 * refused before it, with a notice for page visits and a machine-readable 403 for JSON.
 */
final class BlockSiteWritesWhenReadOnlyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $member;

    private int $reached = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            Route::middleware('web')->{$verb}('/_test/readonly/submit', function (): array {
                $this->reached++;

                return ['saved' => true];
            });
        }

        Route::middleware('web')->get('/_test/readonly/read', static fn (): array => ['read' => true]);

        $this->admin = UserModel::factory()->admin()->create();
        $this->member = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
    }

    public function test_an_inertia_submission_goes_back_with_the_notice(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->from('/_test/readonly/read')
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/_test/readonly/submit')
            ->assertRedirect('/_test/readonly/read')
            ->assertSessionHas('error', 'Estás viendo el sitio como Ana Prueba: no se guardan cambios.');

        $this->assertSame(0, $this->reached);
    }

    public function test_a_json_submission_is_refused_with_the_code_and_the_header(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->postJson('/_test/readonly/submit')
            ->assertForbidden()
            ->assertHeader('X-Impersonation-Read-Only', '1')
            ->assertJsonPath('error', 'impersonation_read_only')
            ->assertJsonPath('message', 'Estás viendo el sitio como Ana Prueba: no se guardan cambios.');

        $this->assertSame(0, $this->reached);
    }

    public function test_every_method_but_get_and_head_is_refused(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        foreach (['putJson', 'patchJson', 'deleteJson'] as $call) {
            $this->{$call}('/_test/readonly/submit')->assertForbidden();
        }

        $this->get('/_test/readonly/read')->assertOk();
        $this->call('HEAD', '/_test/readonly/read')->assertOk();
        $this->assertSame(0, $this->reached);
    }

    public function test_signing_out_and_going_back_are_not_blocked(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->post(route('impersonation.stop'))->assertRedirect();
        $this->assertFalse($this->isImpersonating());
    }

    public function test_logging_out_is_not_blocked(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_subscribing_to_realtime_channels_is_not_blocked(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->postJson(route('realtime.auth'))->assertNotFound();
    }

    public function test_livewire_requests_are_left_to_the_panel_block(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $uri = app('livewire')->getUpdateUri();
        $response = $this->postJson($uri, [], ['X-Livewire' => 'true']);

        $this->assertNotSame('impersonation_read_only', $response->json('error'));
    }

    public function test_a_livewire_header_does_not_exempt_a_site_route(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->postJson('/_test/readonly/submit', [], ['X-Livewire' => 'true'])
            ->assertForbidden()
            ->assertJsonPath('error', 'impersonation_read_only');

        $this->assertSame(0, $this->reached);
    }

    public function test_simulate_lets_the_submission_reach_the_controller(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->postJson('/_test/readonly/submit')->assertOk()->assertJson(['saved' => true]);

        $this->assertSame(1, $this->reached);
    }

    public function test_without_an_impersonation_nothing_changes(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/_test/readonly/submit')->assertOk();

        $this->assertSame(1, $this->reached);
    }

    private function startImpersonating(ImpersonationMode $mode): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $this->member, $mode);
    }

    private function isImpersonating(): bool
    {
        return session()->has('impersonation');
    }
}
