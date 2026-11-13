<?php

declare(strict_types=1);

return [
    'notices' => [
        'subject_type' => 'Propuesta',
        'type_label' => [
            'events' => 'evento',
            'articles' => 'artículo',
        ],
        'review_subject' => 'Propuesta de :type para revisar: :title',
        'review_body' => ':author ha enviado a revisión la propuesta de :type «:title».',
        'review_site_title' => 'Propuesta de :type para revisar',
        'review_site_body' => ':title, de :author',
        'review_digest_line' => ':author propone el :type «:title»',
        'review_action' => 'Revisar propuesta',
        'status' => [
            'approved' => [
                'subject' => 'Tu propuesta de :type «:title» se ha aprobado',
                'body' => 'Tu propuesta de :type «:title» se ha aprobado y ya está publicada.',
                'site_title' => 'Se ha aprobado tu propuesta',
            ],
            'returned' => [
                'subject' => 'Tu propuesta de :type «:title» necesita cambios',
                'body' => 'Tu propuesta de :type «:title» se ha devuelto para que la corrijas y la envíes de nuevo.',
                'site_title' => 'Tu propuesta necesita cambios',
            ],
            'rejected' => [
                'subject' => 'Tu propuesta de :type «:title» se ha rechazado',
                'body' => 'Tu propuesta de :type «:title» se ha rechazado.',
                'site_title' => 'Se ha rechazado tu propuesta',
            ],
        ],
        'reason' => 'Motivo: :reason',
        'status_action' => 'Ver mis propuestas',
    ],
];
