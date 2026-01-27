<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\ScaffoldResultDTO;

interface ModuleScaffoldingServiceInterface
{
    /**
     * Create a new module with the complete directory structure.
     */
    public function createModule(string $name, ?string $description = null, ?string $author = null): ScaffoldResultDTO;

    /**
     * Create a new domain entity for a module.
     */
    public function createEntity(string $module, string $name, bool $withMigration = true): ScaffoldResultDTO;

    /**
     * Create a new controller for a module.
     *
     * @param  string  $type  Controller type: 'default', 'resource', 'api', 'invokable'
     */
    public function createController(string $module, string $name, string $type = 'default'): ScaffoldResultDTO;

    /**
     * Create a new form request for a module.
     */
    public function createRequest(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new service for a module.
     */
    public function createService(string $module, string $name, bool $withInterface = true): ScaffoldResultDTO;

    /**
     * Create a new DTO for a module.
     */
    public function createDto(string $module, string $name, bool $isResponse = false): ScaffoldResultDTO;

    /**
     * Create a new Filament resource for a module.
     */
    public function createFilamentResource(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new policy for a module.
     */
    public function createPolicy(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new migration for a module.
     */
    public function createMigration(string $module, string $name, ?string $table = null): ScaffoldResultDTO;

    /**
     * Create a new test for a module.
     *
     * @param  string  $type  Test type: 'unit', 'feature'
     */
    public function createTest(string $module, string $name, string $type = 'unit'): ScaffoldResultDTO;

    /**
     * Create a new Vue page for a module.
     */
    public function createVuePage(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new Vue component for a module.
     */
    public function createVueComponent(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new enum for a module.
     */
    public function createEnum(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new domain event for a module.
     */
    public function createDomainEvent(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new event listener for a module.
     */
    public function createListener(string $module, string $name, string $eventName, bool $queued = false): ScaffoldResultDTO;

    /**
     * Create a new notification for a module.
     */
    public function createNotification(string $module, string $name): ScaffoldResultDTO;

    /**
     * Create a new Filament RelationManager for a module.
     */
    public function createRelationManager(string $module, string $name, ?string $resource = null): ScaffoldResultDTO;

    /**
     * Create a new Filament Widget for a module.
     */
    public function createWidget(string $module, string $name): ScaffoldResultDTO;
}
