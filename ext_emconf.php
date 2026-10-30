<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenStreetMap',
    'description' => 'GDPR-friendly OpenStreetMap for one location: a static image rendered server-side, an interactive Leaflet map, or a two-click privacy solution, with info box, pin colours, several map styles, geocoding and a backend map preview. No API key required, Leaflet is bundled.',
    'category' => 'plugin',
    'version' => '1.0.1',
    'state' => 'stable',
    'author' => 'SICOR Dev Team, Claude (Anthropic)',
    'author_email' => 'dev@sicor.de',
    'author_company' => 'SICOR GmbH',
    'license' => 'GPL-2.0-or-later',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.4.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
