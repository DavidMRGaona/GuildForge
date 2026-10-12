<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Application\Navigation\Services\RouteRegistryInterface;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Tests\TestCase;

final class RouteRegistryTest extends TestCase
{
    public function test_every_page_offered_for_a_menu_item_has_a_url(): void
    {
        foreach (array_keys(app(RouteRegistryInterface::class)->getAvailableRoutes()) as $option) {
            // "legal.show:aviso-legal" carries its slug, as MenuItemResource stores it
            [$name, $slug] = array_pad(explode(':', $option, 2), 2, null);

            try {
                $url = route($name, $slug === null ? [] : ['slug' => $slug]);
            } catch (UrlGenerationException) {
                $this->fail("The menu offers {$option}, whose route needs parameters the option does not carry");
            }

            $this->assertNotSame('', $url);
        }
    }
}
