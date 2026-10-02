<?php
namespace SICOR\SicOsm\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use SICOR\SicOsm\Domain\Model\Map;
use SICOR\SicOsm\Enum\Layer;
use SICOR\SicOsm\Service\Pin;
use SICOR\SicOsm\Service\StaticMapService;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Preview of the content element "OpenStreetMap" in the page module: the static map
 * as thumbnail with the pin, plus map record, view and layer.
 *
 * An event listener instead of a TCA previewRenderer: only the body of the element is replaced,
 * header and footer stay the TYPO3 standard.
 */
#[AsEventListener(identifier: 'sic-osm/page-content-preview')]
final class PageContentPreview
{
    private const CTYPE = 'sicosm_map';
    private const MAP_TABLE = 'tx_sicosm_domain_model_map';
    private const TEMPLATE = 'EXT:sic_osm/Resources/Private/Templates/Backend/Preview.html';
    private const FOCUS_CENTER = 0.5;
    private const FOCUS_POPUP = 0.7;

    public function __construct(
        private readonly StaticMapService $staticMapService,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly FileRepository $fileRepository,
        private readonly ConfigurationManagerInterface $configurationManager,
    ) {
    }

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== 'tt_content' || $event->getRecordType() !== self::CTYPE) {
            return;
        }
        // The raw row of the record holds the FlexForm XML
        $row = $event->getRecord()->getRawRecord()?->toArray() ?? [];
        $settings = $this->getFlexFormSettings($row['pi_flexform'] ?? '');
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        $view = $this->viewFactory->create(new ViewFactoryData(
            templatePathAndFilename: self::TEMPLATE,
            request: $request instanceof ServerRequestInterface ? $request : null,
        ));
        $view->assignMultiple($this->getVariables($settings, $request instanceof ServerRequestInterface ? $request : null));
        $event->setPreviewContent($view->render());
    }

    private function getVariables(array $settings, ?ServerRequestInterface $request): array
    {
        $mapUid = (int)($settings['settings.map'] ?? 0);
        $viewSetting = in_array($settings['settings.view'] ?? '', ['dynamic', 'click'], true) ? $settings['settings.view'] : 'static';
        $variables = [
            'viewSetting' => $viewSetting,
            'layerLabel' => $viewSetting !== 'static'
                ? 'flexform.layer.' . Layer::fromSetting((string)($settings['settings.layer'] ?? ''))->value
                : '',
            'typoScriptMissing' => $request !== null && $this->isTypoScriptMissing($request),
        ];
        if ($mapUid <= 0) {
            return ['error' => 'preview.noMap'] + $variables;
        }
        // Also hidden records: the editor should see which map is selected
        $row = BackendUtility::getRecord(self::MAP_TABLE, $mapUid);
        if ($row === null) {
            return ['error' => 'preview.notFound'] + $variables;
        }
        $map = Map::fromRow($row);
        $variables['map'] = $map;
        $variables['hidden'] = (bool)($row['hidden'] ?? false);
        $variables['editUrl'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [self::MAP_TABLE => [$mapUid => 'edit']],
            'returnUrl' => $request?->getAttribute('normalizedParams')?->getRequestUri() ?? '',
        ]);
        if (!$map->hasPosition()) {
            return ['error' => 'preview.noPosition'] + $variables;
        }

        // Same image as in the frontend (same hash) – rendered once if it does not exist yet
        $focusY = $map->hasPopup() ? self::FOCUS_POPUP : self::FOCUS_CENTER;
        $siteUrl = (string)$request?->getAttribute('normalizedParams')?->getSiteUrl();
        $image = $this->staticMapService->getImage($map, $siteUrl, $focusY);
        if ($image === null) {
            return ['error' => 'preview.renderError'] + $variables;
        }
        $variables['image'] = $image;
        $variables['focusLow'] = $focusY !== self::FOCUS_CENTER;
        $variables['marker'] = $this->getMarker($map);
        return $variables;
    }

    /**
     * The static TypoScript "OpenStreetMap" is not active on the current page (sys_template, site set or
     * @import): the map then works with defaults, but without lib.sicOsm.map and the TypoScript settings.
     *
     * The frontend TypoScript of the page shown in the page module (request parameter "id") is built the
     * same way Extbase does it for backend modules – cached per page. Extbase marks this API internal,
     * so any failure only hides the hint and never the preview.
     */
    private function isTypoScriptMissing(ServerRequestInterface $request): bool
    {
        try {
            $this->configurationManager->setRequest($request);
            $setup = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        } catch (\Throwable) {
            return false;
        }
        // Set by Configuration/TypoScript/setup.typoscript
        return !isset($setup['plugin.']['tx_sicosm.']['settings.']['includeDefaultCss']);
    }

    /**
     * Pin in the colour of the record, or the custom marker image
     *
     * @return array{svg?: string, url?: string}|null
     */
    private function getMarker(Map $map): ?array
    {
        if (!$map->isShowMarker()) {
            return null;
        }
        $references = $this->fileRepository->findByRelation(self::MAP_TABLE, 'marker', (int)$map->getUid());
        if ($references !== []) {
            return ['url' => (string)$references[0]->getPublicUrl()];
        }
        return ['svg' => Pin::svg($map->getMarkerColor(), 'sic-osm-preview__pin')];
    }

    /**
     * Flat FlexForm values (sheet sDEF, language lDEF): ['settings.map' => '3', …]
     */
    private function getFlexFormSettings(mixed $flexForm): array
    {
        if (!is_string($flexForm) || $flexForm === '') {
            return [];
        }
        $data = GeneralUtility::xml2array($flexForm);
        $fields = is_array($data) ? ($data['data']['sDEF']['lDEF'] ?? []) : [];
        $settings = [];
        foreach ($fields as $name => $value) {
            $settings[$name] = is_array($value) ? (string)($value['vDEF'] ?? '') : '';
        }
        return $settings;
    }
}
