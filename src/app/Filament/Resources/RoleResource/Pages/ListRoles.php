<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Actions\ViewAsRolesAction;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\RoleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = RoleResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ViewAsRolesAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.roles-and-permissions')]),
        ];
    }
}
