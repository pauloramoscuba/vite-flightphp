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
    private ?array $manifest = null;

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
     * Cached in memory after first read.
     *
     * @return array<string, mixed>
     */
    private function getManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!file_exists($this->manifestPath)) {
            return $this->manifest = [];
        }

        $content = file_get_contents($this->manifestPath);
        if ($content === false) {
            return $this->manifest = [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $this->manifest = [];
        }

        return $this->manifest = $data;
    }

    /**
     * Returns the URL for an entry asset.
     *
     * @param string $entry The entry name (e.g., 'main.js')
     * @return string The full URL to the entry asset, or empty string if not found
     */
    public function getEntryUrl(string $entry): string
    {
        $manifest = $this->getManifest();
        $file = $manifest[$entry]['file'] ?? null;

        return $file !== null ? $this->baseUrl . 'dist/' . $file : '';
    }

    /**
     * Returns URLs for imported modules.
     *
     * @param string $entry The entry name (e.g., 'main.js')
     * @return array<string> Array of URLs for imported modules
     */
    public function getImportsUrls(string $entry): array
    {
        $manifest = $this->getManifest();
        $imports = $manifest[$entry]['imports'] ?? null;

        if (!is_array($imports)) {
            return [];
        }

        $urls = [];
        foreach ($imports as $import) {
            $file = $manifest[$import]['file'] ?? null;
            if ($file !== null) {
                $urls[] = $this->baseUrl . 'dist/' . $file;
            }
        }

        return $urls;
    }

    /**
     * Returns URLs for CSS files associated with an entry.
     *
     * @param string $entry The entry name (e.g., 'main.js')
     * @return array<string> Array of URLs for CSS files
     */
    public function getCssUrls(string $entry): array
    {
        $manifest = $this->getManifest();
        $css = $manifest[$entry]['css'] ?? null;

        if (!is_array($css)) {
            return [];
        }

        $urls = [];
        foreach ($css as $file) {
            $urls[] = $this->baseUrl . 'dist/' . $file;
        }

        return $urls;
    }

    /**
     * Find an asset URL by its filename in the manifest.
     *
     * @param string $assetPath The asset path (e.g., '/assets/logo.png')
     * @return string|null The full URL or null if not found
     */
    public function findAsset(string $assetPath): ?string
    {
        $manifest = $this->getManifest();
        $assetName = basename($assetPath);

        foreach ($manifest as $key => $value) {
            $file = $value['file'] ?? null;
            if ($file !== null && basename($key) === $assetName) {
                return $this->baseUrl . 'dist/' . $file;
            }
        }

        return null;
    }
}
