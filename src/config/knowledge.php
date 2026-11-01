<?php

declare(strict_types=1);

return [
    // Days a guide counts as new or updated
    'badge_days' => 30,

    // Days search metrics are kept
    'metrics_retention_days' => 180,

    // Locale used when a guide has no translation for the requested one
    'fallback_locale' => 'es',

    // Global search synonyms: term => equivalent terms
    'synonyms' => [],

    // Words ignored by the search
    'stop_words' => [],
];
