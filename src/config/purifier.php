<?php

declare(strict_types=1);

/**
 * HTMLPurifier configuration for GuildForge.
 *
 * The 'richtext' config is used for sanitizing Filament RichEditor output
 * (articles, legal pages, about history) before rendering with v-html.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
        ],
        'richtext' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => implode(',', [
                // Block elements
                'h1,h2,h3,h4,h5,h6',
                'p[style],div[style],blockquote',
                'ul,ol,li',
                'pre,code',
                'hr,br',
                'table[style],thead,tbody,tfoot,tr,th[style|colspan|rowspan],td[style|colspan|rowspan]',
                // Inline elements
                'strong,b,em,i,u,s,sub,sup',
                'a[href|title|target|rel]',
                'span[style]',
                'img[src|alt|width|height|style]',
            ]),
            'CSS.AllowedProperties' => implode(',', [
                'font-size',
                'font-weight',
                'font-style',
                'font-family',
                'text-decoration',
                'text-align',
                'color',
                'background-color',
                'padding-left',
                'margin-left',
                'list-style-type',
                'width',
                'height',
                'border',
                'border-collapse',
            ]),
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'HTML.TargetBlank' => true,
            'HTML.Nofollow' => true,
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ],
        ],
    ],
];
