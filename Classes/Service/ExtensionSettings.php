<?php
namespace SICOR\SicOsm\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Extension configuration (ext_conf_template.txt) and the identifying User-Agent
 * shared by all requests to OSM servers (tile server, Nominatim)
 */
final class ExtensionSettings
{
    private ?array $settings = null;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * Setting value; $default applies if the setting is empty or the extension is not configured yet
     */
    public function get(string $key, string $default = ''): string
    {
        $value = trim((string)($this->getAll()[$key] ?? ''));
        return $value !== '' ? $value : $default;
    }

    /**
     * Identifying User-Agent, required by the OSM tile and Nominatim usage policies
     */
    public function getUserAgent(string $siteUrl = ''): string
    {
        $details = array_filter([$siteUrl, $this->get('contactEmail')]);
        return 'TYPO3-sic_osm/' . ExtensionManagementUtility::getExtensionVersion('sic_osm')
            . ($details !== [] ? ' (+' . implode('; ', $details) . ')' : '');
    }

    private function getAll(): array
    {
        if ($this->settings === null) {
            try {
                $this->settings = (array)$this->extensionConfiguration->get('sic_osm');
            } catch (\Throwable) {
                // Not configured yet: defaults apply
                $this->settings = [];
            }
        }
        return $this->settings;
    }
}
