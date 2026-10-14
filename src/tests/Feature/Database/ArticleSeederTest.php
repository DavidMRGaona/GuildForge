<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ArticleSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_articles_are_written_by_the_seeded_admins_and_editors(): void
    {
        $this->seed([RolesSeeder::class, UserSeeder::class]);
        $this->assertSame(2, $this->staffCount());

        $this->seed(ArticleSeeder::class);

        // No extra editor is created when the seeded accounts already hold admin or editor
        $this->assertSame(2, $this->staffCount());

        $authorization = app(AuthorizationServiceInterface::class);
        $authors = UserModel::query()
            ->whereIn('id', ArticleModel::query()->select('author_id'))
            ->get();

        $this->assertNotEmpty($authors);
        foreach ($authors as $author) {
            $this->assertTrue(
                $authorization->hasRole($author, 'admin') || $authorization->hasRole($author, 'editor'),
                "Author {$author->email} is neither admin nor editor",
            );
            $this->assertContains($author->email, ['admin@example.local', 'editor@example.local']);
        }
    }

    private function staffCount(): int
    {
        return UserModel::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'editor']))
            ->count();
    }
}
