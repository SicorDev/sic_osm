<?php
namespace SICOR\SicOsm\Service;

/**
 * Web Mercator projection as used by OpenStreetMap tiles (EPSG:3857, 256 px tiles)
 */
final class TileMath
{
    public const TILE_SIZE = 256;

    /** Web Mercator is undefined at the poles; tiles end at ±85.0511° */
    public const MAX_LATITUDE = 85.0511287798;

    /**
     * Global pixel coordinates of a position at the given zoom level
     *
     * @return array{0: float, 1: float} x, y
     */
    public static function toPixel(float $latitude, float $longitude, int $zoom): array
    {
        $scale = self::TILE_SIZE * (2 ** $zoom);
        $latRad = deg2rad(max(-self::MAX_LATITUDE, min(self::MAX_LATITUDE, $latitude)));
        $x = ($longitude + 180) / 360 * $scale;
        $y = (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $scale;
        return [$x, $y];
    }

    /**
     * Number of tiles per axis at the given zoom level
     */
    public static function tilesPerAxis(int $zoom): int
    {
        return 2 ** $zoom;
    }
}
