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
 * An account with two-factor authentication on gives a code at every sign-in (core#770), so only
 * the places below may sign someone in or act as them. A new one either goes through the
 * challenge or is added here with the reason it cannot skip it.
 */
final class SignInPointsTest extends TestCase
{
    use ReadsFiles;

    /**
     * Methods that sign in or check a password, on any receiver ($this->auth->login(),
     * $guard->attempt(), $auth->loginUsingId(), a static Auth::...); and setting the account of
     * the request or validating credentials on the auth manager or a guard (on any receiver those
     * names are too common: forms validate too).
     */
    private const string PATTERN = '/(?:->|::)(login|loginUsingId|attempt|attemptWhen|once|onceUsingId|onceBasic)\s*\('
        .'|(?:Auth::|auth\(\)->|app\([\'"]auth[\'"]\)->|guard\([^)]*\)->|\$guard->|Filament::auth\(\)->)(setUser|validate|basic)\s*\(/';

    /** Where application code lives: the code itself and the route files */
    private const array DIRECTORIES = ['app', 'routes'];

    /**
     * File (relative to src/) => the calls it may make, and why.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        // The password step: validate() checks without signing in; login() only for an account
        // with two-factor authentication off
        'app/Infrastructure/Services/AuthService.php' => ['login', 'validate'],
        // After the challenge accepted the code (site); the panel's sign-in is Filament's, through PanelLogin
        'app/Infrastructure/TwoFactor/SignIn/TwoFactorChallenge.php' => ['login'],
        // Registers the panel's login page (PanelLogin, with the challenge), not a sign-in
        'app/Providers/Filament/AdminPanelProvider.php' => ['login'],
        // Its own private attempt() helper around the cache, not a guard
        'app/Infrastructure/Modules/Services/CacheModuleChangeWindow.php' => ['attempt'],
        // Impersonation sets the account of each request and never signs anybody in
        'app/Http/Middleware/ApplyImpersonation.php' => ['setUser'],
        'app/Infrastructure/Impersonation/ImpersonationService.php' => ['setUser'],
    ];

    public function test_only_the_known_places_sign_in(): void
    {
        $root = dirname(__DIR__, 3);
        $found = [];

        foreach (self::DIRECTORIES as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $calls = $this->callsIn(self::readFile($file->getPathname()));

                if ($calls !== []) {
                    $found[substr($file->getPathname(), strlen($root) + 1)] = $calls;
                }
            }
        }

        ksort($found);
        $allowed = self::ALLOWED;
        ksort($allowed);

        $this->assertSame($allowed, $found, 'Sign-in calls outside the known places (core#770): '.json_encode($found));
    }

    #[DataProvider('signInCalls')]
    public function test_pattern_catches_sign_in_calls(string $code, string $call): void
    {
        $this->assertSame([$call], $this->callsIn("<?php {$code}"));
    }

    #[DataProvider('harmlessCode')]
    public function test_pattern_ignores_comments_and_other_calls(string $code): void
    {
        $this->assertSame([], $this->callsIn("<?php {$code}"));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function signInCalls(): array
    {
        return [
            'facade login' => ['Auth::login($user, true);', 'login'],
            'facade attempt' => ['Auth::attempt($credentials);', 'attempt'],
            'facade by id' => ['Auth::loginUsingId($id);', 'loginUsingId'],
            'helper' => ['auth()->login($user);', 'login'],
            'named guard' => ["Auth::guard('web')->attemptWhen(\$c, \$f);", 'attemptWhen'],
            'injected guard' => ['$this->auth->guard()->once($c);', 'once'],
            'guard variable' => ['$guard->setUser($user);', 'setUser'],
            'container' => ["app('auth')->login(\$user);", 'login'],
            'panel guard' => ['Filament::auth()->login($user);', 'login'],
            'injected auth manager' => ['$this->auth->login($user);', 'login'],
            'injected guard property' => ['$this->guard->login($user, true);', 'login'],
            'any guard variable' => ['$sessionGuard->attempt($credentials);', 'attempt'],
            'auth variable by id' => ['$auth->loginUsingId($id);', 'loginUsingId'],
            'guard in a route file' => ["Route::get('/x', fn () => Auth::onceUsingId(1));", 'onceUsingId'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function harmlessCode(): array
    {
        return [
            'line comment' => ['// Never Auth::login() here'],
            'doc comment' => ['/** Auth::attempt() is not used */'],
            'string' => ["\$text = 'Auth::login(\$user)';"],
            'a form validating' => ['$this->form->validate();'],
            'another login word' => ['$this->loginAttempts(); $user->loginCount();'],
            'reading the user' => ['Auth::user(); Auth::check(); $guard->viaRemember();'],
        ];
    }

    /**
     * The sign-in calls of some PHP code, sorted and without duplicates; comments and strings are ignored.
     *
     * @return list<string>
     */
    private function callsIn(string $source): array
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= ' ';

                continue;
            }

            // Strings keep only their letters, so app('auth') still reads as such and code quoted
            // in a string never looks like a call
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $code .= "'".preg_replace('/[^A-Za-z0-9_]/', '', $token[1])."'";

                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        preg_match_all(self::PATTERN, $code, $matches);
        // One group per branch of the pattern: the name is in whichever matched
        $calls = array_values(array_unique(array_filter([...$matches[1], ...$matches[2]], static fn (string $call): bool => $call !== '')));
        sort($calls);

        return $calls;
    }
}
