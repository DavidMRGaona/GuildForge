<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHeroSlide extends EditRecord
{
    /**
     * The whole save runs in one transaction: images a record lets go of are released after the commit, once
     * every record of the save is written (a later one may have chosen the same file from the library).
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = HeroSlideResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
