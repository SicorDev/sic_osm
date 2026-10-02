<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenStreetMap',
    'description' => 'One place on an OpenStreetMap map: static image rendered on the server (GDPR-friendly), interactive Leaflet map or two-click solution, with info box, geocoding and backend map preview.',
    'category' => 'plugin',
    'version' => '1.0.0',
    'state' => 'beta',
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
