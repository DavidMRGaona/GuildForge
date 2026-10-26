<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Authorization\Services\PermissionSyncRunnerInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use LogicException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Authorization\FakePermissionSyncRunner;

abstract class TestCase extends BaseTestCase
{
    /**
     * artisan() for a test that inspects the command (output, questions, exit code). artisan()
     * itself returns the exit code instead when console output mocking is off, as the framework
     * does internally (RefreshDatabase), so it cannot be narrowed.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            throw new LogicException('artisanCommand() needs console output mocking, which this test turned off.');
        }

        return $pending;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->app->instance(PermissionSyncRunnerInterface::class, new FakePermissionSyncRunner);

        // Override Inertia's assertInertia macro to preserve float types in JSON round-trip.
        // Inertia 3 omits encryptHistory and clearHistory when they are false.
        TestResponse::macro('assertInertia', function (?\Closure $callback = null) {
            try {
                /** @var TestResponse<Response> $this */
                $this->assertViewHas('page');
                $pageData = $this->viewData('page');

                // Use JSON_PRESERVE_ZERO_FRACTION to maintain float types
                $page = json_decode(
                    json_encode($pageData, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
                    true
                );

                Assert::assertIsArray($page);
                Assert::assertArrayHasKey('component', $page);
                Assert::assertArrayHasKey('props', $page);
                Assert::assertArrayHasKey('url', $page);
                Assert::assertArrayHasKey('version', $page);
            } catch (AssertionFailedError) {
                Assert::fail('Not a valid Inertia response.');
            }

            $instance = AssertableInertia::fromArray($page['props']);

            // Use reflection to set private properties
            $reflection = new \ReflectionClass($instance);

            $componentProp = $reflection->getProperty('component');
            $componentProp->setValue($instance, $page['component']);

            $urlProp = $reflection->getProperty('url');
            $urlProp->setValue($instance, $page['url']);

            $versionProp = $reflection->getProperty('version');
            $versionProp->setValue($instance, $page['version']);

            $encryptHistoryProp = $reflection->getProperty('encryptHistory');
            $encryptHistoryProp->setValue($instance, $page['encryptHistory'] ?? false);

            $clearHistoryProp = $reflection->getProperty('clearHistory');
            $clearHistoryProp->setValue($instance, $page['clearHistory'] ?? false);

            if (isset($page['deferredProps'])) {
                $deferredPropsProp = $reflection->getProperty('deferredProps');
                $deferredPropsProp->setValue($instance, $page['deferredProps']);
            }

            if (isset($page['flash'])) {
                $flashProp = $reflection->getProperty('flash');
                $flashProp->setValue($instance, $page['flash']);
            }

            if ($callback instanceof \Closure) {
                $callback($instance);
            }

            return $this;
        });
    }
}
