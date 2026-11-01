<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Url;

final class KnowledgeBasePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $slug = 'ayuda';

    protected string $view = 'filament.pages.knowledge-base';

    #[Url(as: 'guia')]
    public ?string $guide = null;

    public static function getNavigationLabel(): string
    {
        return __('knowledge.contextual_help');
    }

    public function getTitle(): string
    {
        return __('knowledge.contextual_help');
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
