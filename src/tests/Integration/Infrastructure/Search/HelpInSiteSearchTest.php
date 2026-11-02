<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Search\DatabaseSiteSearch;
use App\Infrastructure\Search\Search\HelpHitsProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\Knowledge\RecordingLogger;
use Tests\TestCase;

final class HelpInSiteSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array TYPES = ['events', 'articles', 'galleries'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_help_rows_appear_for_signed_in_users_only(): void
    {
        $user = $this->member();

        $withUser = $this->search('contraseña', user: $user);
        $guest = $this->search('contraseña', user: null);
        $without = $this->search('contraseña', user: $user, includeHelp: false);

        $this->assertSame(['help'], array_values(array_unique(array_map(static fn (SiteSearchHitDTO $hit): string => $hit->type, $withUser->hits))));
        $this->assertNotSame([], $withUser->hits);
        $this->assertSame([], $guest->hits);
        $this->assertSame([], $without->hits);

        $hit = $withUser->hits[0];
        $this->assertSame(route('knowledge.show', 'core.account.change-password'), $hit->url);
        $this->assertSame(__('knowledge.categories.account'), $hit->meta);
        $this->assertFalse($hit->past);
        $this->assertNull($hit->startsAt);
        $this->assertNull($hit->imagePublicId);
        $this->assertNotSame([], $hit->snippet);
    }

    public function test_help_is_hidden_when_email_must_be_verified(): void
    {
        $user = $this->member();
        $policy = $this->createMock(EmailVerificationPolicyInterface::class);
        $policy->method('mustVerify')->willReturn(true);
        $this->app->instance(EmailVerificationPolicyInterface::class, $policy);

        $provider = $this->app->make(HelpHitsProvider::class);

        $this->assertFalse($provider->availableFor($user));
        $this->assertFalse($provider->availableFor(null));
    }

    public function test_help_is_available_when_email_need_not_be_verified(): void
    {
        $this->assertTrue($this->app->make(HelpHitsProvider::class)->availableFor($this->member()));
    }

    public function test_help_is_unavailable_and_logged_when_the_policy_fails(): void
    {
        $logger = new RecordingLogger;
        $policy = $this->createMock(EmailVerificationPolicyInterface::class);
        $policy->method('mustVerify')->willThrowException(new RuntimeException('settings unreadable'));
        $provider = $this->app->make(HelpHitsProvider::class, [
            'emailVerification' => $policy,
            'logger' => $logger,
        ]);

        $this->assertFalse($provider->availableFor($this->member()));
        $this->assertCount(1, $logger->records);
    }

    public function test_help_rows_go_after_documents_in_fallback(): void
    {
        $user = $this->member();
        $this->document(['title' => 'Cambio de contraseña en el club']);
        $this->document(['title' => 'Otra contraseña']);

        $result = $this->search('contraseña', user: $user);

        $types = array_map(static fn (SiteSearchHitDTO $hit): string => $hit->type, $result->hits);
        $this->assertSame(['events', 'events'], array_slice($types, 0, 2));
        $this->assertSame(['help'], array_values(array_unique(array_slice($types, 2))));
        $this->assertGreaterThan(2, count($types));

        // Documents fill the first pages, the help rows the pages after them
        $second = $this->search('contraseña', user: $user, offset: 1, limit: 2);
        $this->assertSame(['events', 'help'], array_map(static fn (SiteSearchHitDTO $hit): string => $hit->type, $second->hits));
        $this->assertSame($result->total, $second->total);

        $beyond = $this->search('contraseña', user: $user, offset: 2, limit: 1);
        $this->assertSame(['help'], array_map(static fn (SiteSearchHitDTO $hit): string => $hit->type, $beyond->hits));
        $this->assertSame($result->hits[2]->url, $beyond->hits[0]->url);
    }

    public function test_help_tab_counts_visible_guides_only(): void
    {
        $user = $this->member();
        $this->document(['title' => 'Cambio de contraseña en el club']);

        $all = $this->search('contraseña', user: $user);
        $help = $this->search('contraseña', user: $user, type: 'help');
        $events = $this->search('contraseña', user: $user, type: 'events');
        // The verification guide needs the verification setting, so it is not visible and not counted
        $hidden = $this->search('reenviar', user: $user);

        $this->assertSame(1, $all->counts['events']);
        $this->assertArrayHasKey('help', $all->counts);
        $this->assertSame(1 + $all->counts['help'], $all->total);
        $this->assertSame($all->counts, $help->counts);
        $this->assertSame($all->counts['help'], $help->total);
        $this->assertCount($help->total, $help->hits);
        $this->assertSame(['help'], array_values(array_unique(array_map(static fn (SiteSearchHitDTO $hit): string => $hit->type, $help->hits))));
        $this->assertSame(1, $events->total);
        $this->assertSame(['events'], array_map(static fn (SiteSearchHitDTO $hit): string => $hit->type, $events->hits));
        $this->assertArrayNotHasKey('help', $hidden->counts);
        $this->assertSame([], $hidden->hits);
    }

    public function test_help_counts_are_left_out_when_nothing_matches(): void
    {
        $result = $this->search('zzzzqqqq', user: $this->member());

        $this->assertSame([], $result->counts);
        $this->assertSame(0, $result->total);
    }

    public function test_help_alone_is_returned_without_audiences_or_types(): void
    {
        $result = $this->search('contraseña', user: $this->member(), audiences: [], types: []);

        $this->assertNotSame([], $result->hits);
        $this->assertSame(['help'], array_keys($result->counts));
    }

    /**
     * @param  list<SearchAudience>  $audiences
     * @param  list<string>  $types
     */
    private function search(
        string $query,
        ?object $user,
        array $audiences = [SearchAudience::Public],
        array $types = self::TYPES,
        ?string $type = null,
        int $offset = 0,
        int $limit = 20,
        bool $includeHelp = true,
    ): SiteSearchResultDTO {
        return $this->app->make(DatabaseSiteSearch::class)->search(new SiteSearchQueryDTO(
            query: $query,
            audiences: $audiences,
            types: $types,
            type: $type,
            offset: $offset,
            limit: $limit,
            user: $user,
            includeHelp: $includeHelp,
        ));
    }

    private function member(): UserModel
    {
        $user = UserModel::factory()->create();
        $this->app->make(MemberRoleServiceInterface::class)->assignTo($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(array $attributes): void
    {
        SearchDocumentModel::query()->create([
            'type' => 'events',
            'source_id' => (string) Str::uuid(),
            'body' => '',
            'keywords' => 'partida',
            'url' => '/'.Str::uuid(),
            'audience' => SearchAudience::Public->value,
            'locale' => 'es',
            ...$attributes,
        ]);
    }
}
