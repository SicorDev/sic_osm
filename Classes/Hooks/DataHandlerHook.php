<?php
namespace SICOR\SicOsm\Hooks;

use SICOR\SicOsm\Service\GeocodeService;
use SICOR\SicOsm\Service\StaticMapService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * DataHandler hooks for map records:
 *  - Geocoding: address → coordinates via Nominatim before saving, if there is no position yet
 *  - Saving or deleting removes the static images and flushes the pages showing
 *    the map, so "save" doubles as "render again" – no extra checkbox needed
 */
final class DataHandlerHook
{
    private const TABLE = 'tx_sicosm_domain_model_map';
    private const LL = 'LLL:EXT:sic_osm/Resources/Private/Language/locallang_db.xlf:';

    public function __construct(
        private readonly StaticMapService $staticMapService,
        private readonly GeocodeService $geocodeService,
        private readonly CacheManager $cacheManager,
        private readonly FlashMessageService $flashMessageService,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    /**
     * Geocoding before the values are evaluated and written – only for records without a position
     * (e.g. a new map with title and address). An existing position is never overwritten on saving:
     * it may have been fine-tuned in the map preview. To determine it again from the address,
     * editors use the button "Determine coordinates from address" (AJAX, GeocodeAjaxController).
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, $table, $id, DataHandler $dataHandler): void
    {
        if ($table !== self::TABLE || !array_key_exists('address', $incomingFieldArray)) {
            return;
        }
        $existing = is_numeric($id)
            ? (BackendUtility::getRecord(self::TABLE, (int)$id, 'address,latitude,longitude') ?? [])
            : [];
        $address = (string)$incomingFieldArray['address'];
        $normalized = GeocodeService::normalizeAddress($address);

        $storedLatitude = (float)($existing['latitude'] ?? 0);
        $storedLongitude = (float)($existing['longitude'] ?? 0);
        $hasPosition = $storedLatitude !== 0.0 || $storedLongitude !== 0.0;
        $hasIncomingPosition = $this->hasIncomingPosition($incomingFieldArray);

        if (!$hasPosition && !$hasIncomingPosition) {
            if ($normalized === '') {
                $this->addMessage('message.noPosition', ContextualFeedbackSeverity::WARNING);
                return;
            }
            $result = $this->geocodeService->geocode($address, $this->getBackendLanguage(), $this->getSiteUrl());
            if ($result !== null) {
                $incomingFieldArray['latitude'] = number_format($result['latitude'], 7, '.', '');
                $incomingFieldArray['longitude'] = number_format($result['longitude'], 7, '.', '');
                $this->addMessage('message.geocode.success', ContextualFeedbackSeverity::OK, $result['label']);
            } else {
                $this->addMessage('message.geocode.notFound', ContextualFeedbackSeverity::WARNING, $normalized);
            }
            return;
        }

        // Address changed, position kept: remind the editor of the button
        $addressChanged = $normalized !== GeocodeService::normalizeAddress((string)($existing['address'] ?? ''));
        if ($hasPosition && $addressChanged && !$this->coordinatesEdited($incomingFieldArray, $storedLatitude, $storedLongitude)) {
            $this->addMessage('message.addressChanged', ContextualFeedbackSeverity::INFO);
        }
    }

    /**
     * Called after every datamap run, also when the record was saved without changes
     */
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        foreach (array_keys($dataHandler->datamap[self::TABLE] ?? []) as $id) {
            // New records ("NEW…") have no images yet
            if (is_numeric($id)) {
                $this->reset((int)$id);
            }
        }
    }

    public function processCmdmap_postProcess(string $command, string $table, $id): void
    {
        if ($table === self::TABLE && $command === 'delete') {
            $this->reset((int)$id);
        }
    }

    private function reset(int $uid): void
    {
        $this->staticMapService->removeImages($uid);
        // Flushed explicitly: DataHandler does not clear page caches for unchanged records,
        // and cached pages must never reference a deleted image
        $this->cacheManager->flushCachesInGroupByTag('pages', self::TABLE . '_' . $uid);
    }

    /**
     * The editor typed coordinates different from the stored ones in this save
     */
    private function coordinatesEdited(array $incomingFieldArray, float $storedLatitude, float $storedLongitude): bool
    {
        if (!$this->hasIncomingPosition($incomingFieldArray)) {
            return false;
        }
        $latitude = (float)str_replace(',', '.', (string)$incomingFieldArray['latitude']);
        $longitude = (float)str_replace(',', '.', (string)$incomingFieldArray['longitude']);
        return abs($latitude - $storedLatitude) > 1e-7 || abs($longitude - $storedLongitude) > 1e-7;
    }

    private function hasIncomingPosition(array $incomingFieldArray): bool
    {
        return trim((string)($incomingFieldArray['latitude'] ?? '')) !== ''
            && trim((string)($incomingFieldArray['longitude'] ?? '')) !== '';
    }

    private function addMessage(string $key, ContextualFeedbackSeverity $severity, string $argument = ''): void
    {
        $languageService = $this->getLanguageService();
        $message = new FlashMessage(
            sprintf($languageService->sL(self::LL . $key), $argument),
            $languageService->sL(self::LL . 'message.title'),
            $severity,
            true
        );
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($message);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'] ?? $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER'] ?? null);
    }

    /**
     * Language of the backend user, so Nominatim returns labels in that language
     */
    private function getBackendLanguage(): string
    {
        $language = (string)($GLOBALS['BE_USER']->user['lang'] ?? '');
        return $language === '' || $language === 'default' ? 'en' : $language;
    }

    private function getSiteUrl(): string
    {
        return (string)($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('normalizedParams')?->getSiteUrl();
    }
}
