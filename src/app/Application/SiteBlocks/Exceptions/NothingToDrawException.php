<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\Exceptions;

use App\Application\Exceptions\NothingToDrawException as CommonNothingToDrawException;

/**
 * The site blocks' name for App\Application\Exceptions\NothingToDrawException, kept so the blocks
 * that already throw it keep working; new code throws the common one. Both renderers catch the
 * common class, so either name leaves a block or a section out quietly.
 */
final class NothingToDrawException extends CommonNothingToDrawException {}
