<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Modules\ModuleServiceProvider;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * A module provider that declares a settings page (any string, as a module could) and still returns
 * a settings schema, like a module that has not dropped it yet. Register it with
 * RegistersBootedModuleProviders::registerBootedProvider().
 */
final class SettingsPageModuleProvider extends ModuleServiceProvider
{
    /**
     * @param  list<class-string<Page>>  $pages  what registerFilamentPages() returns
     */
    public function __construct(
        Application $app,
        private readonly string $name,
        private readonly ?string $settingsPage,
        private readonly array $pages = [],
        private readonly bool $throws = false,
    ) {
        parent::__construct($app);
    }

    /**
     * Declares the page and lists it among its own pages.
     *
     * @param  class-string<Page>  $page
     */
    public static function owning(Application $app, string $name, string $page): self
    {
        return new self($app, $name, $page, [$page]);
    }

    public static function throwing(Application $app, string $name): self
    {
        return new self($app, $name, null, [], true);
    }

    public function moduleName(): string
    {
        return $this->name;
    }

    public function getSettingsPage(): ?string
    {
        if ($this->throws) {
            throw new RuntimeException('The provider failed.');
        }

        // Deliberately any string: the core must cope with whatever a module declares
        return $this->settingsPage; // @phpstan-ignore return.type
    }

    public function registerFilamentPages(): array
    {
        return $this->pages;
    }

    public function getSettingsSchema(): array
    {
        return [TextInput::make('greeting')];
    }
}
