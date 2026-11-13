<?php

declare(strict_types=1);

return [
    'notices' => [
        'subject_type' => 'Proposal',
        'type_label' => [
            'events' => 'event',
            'articles' => 'article',
        ],
        'review_subject' => 'Proposed :type to review: :title',
        'review_body' => ':author sent the proposed :type «:title» for review.',
        'review_site_title' => 'Proposed :type to review',
        'review_site_body' => ':title, by :author',
        'review_digest_line' => ':author proposes the :type «:title»',
        'review_action' => 'Review proposal',
        'status' => [
            'approved' => [
                'subject' => 'Your proposed :type «:title» was approved',
                'body' => 'Your proposed :type «:title» was approved and is now published.',
                'site_title' => 'Your proposal was approved',
            ],
            'returned' => [
                'subject' => 'Your proposed :type «:title» needs changes',
                'body' => 'Your proposed :type «:title» was returned so you can correct it and send it again.',
                'site_title' => 'Your proposal needs changes',
            ],
            'rejected' => [
                'subject' => 'Your proposed :type «:title» was rejected',
                'body' => 'Your proposed :type «:title» was rejected.',
                'site_title' => 'Your proposal was rejected',
            ],
        ],
        'reason' => 'Reason: :reason',
        'status_action' => 'See my proposals',
    ],
];
