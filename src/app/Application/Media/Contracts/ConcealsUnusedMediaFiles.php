<?php

declare(strict_types=1);

namespace App\Application\Media\Contracts;

/**
 * Marks a media source whose unused files may be private (core#843), declared beside ShowsMediaPreviews.
 * The library shows the unused files of a folder whose sources show their previews, since those are what
 * gets reviewed before deleting; a source that implements this interface keeps them out of sight instead:
 * a file without any usage whose folder it claims (winning the folder or not) is drawn with the «Imagen
 * oculta» placeholder and is never offered in «Elegir de la biblioteca».
 *
 * For sources where a file loses its last usage while still private: the images of a deleted forum thread
 * in a members-only category, an upload of a message never sent, the replaced portrait of a character with
 * a spoiler. Files with usages follow the usual rule (ShowsMediaPreviews and each usage's `hidden`).
 *
 * It never changes whether a file is used or can be deleted: an unused file is still deleted once its
 * grace period is over.
 */
interface ConcealsUnusedMediaFiles {}
