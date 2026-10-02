<?php
defined('TYPO3') || die();

$ll = 'LLL:EXT:sic_osm/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $ll . 'tx_sicosm_domain_model_map',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'typeicon_classes' => [
            'default' => 'sic_osm-map',
        ],
        'default_sortby' => 'title ASC',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    title,
                    --palette--;' . $ll . 'palette.address;address,
                    --palette--;' . $ll . 'palette.position;position,
                    --palette--;' . $ll . 'palette.size;size,
                    --palette--;' . $ll . 'palette.appearance;appearance,
                --div--;' . $ll . 'tab.marker,
                    show_marker,
                    --palette--;;marker,
                    --palette--;' . $ll . 'palette.popup;popup,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    hidden,
            ',
        ],
    ],
    'palettes' => [
        'address' => [
            'showitem' => 'address',
        ],
        'position' => [
            'showitem' => 'latitude, longitude, --linebreak--, location_picker',
        ],
        'size' => [
            'showitem' => 'height, image_format',
        ],
        'appearance' => [
            'showitem' => 'grayscale',
        ],
        'marker' => [
            'showitem' => 'marker_color, --linebreak--, marker',
        ],
        'popup' => [
            'showitem' => 'popup_state, --linebreak--, popup_title, --linebreak--, popup_text',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'title' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.title',
            'description' => $ll . 'tx_sicosm_domain_model_map.title.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'address' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.address',
            'description' => $ll . 'tx_sicosm_domain_model_map.address.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 4,
                'eval' => 'trim',
            ],
        ],
        'latitude' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.latitude',
            'description' => $ll . 'tx_sicosm_domain_model_map.latitude.description',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'max' => 15,
                'eval' => 'trim,' . \SICOR\SicOsm\Evaluation\LatitudeEvaluation::class,
            ],
        ],
        'longitude' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.longitude',
            'description' => $ll . 'tx_sicosm_domain_model_map.longitude.description',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'max' => 15,
                'eval' => 'trim,' . \SICOR\SicOsm\Evaluation\LongitudeEvaluation::class,
            ],
        ],
        // Set by zooming the map preview (location_picker renders the hidden input), no own form field
        'zoom' => [
            'config' => [
                'type' => 'passthrough',
                'default' => 15,
            ],
        ],
        'location_picker' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.location_picker',
            'description' => $ll . 'tx_sicosm_domain_model_map.location_picker.description',
            // Backend map to fine-tune the position; writes into latitude/longitude, has no own value
            'config' => [
                'type' => 'user',
                'renderType' => 'sicOsmLocationPicker',
            ],
        ],
        'height' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.height',
            'description' => $ll . 'tx_sicosm_domain_model_map.height.description',
            'config' => [
                'type' => 'number',
                'size' => 5,
                'range' => [
                    'lower' => 150,
                    'upper' => 1200,
                ],
                'default' => 450,
            ],
        ],
        'image_format' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.image_format',
            'description' => $ll . 'tx_sicosm_domain_model_map.image_format.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'JPG', 'value' => 'jpg'],
                    ['label' => 'PNG', 'value' => 'png'],
                ],
                'default' => 'jpg',
            ],
        ],
        'show_marker' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.show_marker',
            'onChange' => 'reload',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
            ],
        ],
        'popup_state' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.popup_state',
            'description' => $ll . 'tx_sicosm_domain_model_map.popup_state.description',
            'displayCond' => 'FIELD:show_marker:=:1',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'tx_sicosm_domain_model_map.popup_state.open', 'value' => 'open'],
                    ['label' => $ll . 'tx_sicosm_domain_model_map.popup_state.closed', 'value' => 'closed'],
                    ['label' => $ll . 'tx_sicosm_domain_model_map.popup_state.none', 'value' => 'none'],
                ],
                'default' => 'open',
            ],
        ],
        'popup_title' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.popup_title',
            'description' => $ll . 'tx_sicosm_domain_model_map.popup_title.description',
            'displayCond' => [
                'AND' => ['FIELD:show_marker:=:1', 'FIELD:popup_state:!=:none'],
            ],
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'popup_text' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.popup_text',
            'description' => $ll . 'tx_sicosm_domain_model_map.popup_text.description',
            'displayCond' => [
                'AND' => ['FIELD:show_marker:=:1', 'FIELD:popup_state:!=:none'],
            ],
            'config' => [
                'type' => 'text',
                'enableRichtext' => true,
                'cols' => 40,
                'rows' => 6,
            ],
        ],
        // Map in grayscale: CSS filter on the map, pin and info box stay coloured
        'grayscale' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.grayscale',
            'description' => $ll . 'tx_sicosm_domain_model_map.grayscale.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        // Colour of the default pin; no effect with a custom marker image
        'marker_color' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.marker_color',
            'description' => $ll . 'tx_sicosm_domain_model_map.marker_color.description',
            'displayCond' => 'FIELD:show_marker:=:1',
            'config' => [
                'type' => 'color',
                'size' => 10,
                'default' => \SICOR\SicOsm\Service\Pin::DEFAULT_COLOR,
            ],
        ],
        'marker' => [
            'label' => $ll . 'tx_sicosm_domain_model_map.marker',
            'description' => $ll . 'tx_sicosm_domain_model_map.marker.description',
            'displayCond' => 'FIELD:show_marker:=:1',
            'config' => [
                'type' => 'file',
                'maxitems' => 1,
                'allowed' => 'svg,png,gif,webp,jpg,jpeg',
            ],
        ],
    ],
];
