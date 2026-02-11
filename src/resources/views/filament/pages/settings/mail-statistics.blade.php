<x-filament-panels::page>
    @livewire(\App\Filament\Widgets\MailStatsOverviewWidget::class)

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
