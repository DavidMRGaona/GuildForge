<?php

declare(strict_types=1);

namespace App\Domain\Realtime\ValueObjects;

/**
 * The name of a private real-time channel, without Pusher's `private-` prefix: dot-separated
 * segments, the first a literal (the module, or `core`), the others literals or `{placeholders}`,
 * e.g. `forum.discussion.{discussion}`. A concrete channel fills every placeholder with one id.
 */
final readonly class RealtimeChannelPattern
{
    /** Pusher refuses channel names over 164 characters; `private-` takes 8 of them. */
    public const int MAX_LENGTH = 150;

    private const string LITERAL = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private const string PLACEHOLDER = '/^\{([a-z][A-Za-z0-9]*)\}$/';

    /** What a placeholder takes: one id (a uuid, a ulid, a number, a slug), never a dot. */
    private const string VALUE = '[A-Za-z0-9_-]{1,64}';

    /**
     * @param  list<string>  $parameters
     */
    private function __construct(
        public string $value,
        private string $regex,
        private array $parameters,
    ) {}

    public static function tryFrom(string $pattern): ?self
    {
        if ($pattern === '' || strlen($pattern) > self::MAX_LENGTH) {
            return null;
        }

        $segments = explode('.', $pattern);
        if (count($segments) < 2 || ! self::isOwner($segments[0])) {
            return null;
        }

        $parts = [];
        $parameters = [];
        foreach ($segments as $segment) {
            if (self::isLiteral($segment)) {
                $parts[] = preg_quote($segment, '/');

                continue;
            }

            if (preg_match(self::PLACEHOLDER, $segment, $match) !== 1 || in_array($match[1], $parameters, true)) {
                return null;
            }

            $parameters[] = $match[1];
            $parts[] = '(?<'.$match[1].'>'.self::VALUE.')';
        }

        return new self($pattern, '/^'.implode('\.', $parts).'$/D', $parameters);
    }

    /**
     * Whether a concrete channel name (no placeholders) is well formed.
     */
    public static function isChannelName(string $channel): bool
    {
        if ($channel === '' || strlen($channel) > self::MAX_LENGTH) {
            return false;
        }

        $segments = explode('.', $channel);
        if (count($segments) < 2 || ! self::isOwner($segments[0])) {
            return false;
        }

        return array_all($segments, fn ($segment) => preg_match('/^'.self::VALUE.'$/D', $segment) === 1);
    }

    /**
     * The first segment: whose channel it is.
     */
    public function prefix(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /**
     * @return list<string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * The values of the placeholders by name when the channel is one of this pattern, or null.
     *
     * @return array<string, string>|null
     */
    public function match(string $channel): ?array
    {
        if (strlen($channel) > self::MAX_LENGTH || preg_match($this->regex, $channel, $match) !== 1) {
            return null;
        }

        $values = [];
        foreach ($this->parameters as $parameter) {
            $values[$parameter] = $match[$parameter];
        }

        return $values;
    }

    private static function isLiteral(string $segment): bool
    {
        return preg_match('/^'.self::LITERAL.'$/D', $segment) === 1;
    }

    /**
     * The first segment says whose channel it is (a module name, or `core`): it starts with a
     * letter and can never pass for the protocol's own channel kinds (`private-`, `presence-`),
     * which the browser adds in front.
     */
    private static function isOwner(string $segment): bool
    {
        return self::isLiteral($segment)
            && ctype_lower($segment[0])
            && ! str_starts_with($segment, 'private-')
            && ! str_starts_with($segment, 'presence-');
    }
}
