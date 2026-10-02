<?php

use SICOR\SicOsm\Controller\Backend\GeocodeAjaxController;

// Button "Determine coordinates from address" in the map record (backend map preview)
return [
    'sic_osm_geocode' => [
        'path' => '/sic-osm/geocode',
        'methods' => ['POST'],
        'target' => GeocodeAjaxController::class . '::geocodeAction',
    ],
];
