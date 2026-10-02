<?php
namespace SICOR\SicOsm\Domain\Model;

use SICOR\SicOsm\Service\Pin;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Map extends AbstractEntity
{
    public const POPUP_OPEN = 'open';
    public const POPUP_CLOSED = 'closed';
    public const POPUP_NONE = 'none';

    protected string $title = '';
    protected string $address = '';
    protected float $latitude = 0.0;
    protected float $longitude = 0.0;
    protected int $zoom = 15;
    protected int $height = 450;
    protected string $imageFormat = 'jpg';
    protected bool $showMarker = true;
    protected string $popupState = self::POPUP_OPEN;
    protected ?FileReference $marker = null;
    protected string $popupTitle = '';
    protected string $popupText = '';
    protected string $markerColor = Pin::DEFAULT_COLOR;
    protected bool $grayscale = false;

    /**
     * Map built from a raw database row – for the page module preview, which runs outside Extbase.
     * Only the fields needed for the static image and the pin are set; the custom marker image is not
     * resolved (FileReference).
     */
    public static function fromRow(array $row): self
    {
        $map = new self();
        $map->uid = (int)($row['uid'] ?? 0);
        $map->pid = (int)($row['pid'] ?? 0);
        $map->title = (string)($row['title'] ?? '');
        $map->address = (string)($row['address'] ?? '');
        $map->latitude = (float)($row['latitude'] ?? 0);
        $map->longitude = (float)($row['longitude'] ?? 0);
        $map->zoom = (int)($row['zoom'] ?? 15) ?: 15;
        $map->height = (int)($row['height'] ?? 450) ?: 450;
        $map->imageFormat = (string)($row['image_format'] ?? 'jpg');
        $map->showMarker = (bool)($row['show_marker'] ?? true);
        $map->popupState = (string)($row['popup_state'] ?? self::POPUP_OPEN);
        $map->popupTitle = (string)($row['popup_title'] ?? '');
        $map->markerColor = (string)($row['marker_color'] ?? '');
        $map->grayscale = (bool)($row['grayscale'] ?? false);
        return $map;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * 0/0 means "not set" (e.g. address could not be geocoded)
     */
    public function hasPosition(): bool
    {
        return $this->latitude !== 0.0 || $this->longitude !== 0.0;
    }

    public function getZoom(): int
    {
        return $this->zoom;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getImageFormat(): string
    {
        return $this->imageFormat;
    }

    public function isShowMarker(): bool
    {
        return $this->showMarker;
    }

    /**
     * Initial state of the info box: open, closed (opened by clicking the pin) or none
     */
    public function getPopupState(): string
    {
        return in_array($this->popupState, [self::POPUP_CLOSED, self::POPUP_NONE], true) ? $this->popupState : self::POPUP_OPEN;
    }

    /**
     * The pin has an info box above it (open or closed initially)
     */
    public function hasPopup(): bool
    {
        return $this->showMarker && $this->getPopupState() !== self::POPUP_NONE;
    }

    public function isPopupOpen(): bool
    {
        return $this->hasPopup() && $this->getPopupState() === self::POPUP_OPEN;
    }

    public function getMarker(): ?FileReference
    {
        return $this->marker;
    }

    /**
     * Popup title, falls back to the map title
     */
    public function getPopupTitle(): string
    {
        return $this->popupTitle !== '' ? $this->popupTitle : $this->title;
    }

    public function getPopupText(): string
    {
        return $this->popupText;
    }

    /**
     * Map shown in grayscale (CSS filter on the map only, pin and info box stay coloured)
     */
    public function isGrayscale(): bool
    {
        return $this->grayscale;
    }

    /**
     * Colour of the default pin as #rrggbb (invalid or empty → default colour)
     */
    public function getMarkerColor(): string
    {
        return Pin::normalizeColor($this->markerColor);
    }
}
