<?php

declare(strict_types=1);

namespace Modules\RoutedModule\Http;

final class PingController
{
    public function show(): string
    {
        return 'pong';
    }
}
