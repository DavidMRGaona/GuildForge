<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The shared prop `impersonation` feeds the site's band: it exists only while an impersonation
 * runs and says who is being viewed and in which mode, nothing about the administrator.
 */
final class SharedImpersonationPropTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->member = UserModel::factory()->create(['name' => 'member-name', 'display_name' => 'Ana Example']);
    }

    public function test_a_guest_gets_no_impersonation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
    }

    public function test_a_signed_in_account_without_impersonation_gets_none(): void
    {
        $this->actingAs($this->admin)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
    }

    public function test_the_read_only_mode_is_shared_with_the_target_name_only(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('impersonation', ['targetName' => 'Ana Example', 'mode' => 'read_only']));
    }

    public function test_the_simulate_mode_is_shared(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('impersonation.mode', 'simulate'));
    }

    public function test_nothing_about_the_administrator_is_shared(): void
    {
        $this->startImpersonating(ImpersonationMode::ReadOnly);

        $response = $this->get('/')->assertOk();

        $this->assertStringNotContainsString($this->admin->email, (string) $response->getContent());
    }

    private function startImpersonating(ImpersonationMode $mode): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->start($this->admin, $this->member, $mode);
    }
}
