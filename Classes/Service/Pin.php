<?php
namespace SICOR\SicOsm\Service;

/**
 * The default pin: one SVG shape, coloured per map record. Rendered inline, so the colour needs
 * neither a file per colour nor CSS – it also works with includeDefaultCss = 0. Used by the frontend
 * (both views), the backend map preview and the page module preview, so the pin looks the same everywhere.
 */
final class Pin
{
    public const DEFAULT_COLOR = '#d7263d';
    public const WIDTH = 32;
    public const HEIGHT = 48;

    private const PATH = 'M16 1C7.7 1 1 7.7 1 16c0 11 15 31 15 31s15-20 15-31C31 7.7 24.3 1 16 1z';
    /** Share of the fill colour in the outline, the rest is black (#d7263d → #761522) */
    private const OUTLINE_SHARE = 0.55;

    /**
     * Lower-case #rrggbb; anything else (empty, invalid) → default colour.
     * The value ends up in HTML attributes, so only this strict format is accepted.
     */
    public static function normalizeColor(string $color): string
    {
        $color = strtolower(trim($color));
        if (preg_match('/^#[0-9a-f]{3}$/', $color)) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }
        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : self::DEFAULT_COLOR;
    }

    /**
     * Darker outline of the pin, so light colours stay visible on the map
     */
    public static function outlineColor(string $color): string
    {
        $color = self::normalizeColor($color);
        $outline = '#';
        foreach ([1, 3, 5] as $offset) {
            $outline .= sprintf('%02x', (int)round(hexdec(substr($color, $offset, 2)) * self::OUTLINE_SHARE));
        }
        return $outline;
    }

    public static function svg(string $color, string $class = 'sic-osm__marker-image sic-osm__pin'): string
    {
        $color = self::normalizeColor($color);
        return '<svg class="' . htmlspecialchars($class) . '" xmlns="http://www.w3.org/2000/svg"'
            . ' width="' . self::WIDTH . '" height="' . self::HEIGHT . '" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '"'
            . ' aria-hidden="true" focusable="false">'
            . '<path class="sic-osm__pin-body" fill="' . $color . '" stroke="' . self::outlineColor($color) . '" stroke-width="1.5" d="' . self::PATH . '"/>'
            . '<circle cx="16" cy="16" r="6" fill="#fff"/>'
            . '</svg>';
    }
}
