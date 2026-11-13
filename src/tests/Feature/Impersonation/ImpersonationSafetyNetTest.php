<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Impersonation\RecordingJob;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * While an impersonation lasts nothing persists, in both modes: every request runs in a
 * transaction of the default connection that is always rolled back, and mail, the queue,
 * the cache, the search index and the upload disks are neutralised until the process ends.
 */
final class ImpersonationSafetyNetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        RecordingJob::$handled = 0;
        $this->admin = UserModel::factory()->admin()->create(['name' => 'Admin']);
        $this->member = UserModel::factory()->create(['name' => 'Original name']);

        Route::middleware('web')->group(function (): void {
            Route::get('/_test/net/rename', function (): array {
                UserModel::query()->whereKey($this->member->id)->update(['name' => 'Changed name']);

                return ['name' => UserModel::query()->findOrFail($this->member->id)->name];
            });
            Route::get('/_test/net/rename-and-fail', function (): never {
                UserModel::query()->whereKey($this->member->id)->update(['name' => 'Changed name']);

                throw new RuntimeException('failed after writing');
            });
            Route::get('/_test/net/assign-role', function (AuthorizationServiceInterface $authorization): array {
                $authorization->assignRole($this->member, 'editor');

                return ['entries' => RoleAuditEntryModel::query()->count()];
            });
            Route::get('/_test/net/after-commit', static function (): array {
                $ran = false;
                DB::transaction(static function () use (&$ran): void {
                    DB::afterCommit(static function () use (&$ran): void {
                        $ran = true;
                    });
                });

                return ['ran' => $ran];
            });
            Route::get('/_test/net/mail', static function (): string {
                Mail::raw('Hello', static fn ($message) => $message->to('someone@example.com'));
                Mail::mailer('smtp')->raw('Hello again', static fn ($message) => $message->to('someone@example.com'));

                return 'sent';
            });
            Route::get('/_test/net/queue', static function (): string {
                dispatch(new RecordingJob);
                dispatch(new RecordingJob)->onConnection('sync');

                return 'queued';
            });
            Route::get('/_test/net/cache', static function (): string {
                Cache::forget('net.key');
                Cache::put('net.key', 'during', 60);

                return (string) Cache::get('net.key');
            });
            Route::get('/_test/net/settings', static function (SettingsServiceInterface $settings): string {
                $settings->set('guild_name', 'Reverted guild');

                return (string) $settings->get('guild_name');
            });
            Route::get('/_test/net/disk', static function (): array {
                $disk = Storage::disk('images');
                $read = $disk->get('kept.txt');
                $disk->put('new.txt', 'new');
                $disk->delete('kept.txt');

                return ['read' => $read, 'new_exists' => $disk->exists('new.txt')];
            });
            Route::get('/_test/net/deferred', function (): string {
                defer(function (): void {
                    dispatch(new RecordingJob);
                    UserModel::query()->whereKey($this->member->id)->update(['name' => 'Changed later']);
                });

                return 'deferred';
            });
            Route::get('/_test/net/failed-query-then-continue', static function (): array {
                try {
                    DB::select('select * from impersonation_missing_table');
                } catch (QueryException) {
                    // Caught and carried on, as some repositories do with a unique violation
                }

                return ['users' => UserModel::query()->count()];
            });
            Route::get('/_test/net/failed-transaction-then-continue', static function (): array {
                try {
                    DB::transaction(static function (): void {
                        DB::select('select 1');
                        DB::select('select * from impersonation_missing_table');
                    });
                } catch (QueryException) {
                    // Laravel rolled back to its own savepoint, which drops ours as well
                }

                return ['users' => UserModel::query()->count()];
            });
            Route::get('/_test/net/failed-query-then-render', static function (): never {
                try {
                    DB::select('select * from impersonation_missing_table');
                } catch (QueryException $exception) {
                    // An exception whose page reads the database, as error pages do
                    throw new class('rendered', 0, $exception) extends RuntimeException
                    {
                        public function render(): Response
                        {
                            return response()->json(['users' => UserModel::query()->count()], 500);
                        }
                    };
                }

                throw new RuntimeException('the query should have failed');
            });
        });
    }

    /**
     * @return array<string, array{ImpersonationMode}>
     */
    public static function modes(): array
    {
        return [
            'read only' => [ImpersonationMode::ReadOnly],
            'simulate' => [ImpersonationMode::Simulate],
        ];
    }

    #[DataProvider('modes')]
    public function test_a_write_is_seen_during_the_request_and_rolled_back_after_it(ImpersonationMode $mode): void
    {
        $this->startImpersonating($mode);

        $this->getJson('/_test/net/rename')->assertOk()->assertJsonPath('name', 'Changed name');

        $this->assertSame('Original name', $this->member->fresh()?->name);
    }

    public function test_a_request_that_fails_is_rolled_back_too(): void
    {
        $this->startImpersonating();

        $this->getJson('/_test/net/rename-and-fail')->assertServerError();

        $this->assertSame('Original name', $this->member->fresh()?->name);
    }

    public function test_without_an_impersonation_writes_persist(): void
    {
        $this->actingAs($this->admin);

        $this->getJson('/_test/net/rename')->assertOk();

        $this->assertSame('Changed name', $this->member->fresh()?->name);
    }

    public function test_opening_a_notice_leaves_it_unread(): void
    {
        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );
        $this->member->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Aviso', url: '/foro/1')));
        $notice = SiteNotificationModel::query()->firstOrFail();
        $this->startImpersonating();

        $this->get('/avisos/'.$notice->id)->assertRedirect('/foro/1');

        $this->assertNull($notice->fresh()?->read_at);
    }

    public function test_a_role_change_and_its_audit_entry_are_rolled_back(): void
    {
        UserModel::factory()->editor()->create();
        $entriesBefore = RoleAuditEntryModel::query()->count();
        $rolesBefore = DB::table('user_role')->where('user_id', $this->member->id)->count();
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/net/assign-role')->assertOk()->assertJsonPath('entries', $entriesBefore + 1);

        $this->assertSame($entriesBefore, RoleAuditEntryModel::query()->count());
        $this->assertSame($rolesBefore, DB::table('user_role')->where('user_id', $this->member->id)->count());
    }

    public function test_callbacks_after_commit_never_run(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/net/after-commit')->assertOk()->assertJsonPath('ran', false);
    }

    public function test_stopping_and_signing_out_are_kept_in_the_log(): void
    {
        $this->startImpersonating();
        $this->getJson('/_test/net/rename')->assertOk();

        $this->post(route('impersonation.stop'))->assertRedirect();

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertSame(ImpersonationEndReason::Stopped, $entry->ended_reason);
        $this->assertNotNull($entry->ended_at);
    }

    public function test_no_mail_leaves_whatever_the_mailer(): void
    {
        // A mailer that cannot connect: sending through it would fail the request
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1],
        ]);
        $this->startImpersonating();

        $this->get('/_test/net/mail')->assertOk()->assertSee('sent');

        // Each mailer keeps what it would have sent, in memory
        foreach (['array', 'smtp'] as $mailer) {
            $transport = Mail::mailer($mailer)->getSymfonyTransport();
            $this->assertInstanceOf(ArrayTransport::class, $transport);
            $this->assertCount(1, $transport->messages());
        }
    }

    public function test_no_job_is_queued_whatever_the_connection(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->get('/_test/net/queue')->assertOk();

        $this->assertSame(0, RecordingJob::$handled);
    }

    public function test_the_search_index_is_updated_through_the_discarding_queue(): void
    {
        config(['scout.queue' => false]);
        $this->startImpersonating();

        $this->getJson('/_test/net/rename')->assertOk();

        $this->assertTrue(config('scout.queue'));
    }

    public function test_the_shared_cache_keeps_its_values(): void
    {
        $shared = $this->sharedCacheStore();
        $shared->put('net.key', 'before', 60);
        $this->startImpersonating();

        $this->get('/_test/net/cache')->assertOk()->assertSee('during');

        $this->assertSame('before', $shared->get('net.key'));
    }

    public function test_saving_settings_leaves_no_reverted_values_in_the_shared_cache(): void
    {
        $shared = $this->sharedCacheStore();
        $this->app->make(SettingsServiceInterface::class)->get('guild_name');
        $this->assertNotNull($shared->get('site_settings'));
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->get('/_test/net/settings')->assertOk()->assertSee('Reverted guild');

        $this->assertStringNotContainsString('Reverted guild', serialize($shared->get('site_settings')));
    }

    public function test_the_upload_disk_is_read_but_never_written_or_deleted(): void
    {
        $real = Storage::fake('images');
        $real->put('kept.txt', 'kept');
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/net/disk')->assertOk()->assertExactJson(['read' => 'kept', 'new_exists' => false]);

        $this->assertTrue($real->exists('kept.txt'));
        $this->assertFalse($real->exists('new.txt'));
    }

    public function test_deferred_work_stays_neutralised(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->get('/_test/net/deferred')->assertOk();

        $this->assertSame(0, RecordingJob::$handled);
        $this->assertSame('Original name', $this->member->fresh()?->name);
    }

    public function test_a_failed_query_does_not_break_the_rest_of_the_request(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/net/failed-query-then-continue')->assertOk()->assertJsonPath('users', 2);
    }

    public function test_a_failed_query_inside_an_inner_transaction_does_not_break_the_rest_of_the_request(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/net/failed-transaction-then-continue')->assertOk()->assertJsonPath('users', 2);
    }

    public function test_a_failed_query_does_not_chain_a_second_error_while_rendering(): void
    {
        $this->startImpersonating(ImpersonationMode::Simulate);

        $this->getJson('/_test/net/failed-query-then-render')->assertStatus(500)->assertJsonPath('users', 2);
    }

    private function startImpersonating(ImpersonationMode $mode = ImpersonationMode::ReadOnly): void
    {
        $this->actingAs($this->admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($this->admin, $this->member, $mode);
    }

    /** The store every request of the site shares, as it is before the impersonation */
    private function sharedCacheStore(): Store
    {
        return Cache::store()->getStore();
    }
}
