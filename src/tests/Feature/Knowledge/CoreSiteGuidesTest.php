<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CoreSiteGuidesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Guides every signed-in account sees, by category. */
    private const array GUIDES_BY_CATEGORY = [
        'getting-started' => ['core.help.welcome', 'core.help.use-help', 'core.search.site-search'],
        'account' => ['core.account.complete-profile', 'core.account.change-password', 'core.account.change-email'],
        'events-and-games' => ['core.events.find-events'],
        'community' => ['core.content.read-articles', 'core.content.browse-galleries'],
    ];

    private const array FEATURED = ['core.help.welcome', 'core.account.complete-profile', 'core.events.find-events'];

    private const string VERIFY_EMAIL_GUIDE = 'core.account.verify-email';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_every_signed_in_user_sees_the_core_site_guides(): void
    {
        $this->actingAs($this->member());

        $this->get('/ayuda')->assertOk()->assertInertia(function (Assert $page): void {
            /** @var list<array{id: string, guideCount: int}> $categories */
            $categories = $page->toArray()['props']['home']['categories'];
            $counts = array_column($categories, 'guideCount', 'id');

            foreach (self::GUIDES_BY_CATEGORY as $category => $guides) {
                $this->assertArrayHasKey($category, $counts);
                $this->assertGreaterThanOrEqual(count($guides), $counts[$category]);
            }

            /** @var list<array{id: string}> $featured */
            $featured = $page->toArray()['props']['home']['featured'];
            foreach (self::FEATURED as $id) {
                $this->assertContains($id, array_column($featured, 'id'));
            }
        });

        foreach (self::GUIDES_BY_CATEGORY as $category => $guides) {
            $this->get('/ayuda/categoria/'.$category)->assertOk()->assertInertia(function (Assert $page) use ($guides): void {
                /** @var list<array{id: string}> $listed */
                $listed = $page->toArray()['props']['page']['guides'];
                foreach ($guides as $id) {
                    $this->assertContains($id, array_column($listed, 'id'));
                }
            });
        }
    }

    public function test_each_guide_renders_its_buttons_and_markers(): void
    {
        $this->actingAs($this->member());
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');

        foreach ([...array_merge(...array_values(self::GUIDES_BY_CATEGORY)), self::VERIFY_EMAIL_GUIDE] as $id) {
            $this->get('/ayuda/'.$id)->assertOk()->assertInertia(function (Assert $page) use ($id): void {
                $guide = $page->toArray()['props']['guide'];
                // Markers are only resolved in the body
                $this->assertStringNotContainsString('{{', $guide['title'].$guide['summary'], $id);

                $html = $guide['html'];
                $this->assertIsString($html);
                $this->assertStringNotContainsString('[[', $html, $id);
                $this->assertStringNotContainsString('{{', $html, $id);
                $this->assertStringNotContainsString('[!', $html, $id);
                $this->assertStringContainsString('class="kb-action"', $html, $id.' has no action button');
            });
        }
    }

    public function test_guides_name_the_association_role_only_through_its_marker(): void
    {
        $files = glob(resource_path('knowledge/es/site/*.md')) ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\bsocios?\b/iu', (string) file_get_contents($file), $file);
        }
    }

    public function test_the_verify_email_guide_is_hidden_when_verification_is_not_required(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '0');
        $this->actingAs($this->member());

        $this->get('/ayuda/'.self::VERIFY_EMAIL_GUIDE)->assertNotFound();
    }

    public function test_the_verify_email_guide_is_shown_when_verification_is_required(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
        $this->actingAs($this->member());

        $this->get('/ayuda/'.self::VERIFY_EMAIL_GUIDE)->assertOk();
    }

    private function member(): UserModel
    {
        $user = UserModel::factory()->create();
        app(MemberRoleServiceInterface::class)->assignTo($user);

        return $user;
    }
}
