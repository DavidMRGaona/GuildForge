<?php

declare(strict_types=1);

return [
    // Days a guide counts as new or updated
    'badge_days' => 30,

    // Days search metrics are kept
    'metrics_retention_days' => 180,

    // Locale used when a guide has no translation for the requested one
    'fallback_locale' => 'es',

    // Global search synonyms: each group lists words or phrases that mean the same (both ways)
    'synonyms' => [
        ['partida', 'mesa'],
        ['apuntarse', 'apuntarme', 'inscribirse', 'inscribirme'],
        ['darse de baja', 'cancelar', 'desapuntarse'],
        ['socio', 'miembro'],
        ['cuota', 'pago'],
        ['contraseña', 'clave'],
        ['correo', 'email'],
        ['campaña', 'crónica'],
    ],

    // Words ignored by the search
    'stop_words' => [
        'de', 'del', 'la', 'las', 'el', 'los', 'un', 'una', 'unos', 'unas', 'y', 'o', 'en', 'a', 'al',
        'para', 'por', 'con', 'que', 'mi', 'mis', 'me', 'se', 'lo', 'como',
    ],
];
