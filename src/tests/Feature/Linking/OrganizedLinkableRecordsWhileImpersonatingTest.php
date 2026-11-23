<?php

declare(strict_types=1);

namespace Tests\Feature\Linking;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Linking\OrganizerFakeLinkableRecordSource;
use Tests\TestCase;

/**
 * A consumer passes the request's user to organizedBy(): while impersonating that is the
 * impersonated account, so the administrator sees what that person organizes, never their own.
 */
final class OrganizedLinkableRecordsWhileImpersonatingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/_test/linking/organized', static fn (Request $request, LinkableRecordLookupInterface $lookup): array => array_map(
            static fn (LinkableRecordDTO $record): string => $record->id,
            $lookup->organizedBy('organized-fake', $request->user() ?? abort(401)),
        ));

        $this->app->make(LinkableRecordSourceRegistryInterface::class)->register(OrganizerFakeLinkableRecordSource::class, 'example-module');
    }

    protected function tearDown(): void
    {
        OrganizerFakeLinkableRecordSource::$organized = [];
        OrganizerFakeLinkableRecordSource::$organizerCalls = [];

        parent::tearDown();
    }

    /**
     * @return array<string, array{ImpersonationMode}>
     */
    public static function modes(): array
    {
        return ['read only' => [ImpersonationMode::ReadOnly], 'simulate' => [ImpersonationMode::Simulate]];
    }

    #[DataProvider('modes')]
    public function test_the_impersonated_account_is_the_organizer(ImpersonationMode $mode): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        OrganizerFakeLinkableRecordSource::$organized = [
            $admin->id => [new LinkableRecordDTO(type: 'organized-fake', id: 'admins-table', title: 'Mesa de la administración')],
            $member->id => [new LinkableRecordDTO(type: 'organized-fake', id: 'members-table', title: 'Mesa del miembro')],
        ];
        $this->actingAs($admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($admin, $member, $mode);

        $this->getJson('/_test/linking/organized')->assertExactJson(['members-table']);
    }
}
