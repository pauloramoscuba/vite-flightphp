<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\Manifest;
use flight\Engine;

/**
 * Vite controller class.
 *
 */
class Vite
{
    protected Engine $app;
    private string $viteHost;
    private string $baseUrl;
    private object $session;
    private Manifest $manifestService;

    /**
     * Constructor
     *
     * @param Engine $app The Flight engine instance
     * @return void
     */
    public function __construct(Engine $app)
    {
        $this->app = $app;
        $this->viteHost = 'http://' . $app->get('vite_host');
        $this->baseUrl = $app->get('flight.base_url');
        /** @var \flight\Session $this->app->session() */
        $this->session = $this->app->session();

        // Initialize manifest service
        $projectRoot = dirname(__DIR__, 2); // app/controllers -> app -> project root
        $manifestPath = "{$projectRoot}/public/dist/.vite/manifest.json";
        $this->manifestService = new Manifest($this->baseUrl, $manifestPath);
    }

    /**
     * Prints all the html entries needed for Vite
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The HTML tags for Vite entries
     */
    public function entry(string $entry): string
    {
        $this->session->set('vite_entry', $entry);
        return $this->jsTag($entry) . "\n" . $this->jsPreloadImports($entry) . "\n" . $this->cssTag($entry);
    }

    /**
     * Some dev/prod mechanism would exist in your project
     *
     * This method checks if the Vite dev server is up by attempting
     * to reach VITE_HOST/<entry>. The result is cached for the lifetime
     * of the request (static).
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return bool True if in development mode, false otherwise
     */
    public function isDev(string $entry): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        if (!function_exists('curl_init')) {
            // If cURL is not available, assume production.
            return $exists = false;
        }

        $handle = curl_init("{$this->viteHost}/{$entry}");
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_NOBODY, true);

        curl_exec($handle);
        $error = curl_errno($handle);

        return $exists = !$error;
    }

    /**
     * Helpers to print tags
     *
     * Generates the JavaScript script tags for the entry
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The HTML script tags
     */
    public function jsTag(string $entry): string
    {
        $url = $this->isDev($entry) ? "{$this->viteHost}/{$entry}" : $this->assetUrl($entry);

        if (!$url) {
            return '';
        }

        $nonce = $this->app->get('csp_nonce');
        if ($this->isDev($entry)) {
            return (
                "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$this->viteHost}/@vite/client\"></script>\n"
                . '<script type="module" nonce="'
                . $nonce
                . '" src="'
                . $url
                . '"></script>'
            );
        }

        return "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$url}\"></script>";
    }

    /**
     * Generates module preload link tags for imported modules
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The HTML preload link tags
     */
    public function jsPreloadImports(string $entry): string
    {
        if ($this->isDev($entry)) {
            return '';
        }

        $res = '';
        foreach ($this->importsUrls($entry) as $url) {
            $res .= "<link rel=\"modulepreload\" href=\"{$url}\">";
        }
        return $res;
    }

    /**
     * Generates CSS link tags for the entry
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The CSS link tags
     */
    public function cssTag(string $entry): string
    {
        // not needed on dev, it's injected by Vite
        if ($this->isDev($entry)) {
            return '';
        }

        $tags = '';
        foreach ($this->cssUrls($entry) as $url) {
            $tags .= "<link rel=\"stylesheet\" href=\"{$url}\">";
        }
        return $tags;
    }

    /**
     * Returns the URL for an asset (like image) referenced in the manifest
     *
     * @param string $assetPath The path to the asset (e.g., '/assets/logo.png')
     * @return string The full URL to the asset
     */
    public function asset(string $assetPath): string
    {
        $entry = $this->session->get('vite_entry') ?? 'main.js';
        if ($this->isDev($entry)) {
            $assetPath = ltrim($assetPath, '/');
            // In development, assets are served from the /src directory
            return "{$this->viteHost}/{$assetPath}";
        }

        // In production, find asset in manifest
        $manifest = $this->manifestService->getManifest();
        // Look for the asset in the manifest
        foreach ($manifest as $key => $value) {
            if (!isset($value['file'])) {
                continue;
            }

            if (basename($key) !== basename($assetPath)) {
                continue;
            }

            return $this->baseUrl . 'dist/' . $value['file'];
        }

        // If not found in manifest, return a fallback path
        return $this->baseUrl . 'dist/assets/' . basename($assetPath);
    }

    /**
     * Get the URL for an entry asset
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The full URL to the entry asset
     */
    public function assetUrl(string $entry): string
    {
        return $this->manifestService->getEntryUrl($entry);
    }

    /**
     * Get URLs for imported modules
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return array<string> Array of URLs for imported modules
     */
    public function importsUrls(string $entry): array
    {
        return $this->manifestService->getImportsUrls($entry);
    }

    /**
     * Get URLs for CSS files associated with an entry
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return array<string> Array of URLs for CSS files
     */
    public function cssUrls(string $entry): array
    {
        return $this->manifestService->getCssUrls($entry);
    }
}
