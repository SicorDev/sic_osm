<?php
defined('TYPO3') || die();

(function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'SicOsm',
        'Map',
        [
            \SICOR\SicOsm\Controller\MapController::class => 'show',
        ],
        // cacheable: output only depends on the map record (cache tag per record, see MapController)
        [],
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    // Backend evaluation of coordinate fields (TCA type number/decimal would round to 2 decimals)
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][\SICOR\SicOsm\Evaluation\LatitudeEvaluation::class] = '';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals'][\SICOR\SicOsm\Evaluation\LongitudeEvaluation::class] = '';

    // Backend map of the map record: drag the pin to fine-tune latitude/longitude
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1758794400] = [
        'nodeName' => 'sicOsmLocationPicker',
        'priority' => 40,
        'class' => \SICOR\SicOsm\Form\Element\LocationPickerElement::class,
    ];

    // Backend styles: map preview of the record, layer preview images in the FlexForm, page module preview
    $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['sic_osm'] = 'EXT:sic_osm/Resources/Public/Css/backend.css';

    // Saving/deleting a map record removes its static images and flushes the pages showing it
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['sic_osm'] = \SICOR\SicOsm\Hooks\DataHandlerHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['sic_osm'] = \SICOR\SicOsm\Hooks\DataHandlerHook::class;
})();
