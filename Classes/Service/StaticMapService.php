<?php
namespace SICOR\SicOsm\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SICOR\SicOsm\Domain\Model\Map;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Renders static map images from OSM tiles on the server.
 *
 * Visitors only load the finished image from this server – no request of the
 * visitor's browser reaches a third party. Tiles are fetched once per map and
 * setting (hash in the file name), which also satisfies the OSM tile usage policy.
 */
final class StaticMapService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const IMAGE_FOLDER = 'typo3temp/assets/sic_osm/';
    private const DEFAULT_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    private const DEFAULT_MAX_AGE_DAYS = '90';
    /** Rendered width; the map is as wide as its container, the image is cropped to it (object-fit: cover) */
    private const DEFAULT_IMAGE_WIDTH = '2000';
    /** Shown where no tiles exist (beyond ±85° latitude), OSM land colour */
    private const BACKGROUND_RGB = [242, 239, 233];

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionSettings $extensionSettings,
    ) {
    }

    /**
     * Returns the static image of the map, rendering it if necessary
     *
     * @param string $siteUrl Sent as Referer to the tile server
     * @param float $focusY Vertical position of the coordinate in the image (0.5 = centre, 0.7 = lower, room for a popup)
     * @return array{url: string, width: int, height: int}|null null if the image could not be rendered
     */
    public function getImage(Map $map, string $siteUrl = '', float $focusY = 0.5): ?array
    {
        $file = $this->getImagePath($map, $focusY);
        if (!$this->isUpToDate($file)) {
            if (!$this->render($map, $file, $siteUrl, $focusY)) {
                return null;
            }
            $this->removeImages((int)$map->getUid(), basename($file));
        }
        return [
            // Modification time as query string: browsers load a re-rendered image immediately
            'url' => PathUtility::getAbsoluteWebPath($file) . '?' . filemtime($file),
            'width' => $this->getImageWidth(),
            'height' => $map->getHeight(),
        ];
    }

    /**
     * Deletes all images of a map, except $keep (file name)
     */
    public function removeImages(int $mapUid, string $keep = ''): void
    {
        $files = glob($this->getFolder() . 'map_' . $mapUid . '_*') ?: [];
        foreach ($files as $file) {
            $name = basename($file);
            if ($name !== $keep && preg_match('/^map_' . $mapUid . '_[0-9a-f]{12}\.(png|jpg)$/', $name)) {
                @unlink($file);
            }
        }
    }

    private function getImagePath(Map $map, float $focusY): string
    {
        $extension = $map->getImageFormat() === 'jpg' ? 'jpg' : 'png';
        // All settings that change the image; a change leads to a new file name.
        // Marker and popup are HTML overlays and therefore not part of the image. The marker colour is
        // included anyway on purpose: a changed colour always leads to a newly rendered map.
        $hash = substr(md5(implode('|', [
            sprintf('%.7F', $map->getLatitude()),
            sprintf('%.7F', $map->getLongitude()),
            $map->getZoom(),
            $this->getImageWidth(),
            $map->getHeight(),
            $extension,
            sprintf('%.2F', $focusY),
            $this->getTileUrl(),
            $map->getMarkerColor(),
        ])), 0, 12);
        return $this->getFolder() . 'map_' . (int)$map->getUid() . '_' . $hash . '.' . $extension;
    }

    private function isUpToDate(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        $maxAgeDays = (int)$this->extensionSettings->get('maxAge', self::DEFAULT_MAX_AGE_DAYS);
        return $maxAgeDays <= 0 || filemtime($file) > time() - $maxAgeDays * 86400;
    }

    private function render(Map $map, string $file, string $siteUrl, float $focusY): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->logger?->error('PHP extension GD is not available, static map cannot be rendered');
            return false;
        }
        $width = $this->getImageWidth();
        $height = max(1, $map->getHeight());
        $zoom = max(0, min(19, $map->getZoom()));
        $tileSize = TileMath::TILE_SIZE;
        $tilesPerAxis = TileMath::tilesPerAxis($zoom);

        // Top left corner of the image in global pixel coordinates; the coordinate is
        // centred horizontally and placed at $focusY vertically
        [$pointX, $pointY] = TileMath::toPixel($map->getLatitude(), $map->getLongitude(), $zoom);
        $originX = (int)round($pointX - $width / 2);
        $originY = (int)round($pointY - $height * $focusY);

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...self::BACKGROUND_RGB));

        for ($tileY = (int)floor($originY / $tileSize); $tileY * $tileSize < $originY + $height; $tileY++) {
            if ($tileY < 0 || $tileY >= $tilesPerAxis) {
                continue;
            }
            for ($tileX = (int)floor($originX / $tileSize); $tileX * $tileSize < $originX + $width; $tileX++) {
                // Wrap around at the date line
                $wrappedX = (($tileX % $tilesPerAxis) + $tilesPerAxis) % $tilesPerAxis;
                $tile = $this->fetchTile($zoom, $wrappedX, $tileY, $siteUrl);
                if ($tile === null) {
                    // Never store an incomplete map
                    return false;
                }
                // Resampling also handles tile servers delivering 512 px (HiDPI) tiles
                imagecopyresampled($image, $tile, $tileX * $tileSize - $originX, $tileY * $tileSize - $originY, 0, 0, $tileSize, $tileSize, imagesx($tile), imagesy($tile));
            }
        }

        GeneralUtility::mkdir_deep($this->getFolder());
        // Write to a temporary file first, so concurrent requests never see a half-written image
        $tempFile = $file . '.' . getmypid() . '.tmp';
        $written = str_ends_with($file, '.jpg') ? imagejpeg($image, $tempFile, 85) : imagepng($image, $tempFile, 9);
        if (!$written || !rename($tempFile, $file)) {
            @unlink($tempFile);
            $this->logger?->error('Static map could not be written', ['file' => $file]);
            return false;
        }
        GeneralUtility::fixPermissions($file);
        return true;
    }

    private function fetchTile(int $zoom, int $x, int $y, string $siteUrl): ?\GdImage
    {
        $url = str_replace(
            ['{z}', '{x}', '{y}', '{s}'],
            [$zoom, $x, $y, ['a', 'b', 'c'][($x + $y) % 3]],
            $this->getTileUrl()
        );
        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => array_filter([
                    'User-Agent' => $this->extensionSettings->getUserAgent($siteUrl),
                    'Referer' => $siteUrl,
                ]),
                'timeout' => 10,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->warning('Map tile could not be loaded', ['url' => $url, 'exception' => $e->getMessage()]);
            return null;
        }
        if ($response->getStatusCode() !== 200) {
            $this->logger?->warning('Map tile could not be loaded', ['url' => $url, 'status' => $response->getStatusCode()]);
            return null;
        }
        $tile = @imagecreatefromstring((string)$response->getBody());
        if ($tile === false) {
            $this->logger?->warning('Map tile is not a valid image', ['url' => $url]);
            return null;
        }
        return $tile;
    }

    /**
     * Width of the rendered image (extension configuration imageWidth, 400–4000 px). Wide enough for
     * wide containers; narrower containers crop it at the sides, so the map keeps its scale.
     */
    private function getImageWidth(): int
    {
        return max(400, min(4000, (int)$this->extensionSettings->get('imageWidth', self::DEFAULT_IMAGE_WIDTH)));
    }

    private function getTileUrl(): string
    {
        return $this->extensionSettings->get('tileUrl', self::DEFAULT_TILE_URL);
    }

    private function getFolder(): string
    {
        return Environment::getPublicPath() . '/' . self::IMAGE_FOLDER;
    }
}
