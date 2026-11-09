<x-filament-panels::page>
    @livewire(\App\Filament\Widgets\MailStatsOverviewWidget::class)

    @if (\App\Filament\Widgets\MailOptOutWidget::canView())
        @livewire(\App\Filament\Widgets\MailOptOutWidget::class)
    @endif

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
