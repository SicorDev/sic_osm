# sic_osm – OpenStreetMap

TYPO3 extension that shows **one place** on an OpenStreetMap map – as a static image rendered on the server (no data sent to third parties) or as an interactive Leaflet map, with a pin and an info box that visitors can open and close. Small and simple on purpose: for many places (address directories with categories and lists) use [sic_address](https://extensions.typo3.org/extension/sic_address) instead.

> **Status: 1.0.0 (beta), tested on TYPO3 14.** Static and interactive map, two-click solution, info box, geocoding, backend map preview, TypoScript integration, pin colour, map layer selection with preview images and page module preview work. Planned: GPX tracks.

## Features

- **Map records:** Maps are configured as records in a SysFolder and can be reused on any page; the content element selects the record directly (no record storage page needed)
- **Three views per content element:** static image, interactive map, or **static image with interactive map on click** (two-click solution). The same map can be shown differently on different pages
- **Two-click solution:** The static map (with pin and info box) is shown first – nothing is sent to third parties. A button below the map names the tile server and loads the interactive map at the same place on click – without page reload, without cookie. "Remember for this visit" (sessionStorage) opens further two-click maps interactively right away. Without JavaScript the static map stays fully usable
- **Static map image (GDPR-friendly):** Rendered once on the server from OSM tiles and stored in `typo3temp/assets/sic_osm/`. Visitors only load the finished image from your server – nothing is sent to third parties
- **Interactive map:** [Leaflet](https://leafletjs.com) 1.9.4, delivered from this extension (no CDN). Six map layers, all usable without API key: OpenStreetMap Standard, OpenStreetMap Germany (German labels), public transport map (bus and rail lines, stops), Humanitarian (pale, calm background), OpenTopoMap (topographic) and CyclOSM (cycling) – selected in the content element from preview images. Mouse wheel zoom only after clicking the map, so scrolling the page is not captured. Several maps per page possible. Fallback link to openstreetmap.org without JavaScript
- **Pin with info box:** Title, address and free text (rich text: opening hours, holidays, links) in the look of a Leaflet popup. Clicking the pin opens and closes the box, the × closes it; initial state per map: open, closed or no info box. Works without JavaScript and identically in both views; on very narrow maps (below 360 px) the box is shown below the map
- **Map in grayscale:** Subdued map without colours, the pin and info box stay coloured – per map, in all views
- **Pin colour:** One pin shape, coloured per map with a colour picker (default red); the outline is darkened automatically
- **Custom pin:** Replace the default pin with your own image (FAL, also SVG) – always anchored at its bottom centre
- **Geocoding:** Enter an address, save – a new map gets its latitude and longitude via Nominatim (OpenStreetMap). Later, the button **"Determine coordinates from address"** places the pin at the address again (e.g. after an address change or to undo a fine-tuning). An existing position is never overwritten on saving. Company names in the first line are handled
- **Backend map preview:** The map record shows a small map with the pin – drag it to fine-tune the position (e.g. to the entrance); latitude and longitude are updated in the form and stored on saving. Zooming the preview sets the zoom level of the map (shown in the map corner). The pin follows the chosen colour
- **Page module preview:** The content element shows the static map as thumbnail with the pin, plus map record (link to edit it), view and map layer; warnings for missing, hidden or unpositioned maps
- **TypoScript integration:** `lib.sicOsm.map` renders a map record without content element, e.g. in the footer of a sitepackage
- **Responsive:** The map is always as wide as its column and has a fixed height (at most 70% of the screen height on small screens). On narrow screens it keeps its scale and shows a narrower section – street names stay readable, the pin stays in place. The static image is rendered 2000 px wide (configurable) and cropped to the column
- **Accessible:** Real `<img>` with alternative text, labelled map region, keyboard navigation (Leaflet), info box toggle reachable by keyboard
- **Always up to date:** Changed settings lead to a new image automatically; saving the map record renders it again; images older than 90 days (configurable) are renewed
- **Cache handling:** Pages showing a map are refreshed automatically when the map record is saved
- **Precise coordinates:** Decimal degrees with 7 decimals; dot or comma accepted, range validated
- **License-compliant attribution:** Attribution of every map layer in German and English, with an English fallback that never disappears
- **Robust against sitepackage CSS:** Global rules like `img { width: 100% }` do not affect the pin
- **Content Security Policy:** Tile servers are allowed for backend and frontend (`Configuration/ContentSecurityPolicies.php`)

## Requirements

- TYPO3 14
- PHP 8.2 – 8.5 with the GD extension
- Outgoing HTTPS connections from the server to the tile server (only when rendering a static map) and to Nominatim (geocoding)

## Installation

Classic installation via Extension Manager (Composer is supported, but not required).

After installation:

1. Run the database analyzer (Admin Tools → Maintenance → Analyze Database Structure).
2. Include the static TypoScript template **"OpenStreetMap"** in your site's root template.
3. Optional: enter a contact e-mail in the extension configuration (see below).

## Usage

1. Create a **Map** record in a SysFolder:
   - **General:** title (also used as alternative text), address (a new map gets its coordinates on saving), latitude/longitude (optional), map preview (drag the pin to fine-tune, zoom the map to set the zoom level; button "Determine coordinates from address"), height, image format, grayscale
   - **Marker:** show pin, pin colour, optional custom pin image, info box (open / closed / none), info box title and information
2. Add a content element **OpenStreetMap** (tab "Plugins") to a page.
3. In the plugin tab select the map, the view (static image / static image, interactive on click / interactive map) and, for the interactive views, the map layer.

### Without content element (TypoScript)

```html
<f:cObject typoscriptObjectPath="lib.sicOsm.map" data="{map: 3}" />
<f:cObject typoscriptObjectPath="lib.sicOsm.map" data="{map: 3, view: 'dynamic', layer: 'cyclosm'}" />
```

`map` is the uid of the map record; `view` (`static` / `click` / `dynamic`) and `layer` (`osm` / `osmde` / `oepnv` / `hot` / `opentopomap` / `cyclosm`) are optional.

## Configuration

### Extension configuration

Admin Tools → Settings → Extension Configuration → `sic_osm`

| Setting | Default | Description |
|---|---|---|
| `tileUrl` | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` | 256 px map tiles for static images; placeholders `{z}`, `{x}`, `{y}`, optional `{s}` |
| `contactEmail` | *(empty)* | Sent to the tile server and Nominatim, as recommended by the [tile](https://operations.osmfoundation.org/policies/tiles/) and [Nominatim](https://operations.osmfoundation.org/policies/nominatim/) usage policies |
| `nominatimUrl` | `https://nominatim.openstreetmap.org/search` | Geocoding service for the address of a map record |
| `imageWidth` | `2000` | Rendered width of static map images in px (400–4000); the map is as wide as its container and crops the image |
| `maxAge` | `90` | Days after which static images are rendered again with current OSM data; `0` = never |

### TypoScript constants

| Constant | Default | Description |
|---|---|---|
| `plugin.tx_sicosm.settings.view` | `static` | Default view (`static` / `click` / `dynamic`), if not set in the content element |
| `plugin.tx_sicosm.settings.layer` | `osm` | Default map layer of the interactive map (views `click` and `dynamic`) |
| `plugin.tx_sicosm.settings.includeDefaultCss` | `1` | Include `sic_osm.css`; only `0` switches it off – without the static template it is included as well (the Leaflet CSS is always included for interactive maps) |

### CSS variables

| Variable | Default | Description |
|---|---|---|
| `--sic-osm-popup-background` | `#fff` | Background of the info box and its tip |
| `--sic-osm-popup-color` | `#333` | Text colour of the info box |
| `--sic-osm-button-background` | `#0078a8` | Background of the button "Load interactive map" (two-click view) |
| `--sic-osm-button-color` | `#fff` | Text colour of that button |

### Template override

```typoscript
plugin.tx_sicosm {
    view.templateRootPaths.10 = EXT:your_sitepackage/Resources/Private/Templates/SicOsm/
    view.partialRootPaths.10 = EXT:your_sitepackage/Resources/Private/Partials/SicOsm/
}
```

Partials: `Map/Static.html` (static image), `Map/Dynamic.html` (interactive map), `Map/ClickToLoad.html` (two-click view), `Map/Marker.html` (pin and toggle, both views), `Map/Popup.html` (info box).

## Privacy

| Where | Data sent to third parties |
|---|---|
| Static image | None – the image is rendered on your server |
| Two-click view | Before the click: none (static image). After the click: like the interactive map. The choice "Remember for this visit" is stored in the browser's sessionStorage (no cookie, deleted when the browser is closed) |
| Interactive map | The visitor's browser loads map tiles from the tile server of the chosen layer (IP address, user agent, referrer): `tile.openstreetmap.org` (OpenStreetMap Foundation, UK), `tile.openstreetmap.de` (FOSSGIS e.V., DE), `tile.memomaps.de` (public transport map, MeMoMaps, DE – free to use, but high-traffic sites must host it themselves), `tile.openstreetmap.fr` (OpenStreetMap France: layer Humanitarian), `tile.opentopomap.org` (OpenTopoMap, DE) or `tile-cyclosm.openstreetmap.fr` (OpenStreetMap France). Mention this in your privacy policy. |
| Geocoding (backend) | When saving a new map record or clicking the button, the server sends the address to Nominatim (OpenStreetMap Foundation) – no visitor data, and not the editor's IP address |
| Map preview (backend) | The editor's browser loads OSM standard tiles from `tile.openstreetmap.org` |

Leaflet itself is delivered from your server.

## Troubleshooting

- **"The map is currently not available":** The static map could not be rendered. Check the TYPO3 log (`var/log/`) for entries of `SICOR.SicOsm`: missing GD extension, tile server not reachable or folder `typo3temp/assets/sic_osm/` not writable. The page retries after 5 minutes.
- **Labels or attribution missing after an update:** Language files are cached in the system cache. Flush it via Admin Tools → Maintenance → *Flush TYPO3 and PHP Cache*. (The attribution falls back to English, so it never disappears.)
- **"Address could not be found":** Check the spelling, set the pin in the map preview, or enter latitude and longitude manually.
- **Pin not at the new address after changing it:** An existing position is kept on saving on purpose. Click "Determine coordinates from address" and save.
- **Map preview stays grey:** A stricter backend Content Security Policy of your installation may block `tile.openstreetmap.org` in `img-src`.
- **Hint "static TypoScript not included" in the page module:** Include the static template **"OpenStreetMap"** in the TypoScript template of the site. Without it the map still works (defaults, CSS), but `lib.sicOsm.map` and the TypoScript settings are missing.
- **Page module loads slowly once:** The preview of a map content element renders the static map image if it does not exist yet (the same image the frontend uses) – this takes a few seconds once per map.
- **Render all maps again:** Admin Tools → Maintenance → *Remove Temporary Assets*, then flush the frontend caches.
- **Map shows old data:** Open the map record and save it – the static image is rendered again.
- **Two-click map looks different after the click:** The static image is always rendered from OpenStreetMap Standard tiles (`tileUrl`), the interactive map uses the chosen layer. Choose OpenStreetMap Standard for a seamless transition.
- **Grayscale has no effect:** "Map in grayscale" is a CSS filter of `sic_osm.css`. With `includeDefaultCss = 0` add it to your own CSS: `.sic-osm--grayscale .sic-osm__image, .sic-osm--grayscale .leaflet-tile-pane { filter: grayscale(100%) }`.
- **Layer OpenStreetMap France disappeared after an update:** It was removed in 0.4.7; content elements still set to it show OpenStreetMap Standard.
- **Frontend shows a different section than the map preview:** Expected. The preview has a different size and centres the pin, while the frontend map is as wide as its column and places the pin lower when there is an info box. The zoom level is the same.

## Credits

- Inspired by [rb_osmsimple](https://extensions.typo3.org/extension/rb_osmsimple) by Rolf Bartusel (2008–2009), [route](https://extensions.typo3.org/extension/route) by Dirk Wildt (2013–2014) and [cbgooglemaps / Quick Maps](https://extensions.typo3.org/extension/cbgooglemaps) by Christian Brinkert (2011–2018)
- Further ideas from [rt_simpleosm](https://extensions.typo3.org/extension/rt_simpleosm) (layer selection with preview images, pin colour, page module preview), [osm](https://extensions.typo3.org/extension/osm) by in2code (hint on missing TypoScript), [maps2](https://extensions.typo3.org/extension/maps2) by jweiland.net (two-click solution) and [tile_maps](https://extensions.typo3.org/extension/tile_maps) by codemacher (grayscale)
- [Leaflet](https://leafletjs.com) © Volodymyr Agafonkin, BSD-2-Clause (`Resources/Public/Contrib/leaflet/LICENSE`)
- Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors, ODbL

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)
