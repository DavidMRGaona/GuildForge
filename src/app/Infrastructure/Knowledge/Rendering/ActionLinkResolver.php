<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\Services\GuideConditionEvaluatorInterface;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Turns an action button into a URL, or null when it must not be shown.
 */
final readonly class ActionLinkResolver
{
    public function __construct(
        private GuideConditionEvaluatorInterface $evaluator,
        private PermissionRegistryInterface $permissions,
    ) {}

    /**
     * @param  string  $target  A route name, optionally followed by `?key=value&…`
     */
    public function resolve(string $target, ?string $condition, ?string $module, object $user): ?string
    {
        try {
            ['route' => $routeName, 'query' => $query] = ActionTarget::split($target);
            $parameters = ActionTarget::parameters($query);
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null || $parameters === null || self::hasRequiredParameters($route)) {
                return null;
            }

            if (! $this->evaluator->passes($user, new GuideConditions, $module)) {
                return null;
            }

            if ($condition !== null && ! $this->conditionPasses($condition, $module, $user)) {
                return null;
            }

            // Always as a query: route() would put a key named like an optional route parameter
            // (`{page?}`) in the path instead
            $url = route($routeName, [], false);

            return $parameters === [] ? $url : $url.'?'.Arr::query($parameters);
        } catch (Throwable $e) {
            // Hidden, not broken: the guide still renders without the button, and the cause is not lost
            report($e);

            return null;
        }
    }

    public static function hasRequiredParameters(RoutingRoute $route): bool
    {
        return preg_match('/\{[^}?]+\}/', $route->uri()) === 1;
    }

    private function conditionPasses(string $condition, ?string $module, object $user): bool
    {
        ['negated' => $negated, 'key' => $key] = ActionCondition::parse($condition);
        if ($key === '') {
            return false;
        }

        if (ActionCondition::kind($key, $this->permissions) === 'permission') {
            $holds = $this->evaluator->passes($user, new GuideConditions(permissionsAll: [$key]), null);

            return $negated ? ! $holds : $holds;
        }

        $conditions = $negated
            ? new GuideConditions(negatedCapabilities: [$key])
            : new GuideConditions(capabilities: [$key]);

        return $this->evaluator->passes($user, $conditions, $module);
    }
}
