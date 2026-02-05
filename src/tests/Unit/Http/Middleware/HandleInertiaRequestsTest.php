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

    public function test_convert_single_to_double_quotes(): void
    {
        $reflection = new ReflectionClass(HandleInertiaRequests::class);
        $method = $reflection->getMethod('convertSingleToDoubleQuotes');
        $method->setAccessible(true);

        // Simple case
        $result = $method->invoke($this->middleware, "'hello'");
        $this->assertSame('"hello"', $result);

        // Mixed quotes - single inside double should stay
        $result = $method->invoke($this->middleware, '"hello \'world\'"');
        $this->assertSame('"hello \'world\'"', $result);

        // Object with single quoted values
        $input = "{'key': 'value', 'nested': {'inner': 'test'}}";
        $result = $method->invoke($this->middleware, $input);
        $this->assertSame('{"key": "value", "nested": {"inner": "test"}}', $result);
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
