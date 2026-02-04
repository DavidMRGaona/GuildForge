<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

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

                \PHPUnit\Framework\Assert::assertIsArray($page);
                \PHPUnit\Framework\Assert::assertArrayHasKey('component', $page);
                \PHPUnit\Framework\Assert::assertArrayHasKey('props', $page);
                \PHPUnit\Framework\Assert::assertArrayHasKey('url', $page);
                \PHPUnit\Framework\Assert::assertArrayHasKey('version', $page);
                \PHPUnit\Framework\Assert::assertArrayHasKey('encryptHistory', $page);
                \PHPUnit\Framework\Assert::assertArrayHasKey('clearHistory', $page);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                \PHPUnit\Framework\Assert::fail('Not a valid Inertia response.');
            }

            $instance = AssertableInertia::fromArray($page['props']);

            // Use reflection to set private properties
            $reflection = new \ReflectionClass($instance);

            $componentProp = $reflection->getProperty('component');
            $componentProp->setAccessible(true);
            $componentProp->setValue($instance, $page['component']);

            $urlProp = $reflection->getProperty('url');
            $urlProp->setAccessible(true);
            $urlProp->setValue($instance, $page['url']);

            $versionProp = $reflection->getProperty('version');
            $versionProp->setAccessible(true);
            $versionProp->setValue($instance, $page['version']);

            $encryptHistoryProp = $reflection->getProperty('encryptHistory');
            $encryptHistoryProp->setAccessible(true);
            $encryptHistoryProp->setValue($instance, $page['encryptHistory']);

            $clearHistoryProp = $reflection->getProperty('clearHistory');
            $clearHistoryProp->setAccessible(true);
            $clearHistoryProp->setValue($instance, $page['clearHistory']);

            if (isset($page['deferredProps'])) {
                $deferredPropsProp = $reflection->getProperty('deferredProps');
                $deferredPropsProp->setAccessible(true);
                $deferredPropsProp->setValue($instance, $page['deferredProps']);
            }

            if (isset($page['flash'])) {
                $flashProp = $reflection->getProperty('flash');
                $flashProp->setAccessible(true);
                $flashProp->setValue($instance, $page['flash']);
            }

            if ($callback) {
                $callback($instance);
            }

            return $this;
        });
    }
}
