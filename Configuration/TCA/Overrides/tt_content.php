<?php
defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

$pluginSignature = ExtensionUtility::registerPlugin(
    'SicOsm',
    'Map',
    'LLL:EXT:sic_osm/Resources/Private/Language/locallang_db.xlf:plugin.map.title',
    'sic_osm-map',
    'plugins',
    'LLL:EXT:sic_osm/Resources/Private/Language/locallang_db.xlf:plugin.map.description',
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// No "pages" (Datensatzsammlung): the map record is selected directly in the FlexForm
$GLOBALS['TCA']['tt_content']['types'][$pluginSignature]['showitem'] = '
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
        --palette--;;general,
        --palette--;;headers,
    --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin,
        pi_flexform,
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
        --palette--;;language,
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
        --palette--;;hidden,
        --palette--;;access,
';

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:sic_osm/Configuration/FlexForms/map.xml',
    $pluginSignature
);
