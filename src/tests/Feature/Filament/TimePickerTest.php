<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Filament\TimePickerFixturePage;
use Tests\TestCase;

/**
 * The picker's input is free text: whatever reaches the form must be a time, or a form
 * error the admin can read, never a value that fails later in the caller ("10::00").
 */
final class TimePickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_times_with_and_without_seconds_are_saved(): void
    {
        Livewire::test(TimePickerFixturePage::class)
            ->fillForm(['starts_at' => '10:00', 'ends_at' => '14:30:00'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['starts_at' => '10:00', 'ends_at' => '14:30', 'closes_at' => null]);
    }

    public function test_a_picker_that_asks_for_seconds_hands_them_over(): void
    {
        Livewire::test(TimePickerFixturePage::class)
            ->fillForm(['starts_at' => '10:00', 'closes_at' => '22:15:30'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['starts_at' => '10:00', 'ends_at' => null, 'closes_at' => '22:15:30']);
    }

    public function test_a_mistyped_time_is_a_form_error(): void
    {
        Livewire::test(TimePickerFixturePage::class)
            ->fillForm(['starts_at' => '10::00'])
            ->call('save')
            ->assertHasFormErrors(['starts_at'])
            ->assertSet('saved', null)
            ->assertSee(__('filament.time_picker.invalid'));
    }
}
