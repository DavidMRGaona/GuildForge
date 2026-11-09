<?php

declare(strict_types=1);

namespace App\Application\Publications\Contracts;

use App\Application\Publications\DTOs\PublicationDTO;

/**
 * Offers a kind of published record (events, game tables, campaigns…) to modules that announce
 * them elsewhere, such as a forum or a channel.
 *
 * The core registers its own (events); modules return theirs from
 * ModuleServiceProvider::registerPublicationSources(). Whoever owns the record dispatches
 * PublicationChanged after any save that may change what find() returns, and PublicationRemoved
 * when it is deleted, both once the change has committed. Without a listener nothing happens.
 */
interface PublicationSourceInterface
{
    /**
     * Unique, stable kebab-case type of the records this source offers (e.g. 'game-table').
     * Listeners store it, so it must never change.
     */
    public function type(): string;

    /**
     * The record as it is now, or null when it does not exist or is not published (a draft, a
     * record taken down). A cancelled or ended record that is still published is returned, with
     * its state.
     */
    public function find(string $id): ?PublicationDTO;

    /**
     * Ids of the published records that are not over yet (cancelled ones included while their
     * date has not passed), for listeners that reconcile what they announced.
     *
     * @return iterable<string>
     */
    public function publishedIds(): iterable;
}
