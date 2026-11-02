<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Knowledge\Services\GuideConditionEvaluatorInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Knowledge\DeniedKnowledgeCapability;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\Support\Knowledge\ThrowingKnowledgeCapability;
use Tests\Support\Knowledge\UserDependentKnowledgeCapability;
use Tests\TestCase;

final class GuideConditionEvaluatorTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private GuideConditionEvaluatorInterface $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
        $registry->register(FakeKnowledgeCapability::class);
        $registry->register(DeniedKnowledgeCapability::class);
        $registry->register(ThrowingKnowledgeCapability::class);
        $registry->register(UserDependentKnowledgeCapability::class);

        $this->evaluator = $this->app->make(GuideConditionEvaluatorInterface::class);
    }

    private function enableModules(string ...$names): void
    {
        $resolver = Mockery::mock(EnabledModulesResolverInterface::class);
        $resolver->shouldReceive('names')->andReturn(array_values($names));
        $this->app->instance(EnabledModulesResolverInterface::class, $resolver);
        $this->app->forgetInstance(GuideConditionEvaluatorInterface::class);
        $this->evaluator = $this->app->make(GuideConditionEvaluatorInterface::class);
    }

    public function test_empty_conditions_pass(): void
    {
        $this->assertTrue($this->evaluator->passes(UserModel::factory()->create(), new GuideConditions, null));
    }

    public function test_permissions_all_requires_every_permission(): void
    {
        $user = $this->editorWithPermissions(['events.view_any', 'events.create']);

        $this->assertTrue($this->evaluator->passes($user, new GuideConditions(permissionsAll: ['events.view_any', 'events.create']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(permissionsAll: ['events.view_any', 'events.delete']), null));
    }

    public function test_permissions_any_requires_one_permission(): void
    {
        $user = $this->editorWithPermissions(['events.view_any']);

        $this->assertTrue($this->evaluator->passes($user, new GuideConditions(permissionsAny: ['events.delete', 'events.view_any']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(permissionsAny: ['events.delete', 'articles.delete']), null));
    }

    public function test_association_member_true_and_false(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $member = UserModel::factory()->create();
        $member->roles()->attach(RoleModel::query()->where('name', 'socio')->value('id'));
        $outsider = UserModel::factory()->create();

        $needsMember = new GuideConditions(associationMember: true);
        $needsNonMember = new GuideConditions(associationMember: false);

        $this->assertTrue($this->evaluator->passes($member, $needsMember, null));
        $this->assertFalse($this->evaluator->passes($outsider, $needsMember, null));
        $this->assertTrue($this->evaluator->passes($outsider, $needsNonMember, null));
        $this->assertFalse($this->evaluator->passes($member, $needsNonMember, null));
    }

    public function test_negated_capability_passes_only_when_capability_is_false(): void
    {
        $user = UserModel::factory()->create();

        $this->assertTrue($this->evaluator->passes($user, new GuideConditions(capabilities: ['fake.can_do_it']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(capabilities: ['fake.denied']), null));
        $this->assertTrue($this->evaluator->passes($user, new GuideConditions(negatedCapabilities: ['fake.denied']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(negatedCapabilities: ['fake.can_do_it']), null));
    }

    public function test_capability_results_are_memoized_per_user(): void
    {
        $allowed = UserModel::factory()->create(['email' => 'ana'.UserDependentKnowledgeCapability::ALLOWED_DOMAIN]);
        $other = UserModel::factory()->create(['email' => 'bruno@example.test']);
        $conditions = new GuideConditions(capabilities: ['fake.per_user']);
        $negated = new GuideConditions(negatedCapabilities: ['fake.per_user']);

        $this->assertTrue($this->evaluator->passes($allowed, $conditions, null));
        $this->assertFalse($this->evaluator->passes($other, $conditions, null));
        $this->assertTrue($this->evaluator->passes($other, $negated, null));
        $this->assertFalse($this->evaluator->passes($allowed, $negated, null));
        $this->assertTrue($this->evaluator->passes($allowed, $conditions, null));
    }

    public function test_unknown_capability_fails_closed(): void
    {
        $user = UserModel::factory()->create();

        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(capabilities: ['nope.unknown']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(negatedCapabilities: ['nope.unknown']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(capabilities: ['fake.throws']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(negatedCapabilities: ['fake.throws']), null));
    }

    public function test_guides_of_disabled_modules_fail(): void
    {
        $this->enableModules('tournaments');
        $user = UserModel::factory()->create();

        $this->assertTrue($this->evaluator->passes($user, new GuideConditions, 'tournaments'));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions, 'venue-bookings'));
        $this->assertTrue($this->evaluator->passes($user, new GuideConditions(modules: ['tournaments']), null));
        $this->assertFalse($this->evaluator->passes($user, new GuideConditions(modules: ['tournaments', 'venue-bookings']), null));
    }

    public function test_inactive_role_permissions_do_not_count(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $role = RoleModel::query()->create(['name' => 'treasury', 'display_name' => 'Treasury']);
        $role->requires_association_member = true;
        $role->save();
        $permission = PermissionModel::query()->create(['key' => 'fees.view_any', 'label' => 'fees', 'resource' => 'fees', 'action' => 'view_any']);
        $role->permissions()->attach($permission->id);

        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $conditions = new GuideConditions(permissionsAny: ['fees.view_any']);

        $this->assertFalse($this->evaluator->passes($user, $conditions, null));

        $user->roles()->attach(RoleModel::query()->where('name', 'socio')->value('id'));
        $this->app->make(AuthorizationServiceInterface::class)->invalidateCacheFor($user);

        $this->assertTrue($this->evaluator->passes($user, $conditions, null));
    }
}
