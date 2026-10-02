<?php
namespace SICOR\SicOsm\Form\Element;

use SICOR\SicOsm\Enum\Layer;
use SICOR\SicOsm\Service\Pin;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

/**
 * Backend map of the map record: shows the pin at the stored position; dragging the pin
 * (or clicking into the map while no position exists) writes latitude/longitude into the form.
 * The button "Determine coordinates from address" geocodes the address currently in the form
 * (AJAX route sic_osm_geocode) and moves the pin there – e.g. to undo a fine-tuning.
 * Saving stays the only way to store, the element has no value of its own.
 */
final class LocationPickerElement extends AbstractFormElement
{
    private const LL = 'LLL:EXT:sic_osm/Resources/Private/Language/locallang_db.xlf:';

    public function render(): array
    {
        $result = $this->initializeResultArray();
        $row = $this->data['databaseRow'];
        // data[<table>][<uid or NEW…>] – prefix of the form field names
        $fieldPrefix = 'data[' . $this->data['tableName'] . '][' . $row['uid'] . ']';
        $latitude = (float)($row['latitude'] ?? 0);
        $longitude = (float)($row['longitude'] ?? 0);
        // zoom is a passthrough field: this element renders its (hidden) form field
        $zoom = max(1, min(19, (int)($row['zoom'] ?? 15) ?: 15));

        $languageService = $this->getLanguageService();
        $label = static fn(string $key): string => $languageService->sL(self::LL . $key);
        $config = [
            'addressField' => $fieldPrefix . '[address]',
            'latitudeField' => $fieldPrefix . '[latitude]',
            'longitudeField' => $fieldPrefix . '[longitude]',
            // The pin shows the colour of the record and follows changes in the colour field
            'markerColorField' => $fieldPrefix . '[marker_color]',
            'pin' => Pin::svg((string)($row['marker_color'] ?? ''), 'sic-osm-picker__pin'),
            'pinSize' => [Pin::WIDTH, Pin::HEIGHT],
            'hasPosition' => $latitude !== 0.0 || $longitude !== 0.0,
            'lat' => $latitude,
            'lon' => $longitude,
            'zoom' => $zoom,
            'tileUrl' => Layer::Osm->tileUrl(),
            'maxZoom' => Layer::Osm->maxZoom(),
            'attribution' => Layer::Osm->attributionFallback(),
            'labels' => [
                'title' => $label('message.title'),
                'success' => $label('message.geocode.success'),
                'notFound' => $label('message.geocode.notFound'),
                'empty' => $label('picker.addressEmpty'),
                'failed' => $label('picker.requestFailed'),
                'zoom' => $label('picker.zoom'),
            ],
        ];

        $result['html'] = '<div class="formengine-field-item t3js-formengine-field-item">'
            . '<p class="sic-osm-picker__actions">'
            . '<button type="button" class="btn btn-default sic-osm-picker__geocode">' . htmlspecialchars($label('picker.geocode')) . '</button>'
            . '</p>'
            . '<input type="hidden" class="sic-osm-picker__zoom" name="' . htmlspecialchars($fieldPrefix . '[zoom]') . '" value="' . $zoom . '" />'
            . '<div class="sic-osm-picker" data-sic-osm-picker="' . htmlspecialchars(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) . '"></div>'
            . '<p class="sic-osm-picker__hint text-body-secondary"' . ($config['hasPosition'] ? ' hidden' : '') . '>'
            . htmlspecialchars($label('picker.noPosition'))
            . '</p>'
            . '<p class="sic-osm-picker__help text-body-secondary"' . ($config['hasPosition'] ? '' : ' hidden') . '>'
            . htmlspecialchars($label('picker.help'))
            . '</p>'
            . '</div>';
        $result['stylesheetFiles'][] = 'EXT:sic_osm/Resources/Public/Contrib/leaflet/leaflet.css';
        $result['javaScriptModules'][] = JavaScriptModuleInstruction::create('@sicor/sic-osm/backend/location-picker.js');

        return $result;
    }
}
