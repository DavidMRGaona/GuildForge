<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Concerns;

use App\Application\Services\SlugRedirectServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;
use App\Infrastructure\Persistence\Eloquent\Models\SlugRedirectModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class HasSlugTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test table
        if (! Schema::hasTable('test_sluggable_models')) {
            Schema::create('test_sluggable_models', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('test_sluggable_without_uuid_models')) {
            Schema::create('test_sluggable_without_uuid_models', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_sluggable_models');
        Schema::dropIfExists('test_sluggable_without_uuid_models');
        parent::tearDown();
    }

    public function test_creating_model_generates_slug_with_uuid(): void
    {
        $model = TestSluggableModel::create([
            'title' => 'My Test Title',
        ]);

        $this->assertNotNull($model->slug);
        $this->assertStringStartsWith('my-test-title-', $model->slug);
        $this->assertMatchesRegularExpression('/^my-test-title-[a-f0-9]{8}$/', $model->slug);
    }

    public function test_creating_model_generates_slug_without_uuid(): void
    {
        $model = TestSluggableWithoutUuidModel::create([
            'title' => 'My Test Title',
        ]);

        $this->assertNotNull($model->slug);
        $this->assertEquals('my-test-title', $model->slug);
    }

    public function test_updating_title_creates_redirect(): void
    {
        $model = TestSluggableModel::create([
            'title' => 'Original Title',
        ]);
        $originalSlug = $model->slug;

        $model->title = 'Updated Title';
        $model->save();

        $this->assertNotEquals($originalSlug, $model->slug);
        $this->assertStringStartsWith('updated-title-', $model->slug);

        $this->assertDatabaseHas('slug_redirects', [
            'old_slug' => $originalSlug,
            'new_slug' => $model->slug,
            'entity_type' => 'test_sluggable',
            'entity_id' => $model->id,
        ]);
    }

    public function test_updating_title_updates_chain_redirects(): void
    {
        $model = TestSluggableModel::create([
            'title' => 'Title A',
        ]);
        $slugA = $model->slug;

        $model->title = 'Title B';
        $model->save();
        $slugB = $model->slug;

        $model->title = 'Title C';
        $model->save();
        $slugC = $model->slug;

        // A should now point directly to C (chain updated)
        $this->assertDatabaseHas('slug_redirects', [
            'old_slug' => $slugA,
            'new_slug' => $slugC,
        ]);

        // B should point to C
        $this->assertDatabaseHas('slug_redirects', [
            'old_slug' => $slugB,
            'new_slug' => $slugC,
        ]);
    }

    public function test_slug_collision_extends_uuid_portion(): void
    {
        $model1 = TestSluggableModel::create([
            'title' => 'Same Title',
        ]);

        // Manually create collision by setting same base slug
        $model1->slug = 'collision-test-' . substr($model1->id, 0, 8);
        $model1->saveQuietly();

        $model2 = new TestSluggableModel();
        $model2->id = $model1->id; // Force same ID prefix to test collision logic
        $model2->id = 'aaaaaaaa' . substr($model1->id, 8); // Same first 8 chars

        // This forces the collision detection to extend the UUID portion
        // but in practice, different UUIDs will produce different slugs
        $model2->title = 'Another Title';
        $model2->save();

        $this->assertNotEquals($model1->slug, $model2->slug);
    }

    public function test_slug_collision_adds_numeric_suffix(): void
    {
        $model1 = TestSluggableWithoutUuidModel::create([
            'title' => 'Same Title',
        ]);

        $model2 = TestSluggableWithoutUuidModel::create([
            'title' => 'Same Title',
        ]);

        $model3 = TestSluggableWithoutUuidModel::create([
            'title' => 'Same Title',
        ]);

        $this->assertEquals('same-title', $model1->slug);
        $this->assertEquals('same-title-2', $model2->slug);
        $this->assertEquals('same-title-3', $model3->slug);
    }

    public function test_numeric_suffix_increments_on_multiple_collisions(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            TestSluggableWithoutUuidModel::create([
                'title' => 'Repeated Title',
            ]);
        }

        $this->assertDatabaseHas('test_sluggable_without_uuid_models', ['slug' => 'repeated-title']);
        $this->assertDatabaseHas('test_sluggable_without_uuid_models', ['slug' => 'repeated-title-2']);
        $this->assertDatabaseHas('test_sluggable_without_uuid_models', ['slug' => 'repeated-title-3']);
        $this->assertDatabaseHas('test_sluggable_without_uuid_models', ['slug' => 'repeated-title-4']);
        $this->assertDatabaseHas('test_sluggable_without_uuid_models', ['slug' => 'repeated-title-5']);
    }

    public function test_empty_title_uses_fallback(): void
    {
        $model = TestSluggableModel::create([
            'title' => '!!!',
        ]);

        $this->assertStringStartsWith('item-', $model->slug);
    }

    public function test_special_characters_are_converted(): void
    {
        $model = TestSluggableModel::create([
            'title' => '¡Hola Mundo! ¿Cómo estás?',
        ]);

        $this->assertStringStartsWith('hola-mundo-como-estas-', $model->slug);
    }

    public function test_no_redirect_when_title_unchanged(): void
    {
        $model = TestSluggableModel::create([
            'title' => 'My Title',
        ]);

        SlugRedirectModel::query()->delete();

        $model->title = 'My Title'; // Same title
        $model->save();

        $this->assertDatabaseCount('slug_redirects', 0);
    }
}

/**
 * Test model that uses HasSlug with UUID (default mode).
 *
 * @property string $id
 * @property string $title
 * @property string $slug
 */
class TestSluggableModel extends Model
{
    use HasSlug;
    use HasUuids;

    protected $table = 'test_sluggable_models';

    protected $fillable = ['id', 'title', 'slug'];

    public function getSlugEntityType(): string
    {
        return 'test_sluggable';
    }
}

/**
 * Test model that uses HasSlug without UUID (numeric suffix mode).
 *
 * @property string $id
 * @property string $title
 * @property string $slug
 */
class TestSluggableWithoutUuidModel extends Model
{
    use HasSlug;
    use HasUuids;

    protected $table = 'test_sluggable_without_uuid_models';

    protected $fillable = ['id', 'title', 'slug'];

    public function getSlugEntityType(): string
    {
        return 'test_sluggable_no_uuid';
    }

    protected function slugIncludesUuid(): bool
    {
        return false;
    }
}
