<?php

declare(strict_types=1);

namespace App\Filament\Impersonation;

use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Livewire\Component;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Which Livewire calls still run while an administrator impersonates in «Solo mirar»
 * (core#576). It fails closed: a call runs only when it is in a list of what browses or reads,
 * never because it is missing from a list of what writes, so module pages are covered
 * without doing anything.
 *
 * - Livewire's own calls and Filament's browsing (pages, sorting, filters, search, columns,
 *   deferred parts, getters of form fields and columns) run.
 * - Mounting an action runs when it opens a modal or a slide-over (forms, confirmations,
 *   «Ver detalles»); an action that would run on click is refused unless ReadOnlyActions marks it.
 * - Running a mounted action (submitting its modal) is refused unless ReadOnlyActions marks it.
 * - Any other public method (save, create, a module's own method) is refused unless it carries
 *   #[ReadsOnly].
 */
final readonly class PanelReadOnlyRule
{
    /** Calls that only browse: they never run an action nor save */
    public const array BROWSING_METHODS = [
        // Livewire
        '$refresh', '$set', '$sync', '$toggle', '$commit', '__dispatch', '__lazyLoad',
        // Pagination
        'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage',
        // Tables
        'loadTable', 'sortTable', 'applyTableFilters', 'removeTableFilter', 'removeTableFilters',
        'resetTableFiltersForm', 'resetTableSearch', 'resetTableColumnSearch', 'resetTableColumnSearches',
        'applyTableColumnManager', 'resetTableColumnManager', 'toggleTableReordering', 'deselectAllTableRecords',
        'getAllSelectableTableRecordKeys', 'getGroupedSelectableTableRecordKeys',
        'getAllSelectableTableRecordsCount', 'getAllTableRecordsCount',
        // Schemas and actions
        'loadDeferredSchema', 'partiallyRenderSchemaComponent', 'mergeMountedActionArguments',
        'unmountAction', 'unmountTableAction',
    ];

    /**
     * Calls that reach a method of a form field or a column: only its getters run (search
     * results, option labels), with the method name at this position of the call's arguments
     */
    private const array COMPONENT_METHOD_CALLS = [
        'callSchemaComponentMethod' => 1,
        'callTableColumnMethod' => 2,
    ];

    private const array MOUNTING_METHODS = ['mountAction', 'mountTableAction', 'mountTableBulkAction'];

    private const array RUNNING_METHODS = ['callMountedAction', 'callMountedTableAction', 'callMountedTableBulkAction'];

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function allows(Component $component, string $method, array $params): bool
    {
        if (in_array($method, self::BROWSING_METHODS, true)) {
            return true;
        }

        if (array_key_exists($method, self::COMPONENT_METHOD_CALLS)) {
            $componentMethod = array_values($params)[self::COMPONENT_METHOD_CALLS[$method]] ?? null;

            return is_string($componentMethod) && str_starts_with($componentMethod, 'get');
        }

        if ($component instanceof HasActions && in_array($method, self::MOUNTING_METHODS, true)) {
            $entry = $this->mountedEntry($method, array_values($params));

            return $entry !== null && $this->mountingOnlyOpens($component, $entry);
        }

        if ($component instanceof HasActions && in_array($method, self::RUNNING_METHODS, true)) {
            $action = $this->invoke($component, 'getMountedAction');

            return $action instanceof Action && ReadOnlyActions::allows($action);
        }

        return $this->isMarkedAsReading($component, $method);
    }

    /**
     * What Filament pushes onto the mounted actions for this call; null when the arguments are
     * not what the method takes.
     *
     * @param  list<mixed>  $params
     * @return array{name: string, arguments: array<mixed>, context: array<mixed>}|null
     */
    private function mountedEntry(string $method, array $params): ?array
    {
        $name = $params[0] ?? null;

        if (! is_string($name)) {
            return null;
        }

        return match ($method) {
            'mountAction' => is_array($params[1] ?? []) && is_array($params[2] ?? [])
                ? ['name' => $name, 'arguments' => $params[1] ?? [], 'context' => $params[2] ?? []]
                : null,
            'mountTableAction' => $this->tableEntry($name, $params[1] ?? null, $params[2] ?? []),
            default => ['name' => $name, 'arguments' => [], 'context' => ['table' => true, 'bulk' => true]],
        };
    }

    /**
     * @return array{name: string, arguments: array<mixed>, context: array<mixed>}|null
     */
    private function tableEntry(string $name, mixed $record, mixed $arguments): ?array
    {
        if (! is_array($arguments) || ($record !== null && ! is_string($record) && ! is_int($record))) {
            return null;
        }

        return [
            'name' => $name,
            'arguments' => $arguments,
            'context' => $record === null ? ['table' => true] : ['table' => true, 'recordKey' => (string) $record],
        ];
    }

    /**
     * Resolves the action as mountAction() would and takes it off again the way Filament takes
     * off the actions without a modal, so the page is left as it was whatever the answer.
     *
     * @param  array{name: string, arguments: array<mixed>, context: array<mixed>}  $entry
     */
    private function mountingOnlyOpens(HasActions&Component $component, array $entry): bool
    {
        $mounted = new ReflectionProperty($component, 'mountedActions');
        /** @var array<mixed> $before */
        $before = $mounted->getValue($component);
        $mounted->setValue($component, [...$before, $entry]);

        try {
            $action = $this->invoke($component, 'getMountedAction');

            return $action instanceof Action
                && (ReadOnlyActions::allows($action) || $this->invoke($component, 'mountedActionShouldOpenModal', ['mountedAction' => $action]) === true);
        } catch (ActionNotResolvableException) {
            return false;
        } finally {
            $mounted->setValue($component, $before);
            $this->invoke($component, 'truncateCachedMountedActions');
        }
    }

    private function isMarkedAsReading(Component $component, string $method): bool
    {
        if (! method_exists($component, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($component, $method);

        return $reflection->isPublic() && $reflection->getAttributes(ReadsOnly::class) !== [];
    }

    /**
     * Filament's action methods belong to its InteractsWithActions trait, not to the HasActions
     * contract, and some are protected.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function invoke(Component $component, string $method, array $arguments = []): mixed
    {
        return (new ReflectionMethod($component, $method))->invokeArgs($component, $arguments);
    }
}
