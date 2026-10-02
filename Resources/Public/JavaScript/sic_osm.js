/*
 * sic_osm – initialises all interactive maps of a page.
 * Configuration per map: JSON in data-sic-osm-config (see MapController::buildDynamicConfig()).
 * Marker markup (incl. popup): <template class="sic-osm__marker-template"> next to the map,
 * rendered by Fluid – identical to the static view.
 * Two-click maps: static image with an activation bar; Leaflet is loaded on demand and the
 * interactive map replaces the image (data-sic-osm-click holds the same JSON configuration).
 */
(function () {
    'use strict';

    var LEAFLET_PREFIX = '<a href="https://leafletjs.com" target="_blank" rel="noopener noreferrer">Leaflet</a>';
    /** sessionStorage key: the visitor allowed interactive maps for this visit (no cookie) */
    var CONSENT_KEY = 'sicOsmInteractiveMaps';
    var leafletPromise = null;

    /**
     * @param {boolean|null} popupOpen state of the info box to take over (two-click maps), null = as rendered
     */
    function createMarkerIcon(template, popupOpen) {
        var marker = template.content.firstElementChild.cloneNode(true);
        var toggle = marker.querySelector('.sic-osm__popup-toggle');
        if (toggle && popupOpen !== null && popupOpen !== undefined) {
            toggle.checked = popupOpen;
        }
        // Pin (toggles the info box via <label>) and info box (links, text selection, scrolling)
        // stay usable: their clicks and scrolling must not move or zoom the map
        L.DomEvent.disableClickPropagation(marker);
        L.DomEvent.disableScrollPropagation(marker);
        // The 0×0 icon sits on the coordinate; the pin wrapper inside is anchored bottom centre (see sic_osm.css)
        return L.divIcon({
            html: marker,
            className: 'sic-osm__marker-icon',
            iconSize: [0, 0],
            iconAnchor: [0, 0]
        });
    }

    /**
     * Centre the map so that the coordinate appears at focusY of the height
     * (0.5 = centre, 0.7 = lower, leaving room for the popup above the marker)
     */
    function setFocusedView(map, config) {
        var height = map.getSize().y;
        var point = map.project([config.lat, config.lon], config.zoom).subtract([0, (config.focusY - 0.5) * height]);
        map.setView(map.unproject(point, config.zoom), config.zoom, { animate: false });
        return height > 0;
    }

    function initMap(element, popupOpen) {
        var config;
        try {
            config = JSON.parse(element.getAttribute('data-sic-osm-config'));
        } catch (e) {
            return;
        }

        var map = L.map(element, {
            // Zooming with the mouse wheel only after the visitor interacted with the map –
            // otherwise scrolling the page would zoom the map instead
            scrollWheelZoom: false
        });
        map.attributionControl.setPrefix(LEAFLET_PREFIX);

        L.tileLayer(config.tileUrl, {
            maxZoom: config.maxZoom,
            subdomains: config.subdomains || 'abc',
            attribution: config.attribution
        }).addTo(map);

        var focused = setFocusedView(map, config);

        var template = config.marker ? element.parentNode.querySelector('template.sic-osm__marker-template') : null;
        if (template) {
            L.marker([config.lat, config.lon], {
                icon: createMarkerIcon(template, popupOpen),
                interactive: false,
                keyboard: false
            }).addTo(map);
        }

        map.on('click focus', function () {
            map.scrollWheelZoom.enable();
        });
        map.on('mouseout blur', function () {
            map.scrollWheelZoom.disable();
        });

        // Maps in initially hidden containers (tabs, accordions) need a size update when shown;
        // the focused view is applied once the map has a real size
        if ('ResizeObserver' in window) {
            new ResizeObserver(function () {
                map.invalidateSize();
                if (!focused) {
                    focused = setFocusedView(map, config);
                }
            }).observe(element);
        }
    }

    /** Leaflet (CSS + UMD script from this extension), loaded once on demand */
    function loadLeaflet(scriptUrl, cssUrl) {
        if (typeof L !== 'undefined') {
            return Promise.resolve();
        }
        if (!leafletPromise) {
            leafletPromise = new Promise(function (resolve, reject) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = cssUrl;
                document.head.appendChild(link);
                var script = document.createElement('script');
                script.src = scriptUrl;
                script.onload = resolve;
                script.onerror = function () {
                    leafletPromise = null;
                    reject(new Error('Leaflet could not be loaded'));
                };
                document.head.appendChild(script);
            });
        }
        return leafletPromise;
    }

    function isConsentRemembered() {
        try {
            return window.sessionStorage.getItem(CONSENT_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function rememberConsent() {
        try {
            window.sessionStorage.setItem(CONSENT_KEY, '1');
        } catch (e) {
            // Storage not available (e.g. blocked): the choice applies to this map only
        }
    }

    /**
     * Replace the static image of a two-click map by the interactive map at the same place
     * @param {boolean} byVisitor focus the map afterwards (activated by the button, not automatically)
     */
    function activate(figure, byVisitor) {
        if (figure.hasAttribute('data-sic-osm-activated')) {
            return;
        }
        figure.setAttribute('data-sic-osm-activated', '');
        var button = figure.querySelector('.sic-osm__activate-button');
        if (button) {
            button.disabled = true;
        }
        loadLeaflet(figure.getAttribute('data-sic-osm-leaflet-js'), figure.getAttribute('data-sic-osm-leaflet-css')).then(function () {
            var canvas = figure.querySelector('.sic-osm__canvas');
            var toggle = canvas ? canvas.querySelector('.sic-osm__popup-toggle') : null;
            var popupOpen = toggle ? toggle.checked : null;

            var element = document.createElement('div');
            element.className = 'sic-osm__map';
            element.setAttribute('role', 'region');
            element.setAttribute('aria-label', figure.getAttribute('data-sic-osm-label') || '');
            element.setAttribute('data-sic-osm-config', figure.getAttribute('data-sic-osm-click'));
            element.setAttribute('data-sic-osm-ready', '');
            // Removing the static canvas also removes its checkbox, so the IDs in the marker template stay unique
            canvas.parentNode.replaceChild(element, canvas);
            ['.sic-osm__activate', '.sic-osm__attribution'].forEach(function (selector) {
                var obsolete = figure.querySelector(selector);
                if (obsolete) {
                    obsolete.parentNode.removeChild(obsolete);
                }
            });
            figure.classList.remove('sic-osm--static');
            figure.classList.add('sic-osm--dynamic');
            initMap(element, popupOpen);
            if (byVisitor) {
                // Leaflet makes the map container focusable (keyboard navigation)
                element.focus({ preventScroll: true });
            }
        }).catch(function () {
            figure.removeAttribute('data-sic-osm-activated');
            if (button) {
                button.disabled = false;
            }
        });
    }

    function initClickMaps() {
        var figures = document.querySelectorAll('[data-sic-osm-click]:not([data-sic-osm-ready])');
        var remembered = isConsentRemembered();
        Array.prototype.forEach.call(figures, function (figure) {
            figure.setAttribute('data-sic-osm-ready', '');
            if (remembered) {
                activate(figure, false);
                return;
            }
            var bar = figure.querySelector('.sic-osm__activate');
            var button = figure.querySelector('.sic-osm__activate-button');
            if (!bar || !button) {
                return;
            }
            // Only shown with JavaScript – without it, the static map stays as it is
            bar.hidden = false;
            button.addEventListener('click', function () {
                var remember = figure.querySelector('.sic-osm__activate-remember-input');
                if (remember && remember.checked) {
                    rememberConsent();
                    // All other two-click maps of this page follow the visitor's choice
                    Array.prototype.forEach.call(document.querySelectorAll('.sic-osm--click.sic-osm--static'), function (other) {
                        if (other !== figure) {
                            activate(other, false);
                        }
                    });
                }
                activate(figure, true);
            });
        });
    }

    function init() {
        initClickMaps();
        if (typeof L === 'undefined') {
            return;
        }
        var elements = document.querySelectorAll('[data-sic-osm-config]');
        for (var i = 0; i < elements.length; i++) {
            if (!elements[i].hasAttribute('data-sic-osm-ready')) {
                elements[i].setAttribute('data-sic-osm-ready', '');
                initMap(elements[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
