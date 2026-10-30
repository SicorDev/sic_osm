<?php
namespace SICOR\SicOsm\Enum;

/**
 * Map layers of the interactive view. Tiles are loaded by the visitor's browser
 * directly from these servers. All of them are usable without API key (checked live on 2026-09-30:
 * tile requests and the delivered images; many providers now require keys or accounts). Each layer also needs: CSP entry (ContentSecurityPolicies.php),
 * attribution label (locallang.xlf), FlexForm item with preview image (Resources/Public/Images/Layers/).
 */
enum Layer: string
{
    case Osm = 'osm';
    case Oepnv = 'oepnv';
    case Hot = 'hot';
    case OpenTopoMap = 'opentopomap';
    case CyclOsm = 'cyclosm';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Osm;
    }

    /**
     * Tile URL template in Leaflet syntax
     */
    public function tileUrl(): string
    {
        return match ($this) {
            self::Osm => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            // Free use incl. commercial with attribution; high-traffic sites must host it themselves (memomaps.de FAQ)
            self::Oepnv => 'https://tile.memomaps.de/tilegen/{z}/{x}/{y}.png',
            self::Hot => 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
            self::OpenTopoMap => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            self::CyclOsm => 'https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png',
        };
    }

    /**
     * Host name of the tile server without subdomain placeholder, e.g. "tile.opentopomap.org"
     */
    public function host(): string
    {
        return (string)parse_url(str_replace('{s}.', '', $this->tileUrl()), PHP_URL_HOST);
    }

    public function subdomains(): string
    {
        return match ($this) {
            self::Osm, self::Oepnv => '',
            self::Hot, self::OpenTopoMap, self::CyclOsm => 'abc',
        };
    }

    public function maxZoom(): int
    {
        return match ($this) {
            self::Osm, self::Hot => 19,
            self::Oepnv => 18,
            self::OpenTopoMap => 17,
            self::CyclOsm => 20,
        };
    }

    /**
     * Label key (locallang.xlf) of the attribution required by the provider's license
     */
    public function attributionKey(): string
    {
        return 'map.attribution.' . $this->value;
    }

    /**
     * English attribution, used if the label cannot be resolved (e.g. outdated language cache).
     * The attribution is required by the licenses and must never disappear.
     */
    public function attributionFallback(): string
    {
        $osm = '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors';
        $osmFr = '<a href="https://openstreetmap.fr" target="_blank" rel="noopener noreferrer">OpenStreetMap France</a>';
        return match ($this) {
            self::Osm => $osm,
            self::Oepnv => 'Map <a href="https://memomaps.de" target="_blank" rel="noopener noreferrer">memomaps.de</a> <a href="https://creativecommons.org/licenses/by-sa/2.0/" target="_blank" rel="noopener noreferrer">CC-BY-SA</a>, map data ' . $osm . ' (<a href="https://opendatacommons.org/licenses/odbl/" target="_blank" rel="noopener noreferrer">ODbL</a>)',
            self::Hot => $osm . ' | Map style: <a href="https://www.hotosm.org" target="_blank" rel="noopener noreferrer">Humanitarian OpenStreetMap Team</a>, hosted by ' . $osmFr,
            self::OpenTopoMap => 'Map data: ' . $osm . ', SRTM | Map style: © <a href="https://opentopomap.org" target="_blank" rel="noopener noreferrer">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener noreferrer">CC-BY-SA</a>)',
            self::CyclOsm => $osm . ' | Map style: <a href="https://www.cyclosm.org" target="_blank" rel="noopener noreferrer">CyclOSM</a>, hosted by ' . $osmFr,
        };
    }
}
