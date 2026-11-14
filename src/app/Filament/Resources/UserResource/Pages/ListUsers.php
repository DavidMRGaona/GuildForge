<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Actions\ViewAsRolesAction;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = UserResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ViewAsRolesAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.manage-users')]),
        ];
    }
}
