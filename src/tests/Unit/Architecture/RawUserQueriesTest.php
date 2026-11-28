<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\Files\ReadsFiles;

/**
 * Bots (core#863) are hidden from every Eloquent query on users by the `WithoutBots` global scope. A query
 * that skips Eloquent (`DB::table('users')`, a join on `users`) or drops every global scope sees them again,
 * so each one in `app/` must be known here with the reason a bot cannot slip through it.
 *
 * The dynamic `$class::query()->withoutGlobalScopes()` of `EloquentColumnMediaSource` (used by `AvatarMediaSource`) is not
 * matched by the pattern and sees bots on purpose: a bot's avatar must count as used in the media library.
 */
final class RawUserQueriesTest extends TestCase
{
    use ReadsFiles;

    private const string PATTERN = '/DB::table\(\s*[\'"](?:\w+\.)?users(?:\s+(?i:as)\s+\w+)?[\'"]'
        .'|->from\(\s*[\'"](?:\w+\.)?users(?:\s+(?i:as)\s+\w+)?[\'"]'
        .'|(?i:join(?:where)?)\(\s*[\'"](?:\w+\.)?users(?:\s+(?i:as)\s+\w+)?[\'"]'
        .'|UserModel::(?:query\(\)->)?withoutGlobalScopes\(\s*\)/';

    /**
     * File (relative to `app/`) => why a bot cannot slip through it.
     *
     * @var array<string, string>
     */
    private const array ALLOWED = [
        'Infrastructure/Impersonation/RoleViewAccount.php' => 'Inserts the throwaway account of a view as roles; reads nothing.',
        'Infrastructure/Authorization/Services/InactiveRoleHolders.php' => 'Joins role holders; a bot holds no role.',
        'Infrastructure/Authorization/Services/RoleChangeAnnouncer.php' => 'Joins role holders; a bot holds no role.',
        'Infrastructure/Profiles/Services/ProfileMembershipDisplay.php' => 'Joins public profiles; a bot has no profile.',
        'Infrastructure/Mail/Preferences/MailCategoryOptOutStatistics.php' => 'Filters bot_key itself.',
    ];

    public function test_raw_queries_on_users_are_known_and_justified(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(dirname(__DIR__, 3).'/app/'));

            if (preg_match(self::PATTERN, self::readFile($file->getPathname())) === 1 && ! isset(self::ALLOWED[$relative])) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'These files query users around the WithoutBots scope; filter bot_key or justify them in ALLOWED: '.implode(', ', $offenders));
    }

    public function test_every_allowed_file_still_has_such_a_query(): void
    {
        foreach (array_keys(self::ALLOWED) as $relative) {
            $this->assertSame(
                1,
                preg_match(self::PATTERN, self::readFile(dirname(__DIR__, 3).'/app/'.$relative)),
                "{$relative} no longer queries users around the scope: remove it from ALLOWED.",
            );
        }
    }

    #[DataProvider('rawQueries')]
    public function test_pattern_catches_raw_queries(string $code): void
    {
        $this->assertSame(1, preg_match(self::PATTERN, $code), "Pattern misses: {$code}");
    }

    #[DataProvider('safeQueries')]
    public function test_pattern_ignores_scoped_queries(string $code): void
    {
        $this->assertSame(0, preg_match(self::PATTERN, $code), "Pattern flags: {$code}");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rawQueries(): iterable
    {
        yield 'query builder' => ["DB::table('users')->count();"];
        yield 'from' => ["\$db->from('users')"];
        yield 'join' => ["->join('users', 'users.id', '=', 'x.user_id')"];
        yield 'left join' => ["->leftJoin('users', 'users.id', '=', 'x.user_id')"];
        yield 'right join' => ["->rightJoin('users', 'users.id', '=', 'x.user_id')"];
        yield 'cross join' => ["->crossJoin('users')"];
        yield 'aliased table' => ["DB::table('users as u')->count();"];
        yield 'aliased from' => ["\$db->from('users as u')"];
        yield 'aliased join' => ["->leftJoin('users as u', 'u.id', '=', 'x.user_id')"];
        yield 'upper case alias keyword' => ["DB::table('users AS u')->count();"];
        yield 'join where' => ["->joinWhere('users', 'users.id', '=', 'x.user_id')"];
        yield 'left join where' => ["->leftJoinWhere('users', 'users.id', '=', 'x.user_id')"];
        yield 'right join where' => ["->rightJoinWhere('users as u', 'u.id', '=', 'x.user_id')"];
        yield 'schema qualified table' => ["DB::table('public.users')->count();"];
        yield 'schema qualified join' => ["->join('public.users', 'users.id', '=', 'x.user_id')"];
        yield 'schema qualified alias' => ["->from('public.users as u')"];
        yield 'all scopes dropped' => ['UserModel::withoutGlobalScopes()->get();'];
        yield 'all scopes dropped after query' => ['UserModel::query()->withoutGlobalScopes()->get();'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeQueries(): iterable
    {
        yield 'eloquent' => ['UserModel::query()->count();'];
        yield 'with bots' => ['UserModel::query()->withBots()->get();'];
        yield 'one scope dropped' => ['UserModel::withoutGlobalScope(SoftDeletingScope::class)->get();'];
        yield 'similar table name' => ["DB::table('users_archive')->count();"];
        yield 'schema qualified similar name' => ["DB::table('public.users_archive')->count();"];
        yield 'other table' => ["DB::table('user_role')->count();"];
        yield 'other model' => ['ArticleModel::query()->withoutGlobalScopes()->get();'];
    }
}
