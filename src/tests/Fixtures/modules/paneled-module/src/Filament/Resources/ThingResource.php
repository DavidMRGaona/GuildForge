<?php

declare(strict_types=1);

namespace Modules\PaneledModule\Filament\Resources;

use App\Filament\Resources\BaseResource;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Modules\PaneledModule\Filament\Resources\ThingResource\Pages\ListThings;

final class ThingResource extends BaseResource
{
    protected static ?string $model = TagModel::class;

    protected static ?string $slug = 'things';

    public static function getPages(): array
    {
        return [
            'index' => ListThings::route('/'),
        ];
    }
}
