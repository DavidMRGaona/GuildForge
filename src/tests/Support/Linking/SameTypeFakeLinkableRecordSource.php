<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

/**
 * A second source claiming the type 'fake', as when two modules pick the same type.
 */
final class SameTypeFakeLinkableRecordSource extends FakeLinkableRecordSource {}
