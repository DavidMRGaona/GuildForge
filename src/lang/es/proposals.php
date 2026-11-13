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

    'seo' => [
        'events' => 'Proponer un evento',
        'articles' => 'Proponer un artículo',
    ],

    'fields' => [
        'title' => 'título',
        'description' => 'descripción',
        'location' => 'lugar',
        'start_date' => 'fecha de inicio',
        'start_time' => 'hora de inicio',
        'end_date' => 'fecha de fin',
        'end_time' => 'hora de fin',
        'image' => 'imagen',
        'tags' => 'etiquetas',
        'note' => 'nota para la administración',
        'excerpt' => 'extracto',
        'content' => 'contenido',
    ],

    'flash' => [
        'saved' => 'Borrador guardado.',
        'submitted' => 'Propuesta enviada a revisión. Te avisaremos cuando se revise.',
        'published' => 'Propuesta publicada.',
        'withdrawn' => 'Propuesta retirada: vuelve a ser un borrador.',
        'deleted' => 'Propuesta eliminada.',
        'saved_not_sent' => 'Se ha guardado el borrador, pero no se ha podido enviar: :reason',
    ],

    'errors' => [
        'generic' => 'No se ha podido completar la acción.',
        'end_before_start' => 'El fin no puede ser anterior al inicio.',
        'invalid_status' => 'La propuesta ya no está en un estado que permita esta acción.',
        'pending_limit' => 'Ya tienes el máximo de propuestas en revisión. Espera a que se revisen o retira alguna.',
        'drafts_limit' => 'Ya tienes el máximo de borradores abiertos. Envía o elimina alguno.',
        'hourly_limit' => 'Has enviado demasiadas propuestas en la última hora. Inténtalo más tarde.',
        'invalid_title' => 'El título no es válido.',
        'invalid_dates' => 'El fin no puede ser anterior al inicio.',
        'invalid_content' => 'El contenido no puede estar vacío.',
        'invalid_image' => 'La imagen no es válida.',
        'invalid_location' => 'El lugar no es válido.',
        'invalid_excerpt' => 'El extracto no es válido.',
        'invalid_note' => 'La nota no es válida.',
    ],
];
