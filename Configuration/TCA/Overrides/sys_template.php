<?php
defined('TYPO3') || die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'sic_osm',
    'Configuration/TypoScript/',
    'OpenStreetMap'
);
