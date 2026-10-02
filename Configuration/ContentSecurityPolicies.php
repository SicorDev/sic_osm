<?php

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

/*
 * Map tiles are images loaded by the browser from the tile servers:
 *  - backend: location picker of the map record (OSM standard tiles)
 *  - frontend: interactive map (tile server of the chosen layer), only relevant if the
 *    frontend Content Security Policy is enabled
 */
return Map::fromEntries(
    [
        Scope::backend(),
        new MutationCollection(
            new Mutation(MutationMode::Extend, Directive::ImgSrc, new UriValue('https://tile.openstreetmap.org')),
        ),
    ],
    [
        Scope::frontend(),
        new MutationCollection(
            new Mutation(
                MutationMode::Extend,
                Directive::ImgSrc,
                new UriValue('https://tile.openstreetmap.org'),
                new UriValue('https://tile.openstreetmap.de'),
                new UriValue('https://tile.memomaps.de'),
                new UriValue('https://*.tile.openstreetmap.fr'),
                new UriValue('https://*.tile.opentopomap.org'),
                new UriValue('https://*.tile-cyclosm.openstreetmap.fr'),
            ),
        ),
    ],
);
