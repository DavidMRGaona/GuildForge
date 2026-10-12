<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Override Inertia's assertInertia macro to preserve float types in JSON round-trip
        TestResponse::macro('assertInertia', function (?\Closure $callback = null) {
            try {
                /** @var TestResponse $this */
                $this->assertViewHas('page');
                $pageData = $this->viewData('page');

                // Use JSON_PRESERVE_ZERO_FRACTION to maintain float types
                $page = json_decode(
                    json_encode($pageData, JSON_PRESERVE_ZERO_FRACTION),
                    true
                );

                Assert::assertIsArray($page);
                Assert::assertArrayHasKey('component', $page);
                Assert::assertArrayHasKey('props', $page);
                Assert::assertArrayHasKey('url', $page);
                Assert::assertArrayHasKey('version', $page);
                Assert::assertArrayHasKey('encryptHistory', $page);
                Assert::assertArrayHasKey('clearHistory', $page);
            } catch (AssertionFailedError $e) {
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
            $encryptHistoryProp->setValue($instance, $page['encryptHistory']);

            $clearHistoryProp = $reflection->getProperty('clearHistory');
            $clearHistoryProp->setValue($instance, $page['clearHistory']);

            if (isset($page['deferredProps'])) {
                $deferredPropsProp = $reflection->getProperty('deferredProps');
                $deferredPropsProp->setValue($instance, $page['deferredProps']);
            }

            if (isset($page['flash'])) {
                $flashProp = $reflection->getProperty('flash');
                $flashProp->setValue($instance, $page['flash']);
            }

            if ($callback) {
                $callback($instance);
            }

            return $this;
        });
    }
}
