<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

final class TimePicker extends Field
{
    protected string $view = 'filament.forms.components.time-picker';

    protected bool $hasSeconds = false;

    public function seconds(bool $condition = true): static
    {
        $this->hasSeconds = $condition;

        return $this;
    }

    public function hasSeconds(): bool
    {
        return $this->hasSeconds;
    }
}
