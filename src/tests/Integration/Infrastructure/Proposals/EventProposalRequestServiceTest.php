<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Proposals;

use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Domain\Proposals\Exceptions\InvalidProposalTransitionException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalRequestNotAppliedException;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventProposalRequestModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\Support\Proposals\FixtureEventProposalRequest;
use Tests\TestCase;

final class EventProposalRequestServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string KEY = FixtureEventProposalRequest::KEY;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
        FixtureEventProposalRequest::reset();
        app(EventProposalRequestRegistryInterface::class)->clear();
        app(EventProposalRequestRegistryInterface::class)->register(FixtureEventProposalRequest::class, 'fixture');
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: new ProposalTypeSettingsDTO(enabled: true),
            articles: new ProposalTypeSettingsDTO(enabled: true),
        ));
    }

    protected function tearDown(): void
    {
        FixtureEventProposalRequest::reset();

        parent::tearDown();
    }

    private function requests(): EventProposalRequestServiceInterface
    {
        return app(EventProposalRequestServiceInterface::class);
    }

    private function proposals(): ProposalServiceInterface
    {
        return app(ProposalServiceInterface::class);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions = []): UserModel
    {
        $user = UserModel::factory()->create();

        if ($permissions !== []) {
            $role = RoleModel::query()->create(['name' => 'role-'.uniqid(), 'display_name' => 'Rol']);
            foreach ($permissions as $key) {
                [$resource, $action] = explode('.', $key, 2);
                $role->permissions()->attach(PermissionModel::query()->firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action])->id);
            }
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    /**
     * @param  ?array<array-key, mixed>  $requests
     */
    private function data(?array $requests): EventProposalDataDTO
    {
        return new EventProposalDataDTO(
            title: 'Jornada de rol',
            description: '<p>Partidas toda la tarde</p>',
            startDate: new DateTimeImmutable('2026-12-05 16:00:00 UTC'),
            endDate: new DateTimeImmutable('2026-12-05 21:00:00 UTC'),
            moduleRequests: $requests,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{wanted: bool, values: array<string, mixed>}>
     */
    private function asked(array $values = ['capacity' => '20', 'waitlist' => '1', 'mode' => 'open'], bool $wanted = true): array
    {
        return [self::KEY => ['wanted' => $wanted, 'values' => $values]];
    }

    private function row(string $eventId): ?EventProposalRequestModel
    {
        return EventProposalRequestModel::query()->where('event_id', $eventId)->first();
    }

    /**
     * A proposal awaiting review, with what its author asked for.
     *
     * @param  ?array<array-key, mixed>  $requests
     * @return array{0: string, 1: UserModel}
     */
    private function pending(?array $requests = null): array
    {
        $author = $this->userWith();
        $id = $this->proposals()->createEvent((string) $author->id, $this->data($requests ?? $this->asked()));
        $this->proposals()->submit((string) $author->id, ProposalType::Event, $id);

        return [$id, $author];
    }

    public function test_the_form_offers_only_available_requests(): void
    {
        $this->assertSame([self::KEY], array_map(static fn ($definition): string => $definition->key, $this->requests()->definitions()));

        FixtureEventProposalRequest::$available = false;

        $this->assertSame([], $this->requests()->definitions());
    }

    public function test_check_keeps_only_requests_asked_for_and_reports_refused_fields(): void
    {
        $result = $this->requests()->check([
            ...$this->asked(['capacity' => '0', 'mode' => 'open']),
            'other.unknown' => ['wanted' => true, 'values' => ['x' => 1]],
        ]);

        $this->assertSame([self::KEY => ['capacity' => 'min']], $result->errors);
        $this->assertSame([], $this->requests()->check($this->asked(wanted: false))->requests);
    }

    public function test_the_draft_stores_what_the_author_asks_for_and_drops_what_they_untick(): void
    {
        $author = $this->userWith();
        $id = $this->proposals()->createEvent((string) $author->id, $this->data($this->asked()));

        $this->assertSame(['capacity' => 20, 'waitlist' => true, 'mode' => 'open', 'note' => null], $this->row($id)?->requested_values);
        $this->assertTrue($this->row($id)->apply);

        // A form that says nothing about the requests leaves them alone
        $this->proposals()->updateEvent((string) $author->id, $id, $this->data(null));
        $this->assertNotNull($this->row($id));

        $this->proposals()->updateEvent((string) $author->id, $id, $this->data($this->asked(wanted: false)));
        $this->assertNull($this->row($id));
    }

    public function test_invalid_values_are_refused_and_nothing_is_saved(): void
    {
        $author = $this->userWith();

        try {
            $this->proposals()->createEvent((string) $author->id, $this->data($this->asked(['capacity' => 'many', 'mode' => 'open'])));
            $this->fail('Invalid request values were accepted.');
        } catch (InvalidProposalDataException $exception) {
            $this->assertSame('invalid_requests', $exception->reason);
        }

        $this->assertSame(0, EventModel::query()->includingUnsubmittedProposals()->count());
    }

    public function test_requests_of_a_module_no_longer_offered_stay_and_show_as_inactive(): void
    {
        $author = $this->userWith();
        $id = $this->proposals()->createEvent((string) $author->id, $this->data($this->asked()));

        $stored = $this->requests()->forEvent($id);
        $this->assertCount(1, $stored);
        $this->assertTrue($stored[0]->active);
        $this->assertSame('Inscripciones', $stored[0]->label);
        $this->assertSame('Inscripciones, aforo de 20, con lista de espera', $stored[0]->description);

        app(EventProposalRequestRegistryInterface::class)->clear();
        // Saving the draft again does not drop what is no longer offered
        $this->proposals()->updateEvent((string) $author->id, $id, $this->data([]));

        $stored = $this->requests()->forEvent($id);
        $this->assertFalse($stored[0]->active);
        $this->assertSame(self::KEY, $stored[0]->label);
        $this->assertNull($stored[0]->description);
        $this->assertSame([], $stored[0]->fields);
    }

    public function test_approving_applies_the_values_the_reviewer_approved(): void
    {
        [$id] = $this->pending();
        $reviewer = $this->userWith(['events.review']);

        $this->requests()->review((string) $reviewer->id, $id, [self::KEY => ['apply' => true, 'values' => ['capacity' => '12', 'waitlist' => '0', 'mode' => 'members']]]);
        $this->proposals()->approve((string) $reviewer->id, ProposalType::Event, $id);

        $this->assertSame(['capacity' => 12, 'waitlist' => false, 'mode' => 'members', 'note' => null], FixtureEventProposalRequest::configOf($id));
        $this->assertNotNull($this->row($id)?->applied_at);
        $this->assertSame(ProposalStatus::Approved, EventModel::query()->find($id)?->proposalStatus());
    }

    public function test_an_unticked_request_is_not_applied(): void
    {
        [$id] = $this->pending();
        $reviewer = $this->userWith(['events.review']);

        $this->requests()->review((string) $reviewer->id, $id, [self::KEY => ['apply' => false, 'values' => ['mode' => 'open']]]);
        $this->proposals()->approve((string) $reviewer->id, ProposalType::Event, $id);

        $this->assertNull(FixtureEventProposalRequest::configOf($id));
        $this->assertNull($this->row($id)?->applied_at);
    }

    public function test_a_failing_request_rolls_the_whole_approval_back(): void
    {
        [$id] = $this->pending();
        $reviewer = $this->userWith(['events.review']);
        FixtureEventProposalRequest::$failWith = ProposalRequestRefusedException::because('El espacio no está libre.');

        try {
            $this->proposals()->approve((string) $reviewer->id, ProposalType::Event, $id);
            $this->fail('The approval went on without its request.');
        } catch (ProposalRequestNotAppliedException $exception) {
            $this->assertSame('request_failed', $exception->reason);
            $this->assertSame('Inscripciones', $exception->requestLabel);
            $this->assertSame('El espacio no está libre.', $exception->detail);
        }

        $this->assertSame(ProposalStatus::PendingReview, EventModel::query()->find($id)?->proposalStatus());
        $this->assertNull($this->row($id)?->applied_at);
    }

    public function test_an_unexpected_failure_hides_its_message(): void
    {
        [$id] = $this->pending();
        FixtureEventProposalRequest::$failWith = new RuntimeException('SQLSTATE secret');

        try {
            $this->proposals()->approve((string) $this->userWith(['events.review'])->id, ProposalType::Event, $id);
            $this->fail('The approval went on without its request.');
        } catch (ProposalRequestNotAppliedException $exception) {
            $this->assertNull($exception->detail);
        }
    }

    public function test_existing_configuration_is_never_overwritten(): void
    {
        [$id] = $this->pending();
        $reviewer = $this->userWith(['events.review']);
        // Configuration that came from somewhere else (data touched by hand)
        app(FixtureEventProposalRequest::class)->apply(EventId::fromString($id), ['capacity' => 99]);

        $this->expectException(ProposalRequestNotAppliedException::class);

        try {
            $this->proposals()->approve((string) $reviewer->id, ProposalType::Event, $id);
        } finally {
            $this->assertSame(['capacity' => 99], FixtureEventProposalRequest::configOf($id));
        }
    }

    public function test_a_ticked_request_of_an_inactive_module_blocks_the_approval_until_unticked(): void
    {
        [$id] = $this->pending();
        $reviewer = $this->userWith(['events.review']);
        FixtureEventProposalRequest::$available = false;

        try {
            $this->proposals()->approve((string) $reviewer->id, ProposalType::Event, $id);
            $this->fail('A request of an inactive module was skipped.');
        } catch (ProposalRequestNotAppliedException $exception) {
            $this->assertSame('request_unavailable', $exception->reason);
        }

        // Its values cannot change, but it can be unticked
        $this->requests()->review((string) $reviewer->id, $id, [self::KEY => ['apply' => false, 'values' => ['capacity' => 'ignored']]]);
        $this->proposals()->approve((string) $reviewer->id, ProposalType::Event, $id);

        $this->assertSame(ProposalStatus::Approved, EventModel::query()->find($id)?->proposalStatus());
        $this->assertSame(20, $this->row($id)?->requested_values['capacity'] ?? null);
        $this->assertNull(FixtureEventProposalRequest::configOf($id));
    }

    public function test_publishing_directly_applies_the_requested_values(): void
    {
        $author = $this->userWith(['events.publish_proposals_directly']);
        $id = $this->proposals()->createEvent((string) $author->id, $this->data($this->asked()));

        $this->assertSame(ProposalStatus::Approved, $this->proposals()->submit((string) $author->id, ProposalType::Event, $id));
        $this->assertSame(20, FixtureEventProposalRequest::configOf($id)['capacity'] ?? null);
    }

    public function test_publishing_directly_waits_for_review_when_a_request_fails(): void
    {
        $author = $this->userWith(['events.publish_proposals_directly']);
        $id = $this->proposals()->createEvent((string) $author->id, $this->data($this->asked()));
        FixtureEventProposalRequest::$failWith = ProposalRequestRefusedException::because('No.');

        $this->assertSame(ProposalStatus::PendingReview, $this->proposals()->submit((string) $author->id, ProposalType::Event, $id));
        $this->assertNull(FixtureEventProposalRequest::configOf($id));
    }

    public function test_only_reviewers_decide_and_only_while_it_awaits_review(): void
    {
        [$id, $author] = $this->pending();

        try {
            $this->requests()->review((string) $author->id, $id, [self::KEY => ['apply' => false]]);
            $this->fail('The author reviewed their own requests.');
        } catch (ProposalNotAllowedException) {
            $this->assertTrue($this->row($id)?->apply);
        }

        $reviewer = $this->userWith(['events.review']);
        $this->proposals()->returnToDraft((string) $reviewer->id, ProposalType::Event, $id, 'Falta el lugar');

        $this->expectException(InvalidProposalTransitionException::class);
        $this->requests()->review((string) $reviewer->id, $id, [self::KEY => ['apply' => false]]);
    }

    public function test_the_reviewer_cannot_approve_values_the_request_refuses(): void
    {
        [$id] = $this->pending();

        $this->expectException(InvalidProposalDataException::class);
        $this->requests()->review((string) $this->userWith(['events.review'])->id, $id, [self::KEY => ['apply' => true, 'values' => ['capacity' => '9999', 'mode' => 'open']]]);
    }

    public function test_deleting_the_event_deletes_its_requests(): void
    {
        [$id] = $this->pending();

        EventModel::query()->findOrFail($id)->delete();

        $this->assertSame(0, EventProposalRequestModel::query()->count());
    }
}
