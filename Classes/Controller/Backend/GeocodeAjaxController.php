<?php
namespace SICOR\SicOsm\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SICOR\SicOsm\Service\GeocodeService;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * AJAX endpoint of the button "Determine coordinates from address" (backend map preview).
 * Geocoding runs on the server, so Nominatim gets the identifying User-Agent and the
 * editor's IP address is not sent to a third party.
 */
final class GeocodeAjaxController
{
    public function __construct(
        private readonly GeocodeService $geocodeService,
    ) {
    }

    public function geocodeAction(ServerRequestInterface $request): ResponseInterface
    {
        $address = (string)($request->getParsedBody()['address'] ?? '');
        if (GeocodeService::normalizeAddress($address) === '') {
            return new JsonResponse(['success' => false, 'reason' => 'empty']);
        }
        $result = $this->geocodeService->geocode(
            $address,
            $this->getBackendLanguage(),
            (string)$request->getAttribute('normalizedParams')?->getSiteUrl()
        );
        if ($result === null) {
            return new JsonResponse(['success' => false, 'reason' => 'notFound', 'query' => GeocodeService::normalizeAddress($address)]);
        }
        return new JsonResponse(['success' => true] + $result);
    }

    /**
     * Language of the backend user, so Nominatim returns the label in that language
     */
    private function getBackendLanguage(): string
    {
        $language = (string)($GLOBALS['BE_USER']->user['lang'] ?? '');
        return $language === '' || $language === 'default' ? 'en' : $language;
    }
}
