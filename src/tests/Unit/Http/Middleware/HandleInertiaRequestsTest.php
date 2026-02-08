<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

final class HandleInertiaRequestsTest extends TestCase
{
    private HandleInertiaRequests $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new HandleInertiaRequests();
    }

    public function test_share_returns_expected_keys(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session']->driver());

        $shared = $this->middleware->share($request);

        $this->assertArrayHasKey('appName', $shared);
        $this->assertArrayHasKey('appDescription', $shared);
        $this->assertArrayHasKey('siteLogoLight', $shared);
        $this->assertArrayHasKey('siteLogoDark', $shared);
        $this->assertArrayHasKey('favicons', $shared);
        $this->assertArrayHasKey('theme', $shared);
        $this->assertArrayHasKey('auth', $shared);
        $this->assertArrayHasKey('authSettings', $shared);
        $this->assertArrayHasKey('flash', $shared);
        $this->assertArrayHasKey('moduleSlots', $shared);
        $this->assertArrayHasKey('modulePages', $shared);
        $this->assertArrayHasKey('moduleTranslations', $shared);
        $this->assertArrayHasKey('navigation', $shared);
    }

    public function test_module_translations_returns_empty_array_when_no_modules_loaded(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($this->app['session']->driver());

        $shared = $this->middleware->share($request);
        $translations = $shared['moduleTranslations']();

        $this->assertIsArray($translations);
    }

    public function test_parse_typescript_locale_file_extracts_simple_object(): void
    {
        // Create a temporary TypeScript locale file
        $content = <<<'TS'
export default {
    hello: 'Hola',
    world: 'Mundo',
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Hola', $result['hello']);
            $this->assertSame('Mundo', $result['world']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_extracts_nested_object(): void
    {
        $content = <<<'TS'
export default {
    messages: {
        greeting: 'Hola',
        farewell: 'Adiós',
    },
    buttons: {
        submit: 'Enviar',
        cancel: 'Cancelar',
    },
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertIsArray($result['messages']);
            $this->assertSame('Hola', $result['messages']['greeting']);
            $this->assertSame('Adiós', $result['messages']['farewell']);
            $this->assertIsArray($result['buttons']);
            $this->assertSame('Enviar', $result['buttons']['submit']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_handles_single_quotes(): void
    {
        $content = <<<'TS'
export default {
    title: 'Título con "comillas"',
    description: 'Una descripción',
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Título con "comillas"', $result['title']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_handles_interpolation_strings(): void
    {
        $content = <<<'TS'
export default {
    welcome: 'Bienvenido {name}',
    count: '{count} elementos',
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Bienvenido {name}', $result['welcome']);
            $this->assertSame('{count} elementos', $result['count']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_returns_null_for_invalid_format(): void
    {
        $content = <<<'TS'
// No export default
const messages = {
    hello: 'world',
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertNull($result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_returns_null_for_nonexistent_file(): void
    {
        $reflection = new ReflectionClass(HandleInertiaRequests::class);
        $method = $reflection->getMethod('parseTypeScriptLocaleFile');
        $method->setAccessible(true);

        $result = $method->invoke($this->middleware, '/nonexistent/file.ts');

        $this->assertNull($result);
    }

    public function test_parse_typescript_locale_file_handles_colons_in_string_values(): void
    {
        $content = <<<'TS'
export default {
    generalOpeningAt: 'Apertura general: {date}',
    publicOpeningAt: 'Apertura de inscripciones: {date}',
    simple: 'Sin dos puntos',
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Apertura general: {date}', $result['generalOpeningAt']);
            $this->assertSame('Apertura de inscripciones: {date}', $result['publicOpeningAt']);
            $this->assertSame('Sin dos puntos', $result['simple']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_handles_multiple_colons_in_nested_structures(): void
    {
        $content = <<<'TS'
export default {
    eventActions: {
        generalOpeningAt: 'Apertura general: {date}',
        earlyAccessActiveFrom: 'Early access activo desde {date}',
    },
    registration: {
        opensAt: 'Las inscripciones abren el {date}',
        closesAt: 'Cierre: {date}',
    },
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Apertura general: {date}', $result['eventActions']['generalOpeningAt']);
            $this->assertSame('Early access activo desde {date}', $result['eventActions']['earlyAccessActiveFrom']);
            $this->assertSame('Las inscripciones abren el {date}', $result['registration']['opensAt']);
            $this->assertSame('Cierre: {date}', $result['registration']['closesAt']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_handles_urls_and_time_formats(): void
    {
        $content = <<<'TS'
export default {
    helpUrl: 'Visita https://example.com/help para más info',
    time: 'La sesión empieza a las 14:30',
    multiColon: 'Formato: HH:MM:SS',
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Visita https://example.com/help para más info', $result['helpUrl']);
            $this->assertSame('La sesión empieza a las 14:30', $result['time']);
            $this->assertSame('Formato: HH:MM:SS', $result['multiColon']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_handles_real_world_game_tables_structure(): void
    {
        $content = <<<'TS'
export default {
    gameTables: {
        title: 'Mesas de rol',
        eventActions: {
            createTable: 'Crear partida',
            generalOpeningAt: 'Apertura general: {date}',
            publicOpeningAt: 'Apertura de inscripciones: {date}',
        },
        profile: {
            tabLabel: 'Mesas',
            upcoming: 'Próximas partidas',
        },
    },
    campaigns: {
        profile: {
            tabLabel: 'Campañas',
        },
    },
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Mesas de rol', $result['gameTables']['title']);
            $this->assertSame('Apertura general: {date}', $result['gameTables']['eventActions']['generalOpeningAt']);
            $this->assertSame('Apertura de inscripciones: {date}', $result['gameTables']['eventActions']['publicOpeningAt']);
            $this->assertSame('Mesas', $result['gameTables']['profile']['tabLabel']);
            $this->assertSame('Campañas', $result['campaigns']['profile']['tabLabel']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_parse_typescript_locale_file_handles_comments(): void
    {
        $content = <<<'TS'
export default {
    // This is a comment
    title: 'Mesas de rol',
    subtitle: 'Partidas disponibles', // inline comment
};
TS;

        $tempFile = tempnam(sys_get_temp_dir(), 'locale_');
        file_put_contents($tempFile, $content);

        try {
            $reflection = new ReflectionClass(HandleInertiaRequests::class);
            $method = $reflection->getMethod('parseTypeScriptLocaleFile');
            $method->setAccessible(true);

            $result = $method->invoke($this->middleware, $tempFile);

            $this->assertIsArray($result);
            $this->assertSame('Mesas de rol', $result['title']);
            $this->assertSame('Partidas disponibles', $result['subtitle']);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_admin_routes_bypass_middleware(): void
    {
        $adminPaths = ['admin', 'admin/dashboard', 'admin/users', 'livewire/component'];

        foreach ($adminPaths as $path) {
            $request = Request::create('/' . $path, 'GET');

            $response = $this->middleware->handle($request, function ($req) {
                return response('Bypassed', 200);
            });

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('Bypassed', $response->getContent());
        }
    }
}
