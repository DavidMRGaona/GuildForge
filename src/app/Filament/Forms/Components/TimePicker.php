<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

final class TimePicker extends Field
{
    protected string $view = 'filament.forms.components.time-picker';

    protected bool $hasSeconds = false;

    protected function setUp(): void
    {
        parent::setUp();

        // The input is free text: a mistyped value ("10::00") must stop here as a form error
        // instead of reaching the caller. The wheel has no seconds, stored values may have them
        $this->rule('date_format:H:i,H:i:s');
        $this->validationMessages(['date_format' => __('filament.time_picker.invalid')]);
    }

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
