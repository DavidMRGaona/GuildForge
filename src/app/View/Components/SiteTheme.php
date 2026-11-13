<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The site palette and fonts in the head of every page. DefaultLayout and AuthLayout add them
 * too, but only once Vue has mounted them, and module pages without the core layout (the link
 * page of a printed QR code) get no layout at all. The font files themselves come with app.css
 * (resources/css/fonts.css, core#557); the variables here only say which family each role uses.
 */
final class SiteTheme extends Component
{
    /**
     * @param  array<string, mixed>|null  $theme  the shared Inertia prop "theme"
     */
    public function __construct(
        private readonly ?array $theme = null,
    ) {}

    public function render(): View
    {
        $css = $this->theme['cssVariables'] ?? '';

        return view('components.site-theme', [
            // Nothing inside the style element may end it: "</" becomes the CSS escape "<\/"
            'css' => is_string($css) ? str_replace('</', '<\/', $css) : '',
        ]);
    }
}
