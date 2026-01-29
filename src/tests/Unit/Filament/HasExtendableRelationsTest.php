<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Concerns\HasExtendableRelations;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HasExtendableRelationsTest extends TestCase
{
    #[Test]
    public function it_returns_base_relations_when_no_extended_relations(): void
    {
        TestResource::clearExtendedRelations();

        $relations = TestResource::getRelations();

        $this->assertEquals([TestBaseRelationManager::class], $relations);
    }

    #[Test]
    public function it_filters_out_relation_managers_for_nonexistent_relationships(): void
    {
        TestResource::clearExtendedRelations();

        // This relation manager references a relationship that doesn't exist
        TestResource::extendRelations([
            NonExistentRelationManager::class,
        ]);

        $relations = TestResource::getRelations();

        // Should only contain base relations, nonexistent one filtered out
        $this->assertEquals([TestBaseRelationManager::class], $relations);
    }

    #[Test]
    public function it_includes_relation_managers_for_existing_method_relationships(): void
    {
        TestResource::clearExtendedRelations();

        // This relation manager references an actual method on the model
        TestResource::extendRelations([
            ExistingMethodRelationManager::class,
        ]);

        $relations = TestResource::getRelations();

        $this->assertContains(TestBaseRelationManager::class, $relations);
        $this->assertContains(ExistingMethodRelationManager::class, $relations);
    }

    #[Test]
    public function it_filters_out_nonexistent_relation_manager_classes(): void
    {
        TestResource::clearExtendedRelations();

        // Add a class that doesn't exist
        TestResource::extendRelations([
            'NonExistent\\RelationManager\\Class',
        ]);

        $relations = TestResource::getRelations();

        // Should only contain base relations
        $this->assertEquals([TestBaseRelationManager::class], $relations);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TestResource::clearExtendedRelations();
    }
}

// Test fixtures

class TestModel extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $table = 'test_models';

    /**
     * @return HasMany<TestModel, $this>
     */
    public function existingRelation(): HasMany
    {
        return $this->hasMany(TestModel::class);
    }

    /**
     * @return HasMany<TestModel, $this>
     */
    public function baseRelation(): HasMany
    {
        return $this->hasMany(TestModel::class);
    }
}

class TestResource extends Resource
{
    use HasExtendableRelations;

    protected static ?string $model = TestModel::class;

    /**
     * @return array<class-string>
     */
    protected static function getBaseRelations(): array
    {
        return [TestBaseRelationManager::class];
    }
}

class TestBaseRelationManager extends RelationManager
{
    protected static string $relationship = 'baseRelation';
}

class NonExistentRelationManager extends RelationManager
{
    protected static string $relationship = 'nonExistentRelation';
}

class ExistingMethodRelationManager extends RelationManager
{
    protected static string $relationship = 'existingRelation';
}

class MacroRelationManager extends RelationManager
{
    protected static string $relationship = 'macroRelation';
}
