<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The users list shows who has two-factor authentication on and filters by it (core#771). The column
 * hides below `md` like the other secondary ones, and «Ver detalles» still shows it.
 */
final class TwoFactorColumnAndFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    private UserModel $with;

    private UserModel $without;

    private UserModel $pending;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->with = UserModel::factory()->create();
        $this->enable($this->with);
        $this->without = UserModel::factory()->create();
        $this->pending = UserModel::factory()->create();
        $this->app->make(TwoFactorServiceInterface::class)->beginSetup((string) $this->pending->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_column_is_a_centred_icon_shown_from_md(): void
    {
        $column = $this->table()->getColumn('two_factor');

        $this->assertInstanceOf(IconColumn::class, $column);
        $this->assertSame('md', $column->getVisibleFrom());
        $this->assertSame(Alignment::Center, $column->getAlignment());
        $this->assertSame('Doble factor', $column->getLabel());
    }

    public function test_the_column_says_who_has_it_on(): void
    {
        Livewire::test(ListUsers::class)
            ->assertTableColumnStateSet('two_factor', true, $this->with)
            ->assertTableColumnStateSet('two_factor', false, $this->without)
            // A setup not confirmed yet is not two-factor authentication
            ->assertTableColumnStateSet('two_factor', false, $this->pending);
    }

    public function test_the_column_asks_once_for_the_whole_page(): void
    {
        UserModel::factory()->count(5)->create();
        $queries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'user_two_factor')) {
                $queries++;
            }
        });

        Livewire::test(ListUsers::class);

        $this->assertSame(1, $queries);
    }

    public function test_the_filter_has_three_states(): void
    {
        Livewire::test(ListUsers::class)
            ->filterTable('two_factor', true)
            ->assertCanSeeTableRecords([$this->with])
            ->assertCanNotSeeTableRecords([$this->without, $this->pending]);

        Livewire::test(ListUsers::class)
            ->filterTable('two_factor', false)
            ->assertCanSeeTableRecords([$this->without, $this->pending])
            ->assertCanNotSeeTableRecords([$this->with]);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$this->with, $this->without, $this->pending]);

        $filter = $this->table()->getFilter('two_factor');
        $this->assertNotNull($filter);
        $this->assertSame('Doble factor', $filter->getLabel());
    }

    public function test_details_show_the_column_too(): void
    {
        $entries = RowDetailsAction::entries($this->table(), $this->with);

        $this->assertContains('Doble factor', array_column($entries, 'label'));
    }

    private function table(): Table
    {
        $component = Livewire::test(ListUsers::class)->instance();
        $this->assertInstanceOf(HasTable::class, $component);

        return $component->getTable();
    }

    private function enable(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $setup = $service->beginSetup((string) $user->id);
        $service->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }
}
