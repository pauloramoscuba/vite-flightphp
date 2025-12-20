<?php

declare(strict_types=1);

namespace app\services;

/**
 * Manifest service class for handling Vite manifest operations.
 */
class Manifest
{
    private string $baseUrl;
    private string $manifestPath;

    /**
     * @param string $baseUrl The base URL for asset paths
     * @param string $manifestPath The path to the manifest file
     */
    public function __construct(string $baseUrl, string $manifestPath)
    {
        $this->baseUrl = $baseUrl;
        $this->manifestPath = $manifestPath;
    }

    /**
     * Returns the decoded manifest as an associative array.
     * If the manifest cannot be read or parsed, returns an empty array.
     * @return array
     */
    public function getManifest(): array
    {
        if (!file_exists($this->manifestPath)) {
            return [];
        }

        $content = file_get_contents($this->manifestPath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return [];
        }
        return $data;
    }

    /**
     * Returns the URL for an entry asset
     */
    public function getEntryUrl(string $entry): string
    {
        $manifest = $this->getManifest();

        return isset($manifest[$entry]) ? $this->baseUrl . 'dist/' . $manifest[$entry]['file'] : '';
    }

    /**
     * Returns URLs for imported modules
     */
    public function getImportsUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->getManifest();

        if (!isset($manifest[$entry]['imports'])) {
            return $urls;
        }

        if (!is_array($manifest[$entry]['imports'])) {
            return $urls;
        }

        if (count($manifest[$entry]['imports']) === 0) {
            return $urls;
        }

        foreach ($manifest[$entry]['imports'] as $imports) {
            if (!isset($manifest[$imports]['file'])) {
                continue;
            }
            $urls[] = $this->baseUrl . 'dist/' . $manifest[$imports]['file'];
        }

        return $urls;
    }

    /**
     * Returns URLs for CSS files associated with an entry
     */
    public function getCssUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->getManifest();

        if (
            isset($manifest[$entry]['css'])
            && is_array($manifest[$entry]['css'])
            && count($manifest[$entry]['css']) > 0
        ) {
            foreach ($manifest[$entry]['css'] as $file) {
                $urls[] = $this->baseUrl . "dist/{$file}";
            }
        }
        return $urls;
    }
}
