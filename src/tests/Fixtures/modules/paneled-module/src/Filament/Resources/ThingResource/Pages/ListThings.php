<?php

declare(strict_types=1);

namespace Modules\PaneledModule\Filament\Resources\ThingResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\PaneledModule\Filament\Resources\ThingResource;

final class ListThings extends ListRecords
{
    protected static string $resource = ThingResource::class;
}
