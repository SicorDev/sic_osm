/*
 * sic_osm – backend map of the map record (FormEngine element "sicOsmLocationPicker").
 * Dragging the pin, or clicking into the map while no position exists, writes latitude and
 * longitude into the visible form fields; FormEngine copies them into the hidden fields on
 * "change". Typing coordinates moves the pin. The zoom level of the record is set by zooming this
 * map (hidden field, the TCA field "zoom" is passthrough). The button "Determine coordinates from address"
 * geocodes the address in the form via the AJAX route sic_osm_geocode (server side, Nominatim)
 * and moves the pin there. Stored on saving, like any other field.
 */
const LEAFLET_URL = new URL('../../Contrib/leaflet/leaflet.js', import.meta.url).href;
/** Share of the fill colour in the outline of the pin – same as Pin::OUTLINE_SHARE (PHP) */
const OUTLINE_SHARE = 0.55;
/** Centre of Germany – shown while the record has no position yet */
const FALLBACK_VIEW = { lat: 51.16, lon: 10.45, zoom: 5 };

let leafletPromise = null;

/** Leaflet 1.9 is a UMD build (window.L), so it is loaded as classic script, once */
function loadLeaflet() {
    if (window.L) {
        return Promise.resolve(window.L);
    }
    if (!leafletPromise) {
        leafletPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = LEAFLET_URL;
            script.onload = () => resolve(window.L);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    return leafletPromise;
}

function visibleField(name) {
    return document.querySelector(`[data-formengine-input-name="${CSS.escape(name)}"]`);
}

/** #rgb / #rrggbb → lower-case #rrggbb, otherwise null (like Pin::normalizeColor(), without default) */
function normalizeColor(value) {
    let color = value.trim().toLowerCase();
    if (/^#[0-9a-f]{3}$/.test(color)) {
        color = '#' + [...color.slice(1)].map((c) => c + c).join('');
    }
    return /^#[0-9a-f]{6}$/.test(color) ? color : null;
}

/** Darker outline of the pin – same formula as Pin::outlineColor() */
function outlineColor(color) {
    return '#' + [1, 3, 5]
        .map((offset) => Math.round(parseInt(color.slice(offset, offset + 2), 16) * OUTLINE_SHARE).toString(16).padStart(2, '0'))
        .join('');
}

function readCoordinate(name) {
    const value = (visibleField(name)?.value ?? '').trim().replace(',', '.');
    return value === '' || isNaN(Number(value)) ? null : Number(value);
}

async function initPicker(element) {
    const config = JSON.parse(element.dataset.sicOsmPicker);
    const L = await loadLeaflet();
    const hint = element.parentElement.querySelector('.sic-osm-picker__hint');
    const help = element.parentElement.querySelector('.sic-osm-picker__help');
    const zoomField = element.parentElement.querySelector('.sic-osm-picker__zoom');

    // No zoom animation: zoomend fires immediately, so the recorded zoom level is always the one
    // shown (an animation still running could otherwise finish after the pin was set)
    const map = L.map(element, { scrollWheelZoom: false, zoomAnimation: false });
    map.attributionControl.setPrefix(false);
    L.tileLayer(config.tileUrl, { maxZoom: config.maxZoom, attribution: config.attribution }).addTo(map);
    map.on('click focus', () => map.scrollWheelZoom.enable());
    map.on('mouseout blur', () => map.scrollWheelZoom.disable());

    // Same pin as in the frontend (inline SVG from Pin::svg()), tip on the coordinate
    const [pinWidth, pinHeight] = config.pinSize;
    const icon = L.divIcon({
        html: config.pin,
        className: 'sic-osm-picker__marker',
        iconSize: [pinWidth, pinHeight],
        iconAnchor: [pinWidth / 2, pinHeight],
    });
    let marker = null;

    // Colour field of the record (tab "Marker"): the pin follows it while the editor picks a colour
    const colorField = visibleField(config.markerColorField);
    const updatePinColor = () => {
        const color = normalizeColor(colorField?.value ?? '');
        const body = marker?.getElement()?.querySelector('.sic-osm__pin-body');
        if (color && body) {
            body.setAttribute('fill', color);
            body.setAttribute('stroke', outlineColor(color));
        }
    };
    colorField?.addEventListener('input', updatePinColor);
    colorField?.addEventListener('change', updatePinColor);

    // Current zoom level of the record, shown in the map corner (only once there is a pin)
    const zoomInfo = L.control({ position: 'bottomleft' });
    zoomInfo.onAdd = () => L.DomUtil.create('div', 'sic-osm-picker__zoom-info');
    zoomInfo.addTo(map);
    const showZoom = () => {
        zoomInfo.getContainer().textContent = marker ? config.labels.zoom.replace('%s', String(map.getZoom())) : '';
    };

    // Every zoom change while a pin exists becomes the zoom level of the record.
    // Without a pin (overview of Germany) nothing is taken over.
    map.on('zoomend', () => {
        if (marker && zoomField && zoomField.value !== String(map.getZoom())) {
            zoomField.value = String(map.getZoom());
            markChanged(zoomField);
        }
        showZoom();
    });
    // Set while this module writes the fields, so its own "change" events do not move the pin again
    let writing = false;

    const writeFields = (latLng) => {
        const position = latLng.wrap();
        writing = true;
        for (const [name, value] of [[config.latitudeField, position.lat], [config.longitudeField, position.lng]]) {
            const field = visibleField(name);
            if (field) {
                field.value = value.toFixed(7);
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        writing = false;
    };

    const placeMarker = (latLng) => {
        if (marker) {
            marker.setLatLng(latLng);
        } else {
            marker = L.marker(latLng, { icon, draggable: true, autoPan: true }).addTo(map);
            marker.on('dragend', () => writeFields(marker.getLatLng()));
            // The colour may have been changed before the first pin was set
            updatePinColor();
        }
        // "No position yet" is replaced by the help text for zoom and fine-tuning
        if (hint) {
            hint.hidden = true;
        }
        if (help) {
            help.hidden = false;
        }
        showZoom();
    };

    /** First pin (no position before): zoom in to the default zoom level of the record */
    const setFirstPin = (latLng) => {
        placeMarker(latLng);
        map.setView(latLng, config.zoom);
    };

    if (config.hasPosition) {
        map.setView([config.lat, config.lon], config.zoom);
        placeMarker(L.latLng(config.lat, config.lon));
    } else {
        map.setView([FALLBACK_VIEW.lat, FALLBACK_VIEW.lon], FALLBACK_VIEW.zoom);
    }

    // No position yet: a click into the map sets the pin
    map.on('click', (event) => {
        if (!marker) {
            setFirstPin(event.latlng);
            writeFields(event.latlng);
        }
    });

    // Coordinates typed into the fields move the pin
    for (const name of [config.latitudeField, config.longitudeField]) {
        visibleField(name)?.addEventListener('change', () => {
            if (writing) {
                return;
            }
            const lat = readCoordinate(config.latitudeField);
            const lon = readCoordinate(config.longitudeField);
            if (lat !== null && lon !== null && Math.abs(lat) <= 90 && Math.abs(lon) <= 180 && (lat !== 0 || lon !== 0)) {
                if (marker) {
                    placeMarker(L.latLng(lat, lon));
                    map.panTo([lat, lon]);
                } else {
                    setFirstPin(L.latLng(lat, lon));
                }
            }
        });
    }

    // Button: geocode the address currently in the form (server side) and move the pin there
    const button = element.parentElement.querySelector('.sic-osm-picker__geocode');
    button?.addEventListener('click', async () => {
        const address = (visibleField(config.addressField)?.value ?? '').trim();
        if (address === '') {
            notify('warning', config.labels.title, config.labels.empty);
            return;
        }
        button.disabled = true;
        try {
            const { default: AjaxRequest } = await import('@typo3/core/ajax/ajax-request.js');
            const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.sic_osm_geocode).post({ address });
            const data = await response.resolve();
            if (data.success) {
                const latLng = L.latLng(data.latitude, data.longitude);
                if (marker) {
                    // Keep the zoom level the editor has chosen
                    placeMarker(latLng);
                    map.panTo(latLng);
                } else {
                    setFirstPin(latLng);
                }
                writeFields(latLng);
                notify('success', config.labels.title, config.labels.success.replace('%s', data.label));
            } else {
                const message = data.reason === 'empty' ? config.labels.empty : config.labels.notFound.replace('%s', data.query ?? address);
                notify('warning', config.labels.title, message);
            }
        } catch (error) {
            console.error('sic_osm: geocoding request failed', error);
            notify('error', config.labels.title, config.labels.failed);
        } finally {
            button.disabled = false;
        }
    });

    // Maps in initially hidden tabs need a size update when the tab is shown
    new ResizeObserver(() => map.invalidateSize()).observe(element);
}

/**
 * Mark the hidden zoom field as changed, so FormEngine warns about unsaved changes
 * (loaded on demand, the map works without it)
 */
async function markChanged(field) {
    try {
        const { default: FormEngineValidation } = await import('@typo3/backend/form-engine-validation.js');
        FormEngineValidation.markFieldAsChanged(field);
    } catch (error) {
        // Outside the TYPO3 backend: nothing to mark
    }
}

/** TYPO3 backend notification (loaded on demand, the map works without it) */
async function notify(type, title, message) {
    try {
        const { default: Notification } = await import('@typo3/backend/notification.js');
        Notification[type](title, message);
    } catch (error) {
        console.warn('sic_osm:', message);
    }
}

document.querySelectorAll('[data-sic-osm-picker]:not([data-sic-osm-ready])').forEach((element) => {
    element.setAttribute('data-sic-osm-ready', '');
    initPicker(element).catch((error) => console.error('sic_osm: location picker could not be initialised', error));
});
