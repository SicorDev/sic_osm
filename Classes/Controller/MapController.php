<?php
namespace SICOR\SicOsm\Controller;

use Psr\Http\Message\ResponseInterface;
use SICOR\SicOsm\Domain\Model\Map;
use SICOR\SicOsm\Domain\Repository\MapRepository;
use SICOR\SicOsm\Enum\Layer;
use SICOR\SicOsm\Service\Pin;
use SICOR\SicOsm\Service\StaticMapService;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class MapController extends ActionController
{
    /** Page cache lifetime (seconds) if the map could not be rendered, e.g. tile server not reachable */
    private const RETRY_LIFETIME = 300;

    /** Vertical position of the coordinate: centre, or lower to leave room for the info box above */
    private const FOCUS_CENTER = 0.5;
    private const FOCUS_POPUP = 0.7;

    public function __construct(
        private readonly MapRepository $mapRepository,
        private readonly StaticMapService $staticMapService,
    ) {
    }

    public function showAction(): ResponseInterface
    {
        $data = $this->request->getAttribute('currentContentObject')?->data ?? [];
        // Content element: FlexForm settings. TypoScript lib.sicOsm.map: values from the data array,
        // e.g. <f:cObject typoscriptObjectPath="lib.sicOsm.map" data="{map: 3, view: 'dynamic'}" />
        $mapUid = (int)(($this->settings['map'] ?? 0) ?: ($data['map'] ?? 0));
        $view = (string)(($data['view'] ?? '') ?: ($this->settings['view'] ?? 'static'));
        $layerSetting = (string)(($data['layer'] ?? '') ?: ($this->settings['layer'] ?? ''));

        // findByUid() ignores the storage page, so no "Datensatzsammlung" is needed
        $map = $mapUid > 0 ? $this->mapRepository->findByUid($mapUid) : null;
        $marker = null;
        $staticMap = null;
        $dynamicConfig = null;
        $focusY = self::FOCUS_CENTER;

        if ($map !== null) {
            $cacheCollector = $this->request->getAttribute('frontend.cache.collector');
            // Saving the map record flushes this tag (see DataHandlerHook), so pages showing
            // the map are updated although the record lives in a SysFolder
            $cacheCollector?->addCacheTags(new CacheTag('tx_sicosm_domain_model_map_' . $mapUid));

            if ($map->hasPosition()) {
                $marker = $this->resolveMarker($map);
                // Also with an initially closed box: opening it must not need a different map section
                $focusY = $map->hasPopup() ? self::FOCUS_POPUP : self::FOCUS_CENTER;
                // "click" (two-click solution): static image first, the interactive map is loaded on request,
                // so nothing is sent to third parties before the visitor agrees
                if ($view === 'dynamic' || $view === 'click') {
                    $dynamicConfig = $this->buildDynamicConfig($map, $layerSetting, $marker !== null, $focusY);
                }
                if ($view !== 'dynamic') {
                    $siteUrl = $this->request->getAttribute('normalizedParams')?->getSiteUrl() ?? '';
                    $staticMap = $this->staticMapService->getImage($map, $siteUrl, $focusY);
                    if ($staticMap === null) {
                        // Do not cache the error message for a whole day – try again soon
                        $cacheCollector?->addCacheTags(new CacheTag('tx_sicosm_render_failed', self::RETRY_LIFETIME));
                    }
                }
            }
        }

        $this->view->assign('map', $map);
        $this->view->assign('staticMap', $staticMap);
        $this->view->assign('dynamicConfig', $dynamicConfig);
        $this->view->assign('marker', $marker);
        $this->view->assign('layout', [
            'focusLow' => $focusY !== self::FOCUS_CENTER,
            // Map height in px (the map is as wide as its container); CSS also limits the info box height with it
            'height' => $map?->getHeight() ?? 450,
            // Unique per map on the page: connects the pin/close labels with the toggle checkbox
            'domId' => 'sic-osm-' . ((int)($data['uid'] ?? 0) ?: $mapUid) . '-' . bin2hex(random_bytes(3)),
            'click' => $view === 'click',
            // Named in the activation hint of the two-click view: the server the visitor's browser will contact
            'tileHost' => Layer::fromSetting($layerSetting)->host(),
        ]);
        // Static images are always rendered from OSM standard tiles (tileUrl)
        $this->view->assign('staticAttributionFallback', Layer::Osm->attributionFallback());
        $this->view->assign('settings', $this->settings);
        $this->view->assign('data', $data);

        return $this->htmlResponse();
    }

    /**
     * Configuration of the interactive map, read by sic_osm.js from the data attribute.
     * The marker markup (incl. info box) is rendered by Fluid into a <template> element.
     */
    private function buildDynamicConfig(Map $map, string $layerSetting, bool $hasMarker, float $focusY): string
    {
        $layer = Layer::fromSetting($layerSetting);
        $config = [
            'lat' => $map->getLatitude(),
            'lon' => $map->getLongitude(),
            'zoom' => min($map->getZoom(), $layer->maxZoom()),
            'maxZoom' => $layer->maxZoom(),
            'tileUrl' => $layer->tileUrl(),
            'subdomains' => $layer->subdomains(),
            'attribution' => LocalizationUtility::translate($layer->attributionKey(), 'SicOsm') ?: $layer->attributionFallback(),
            'marker' => $hasMarker,
            'focusY' => $focusY,
        ];
        return json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Custom marker image or the default pin (inline SVG in the colour of the record, see Pin).
     * Both are anchored at the bottom centre (CSS transform), so the dimensions are only
     * needed for the width/height attributes.
     *
     * @return array{url?: string, svg?: string, width: int, height: int}|null
     */
    private function resolveMarker(Map $map): ?array
    {
        if (!$map->isShowMarker()) {
            return null;
        }
        $custom = $map->getMarker()?->getOriginalResource();
        if ($custom !== null) {
            return [
                'url' => (string)$custom->getPublicUrl(),
                'width' => (int)$custom->getProperty('width'),
                'height' => (int)$custom->getProperty('height'),
            ];
        }
        return [
            'svg' => Pin::svg($map->getMarkerColor()),
            'width' => Pin::WIDTH,
            'height' => Pin::HEIGHT,
        ];
    }
}
