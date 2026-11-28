<?php

declare(strict_types=1);

namespace App\Application\Bots\Contracts;

/**
 * A bot a module offers: an account that is not a person and acts for the site (for instance a
 * forum publisher). The key is `<module>.<name>` and starts with the registering module's name.
 */
interface BotInterface
{
    /** `<module>.<name>`, e.g. `forum.publisher` */
    public function key(): string;

    /** What the administration sees in lists, e.g. «Publicador del foro» */
    public function label(): string;

    /** What the bot does, for the administration */
    public function description(): string;

    /** The public name of the account; null means the site's name */
    public function name(): ?string;
}
