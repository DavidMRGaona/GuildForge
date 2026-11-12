<?php

declare(strict_types=1);

namespace App\Application\Security\Services;

use Closure;

/**
 * What modules add to the page's Content Security Policy. For now the origins pages may embed
 * in frames (frame-src): a module that shows content of another service with the reader's
 * permission (a video player, a post) registers the origins of its players in boot().
 * Without any, frame-src stays 'self'.
 */
interface ContentSecurityPolicySourcesInterface
{
    /**
     * Registers origins for frame-src. The function is called each time the headers are built,
     * so it can follow the module's settings; it returns origins written exactly
     * (https://host[:port], lowercase). Anything else is left out, and a function that fails
     * adds nothing.
     *
     * @param  Closure(): iterable<mixed>  $origins
     */
    public function allowFrames(Closure $origins): void;

    /**
     * The valid origins every module asked for, each once, sorted.
     *
     * @return list<string>
     */
    public function frameOrigins(): array;
}
