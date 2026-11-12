<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Security\Services\ContentSecurityPolicySourcesInterface;
use App\Domain\Security\ContentSecurityOrigin;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Keeps the functions modules registered and resolves them when the headers are built. It
 * fails closed: a value that is not an exact https origin is dropped with a warning, and a
 * function that throws adds nothing, so a mistake never opens more than was asked for.
 */
final class ContentSecurityPolicySources implements ContentSecurityPolicySourcesInterface
{
    /** @var list<Closure(): iterable<mixed>> */
    private array $frames = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function allowFrames(Closure $origins): void
    {
        $this->frames[] = $origins;
    }

    public function frameOrigins(): array
    {
        $origins = [];

        foreach ($this->frames as $resolver) {
            // Read the whole list first: a function that fails half-way adds none of its origins.
            try {
                $values = [...$resolver()];
            } catch (Throwable $exception) {
                $this->logger->warning('A module failed to give its frame-src origins; none of them was added.', ['exception' => $exception->getMessage()]);

                continue;
            }

            foreach ($values as $value) {
                $origin = is_string($value) ? ContentSecurityOrigin::tryFrom($value) : null;
                if (! $origin instanceof ContentSecurityOrigin) {
                    $this->logger->warning('A frame-src origin was left out of the Content Security Policy: it is not an exact https origin.', ['value' => is_scalar($value) ? (string) $value : get_debug_type($value)]);

                    continue;
                }
                $origins[$origin->value] = true;
            }
        }

        $list = array_map(strval(...), array_keys($origins));
        sort($list);

        return $list;
    }
}
