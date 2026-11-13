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
        'review_requests' => 'Asked the modules for:',
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

    'seo' => [
        'events' => 'Propose an event',
        'articles' => 'Propose an article',
    ],

    'fields' => [
        'title' => 'title',
        'description' => 'description',
        'location' => 'location',
        'start_date' => 'start date',
        'start_time' => 'start time',
        'end_date' => 'end date',
        'end_time' => 'end time',
        'image' => 'image',
        'tags' => 'tags',
        'note' => 'note for the administration',
        'excerpt' => 'excerpt',
        'content' => 'content',
    ],

    'requests' => [
        'errors' => [
            'required' => 'Fill in «:field».',
            'boolean' => '«:field» must be yes or no.',
            'integer' => '«:field» must be a whole number.',
            'min' => '«:field» must be at least :min.',
            'max' => '«:field» must be at most :max.',
            'option' => 'Choose one of the options of «:field».',
            'text' => '«:field» must be text.',
            'max_length' => '«:field» takes at most :max_length characters.',
        ],
        'notice_line' => ':label: :description',
    ],

    'flash' => [
        'saved' => 'Draft saved.',
        'submitted' => 'Proposal sent for review. We will let you know once it is reviewed.',
        'published' => 'Proposal published.',
        'withdrawn' => 'Proposal withdrawn: it is a draft again.',
        'deleted' => 'Proposal deleted.',
        'saved_not_sent' => 'The draft was saved, but it could not be sent: :reason',
    ],

    'errors' => [
        'generic' => 'The action could not be completed.',
        'end_before_start' => 'The end cannot be before the start.',
        'invalid_status' => 'The proposal is no longer in a status that allows this action.',
        'pending_limit' => 'You already have the maximum number of proposals in review. Wait for them to be reviewed or withdraw one.',
        'drafts_limit' => 'You already have the maximum number of open drafts. Send or delete one.',
        'hourly_limit' => 'You have sent too many proposals in the last hour. Try again later.',
        'invalid_title' => 'The title is not valid.',
        'invalid_dates' => 'The end cannot be before the start.',
        'invalid_content' => 'The content cannot be empty.',
        'invalid_image' => 'The image is not valid.',
        'invalid_location' => 'The location is not valid.',
        'invalid_excerpt' => 'The excerpt is not valid.',
        'invalid_note' => 'The note is not valid.',
        'invalid_requests' => 'Check the module requests.',
    ],
];
