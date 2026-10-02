<?php
namespace SICOR\SicOsm\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Address → coordinates via Nominatim (OpenStreetMap).
 *
 * Usage policy: identifying User-Agent, max. 1 request per second, no bulk
 * geocoding. Used on saving a single map record,
 * so the volume stays far below the limits.
 */
final class GeocodeService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_URL = 'https://nominatim.openstreetmap.org/search';

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionSettings $extensionSettings,
    ) {
    }

    /**
     * Free text address as entered by editors → single line query for Nominatim
     * ("Prinzipalmarkt 10\n48143  Münster\n" → "Prinzipalmarkt 10, 48143 Münster")
     */
    public static function normalizeAddress(string $address): string
    {
        $lines = preg_split('/\R/u', $address) ?: [];
        $lines = array_filter(array_map(
            static fn(string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? '', " \t,;"),
            $lines
        ), static fn(string $line): bool => $line !== '');
        $query = implode(', ', $lines);
        // Collapse repeated separators, e.g. "Münster,, Germany"
        return trim(preg_replace('/\s*[,;]+\s*/u', ', ', $query) ?? '', ', ');
    }

    /**
     * The address as entered, and – if it has several lines – without the first line.
     * Editors often put a company or institution name into the first line (it is shown
     * in the popup), which Nominatim usually does not know.
     *
     * @return string[] normalized queries, most specific first
     */
    public static function getQueries(string $address): array
    {
        $lines = array_values(array_filter(preg_split('/\R/u', trim($address)) ?: [], static fn(string $line): bool => trim($line) !== ''));
        $queries = [self::normalizeAddress($address)];
        if (count($lines) > 1) {
            $queries[] = self::normalizeAddress(implode("\n", array_slice($lines, 1)));
        }
        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * @param string $language Preferred language of the returned label (Accept-Language)
     * @return array{latitude: float, longitude: float, label: string}|null null if nothing was found or the request failed
     */
    public function geocode(string $address, string $language = '', string $siteUrl = ''): ?array
    {
        foreach (self::getQueries($address) as $index => $query) {
            if ($index > 0) {
                // Usage policy: max. 1 request per second
                sleep(1);
            }
            $result = $this->request($query, $language, $siteUrl);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * @return array{latitude: float, longitude: float, label: string}|null
     */
    private function request(string $query, string $language, string $siteUrl): ?array
    {
        $parameters = array_filter([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => '1',
            'email' => $this->extensionSettings->get('contactEmail'),
        ]);
        $url = $this->extensionSettings->get('nominatimUrl', self::DEFAULT_URL) . '?' . http_build_query($parameters);
        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => array_filter([
                    'User-Agent' => $this->extensionSettings->getUserAgent($siteUrl),
                    'Accept-Language' => $language,
                ]),
                'timeout' => 10,
                'http_errors' => false,
            ]);
            if ($response->getStatusCode() !== 200) {
                $this->logger?->warning('Nominatim request failed', ['query' => $query, 'status' => $response->getStatusCode()]);
                return null;
            }
            $results = json_decode((string)$response->getBody(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger?->warning('Nominatim request failed', ['query' => $query, 'exception' => $e->getMessage()]);
            return null;
        }
        $first = is_array($results) ? ($results[0] ?? null) : null;
        if (!is_array($first) || !is_numeric($first['lat'] ?? null) || !is_numeric($first['lon'] ?? null)) {
            return null;
        }
        return [
            'latitude' => (float)$first['lat'],
            'longitude' => (float)$first['lon'],
            'label' => (string)($first['display_name'] ?? ''),
        ];
    }
}
