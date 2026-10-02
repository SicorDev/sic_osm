<?php

// Import map for the backend ES modules (location picker of the map record)
return [
    'dependencies' => ['backend'],
    'imports' => [
        '@sicor/sic-osm/' => 'EXT:sic_osm/Resources/Public/JavaScript/',
    ],
];
