<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\Manifest;
use flight\Engine;
use flight\Session;

/**
 * Vite controller class.
 */
class Vite
{
    protected Engine $app;
    private string $viteHost;
    private string $baseUrl;
    private Session $session;
    private Manifest $manifestService;
    private bool $devMode;

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

        // Initialize manifest service
        $projectRoot = dirname(__DIR__, 2); // app/controllers -> app -> project root
        $manifestPath = "{$projectRoot}/public/dist/.vite/manifest.json";
        $this->manifestService = new Manifest($this->baseUrl, $manifestPath);

        // In production, always use dist. In dev, probe Vite once.
        $this->devMode = $app->get('production') !== true && $this->isViteRunning();

        /** @var Session $this->app->session() */
        $this->session = $this->app->session();
    }

    /**
     * Check if Vite dev server is running.
     *
     * Probes the dev server once with a short timeout (200ms).
     * If unreachable, falls back to production assets from dist/.
     *
     * @return bool True if Vite dev server is reachable
     */
    private function isViteRunning(): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $handle = curl_init($this->viteHost);
        if ($handle === false) {
            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_CONNECTTIMEOUT_MS => 200,
            CURLOPT_TIMEOUT_MS => 200,
        ]);

        curl_exec($handle);
        $error = curl_errno($handle) !== 0;

        return !$error;
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
     * Generates the JavaScript script tags for the entry.
     *
     * In development mode, includes Vite client and uses dev server URLs.
     * In production, uses manifest-optimized URLs.
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The HTML script tags
     */
    public function jsTag(string $entry): string
    {
        if ($this->devMode) {
            $nonce = $this->app->get('csp_nonce');
            return (
                "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$this->viteHost}/@vite/client\"></script>\n"
                . "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$this->viteHost}/{$entry}\"></script>"
            );
        }

        $url = $this->manifestService->getEntryUrl($entry);
        if ($url === '') {
            return '';
        }

        $nonce = $this->app->get('csp_nonce');
        return "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$url}\"></script>";
    }

    /**
     * Generates module preload link tags for imported modules.
     *
     * Only used in production. In dev, Vite handles imports.
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The HTML preload link tags
     */
    public function jsPreloadImports(string $entry): string
    {
        if ($this->devMode) {
            return '';
        }

        $res = '';
        foreach ($this->manifestService->getImportsUrls($entry) as $url) {
            $res .= "<link rel=\"modulepreload\" href=\"{$url}\">";
        }
        return $res;
    }

    /**
     * Generates CSS link tags for the entry.
     *
     * Only used in production. In dev, CSS is injected by Vite HMR.
     *
     * @param string $entry The entry file name (e.g., 'main.js')
     * @return string The CSS link tags
     */
    public function cssTag(string $entry): string
    {
        if ($this->devMode) {
            return '';
        }

        $tags = '';
        foreach ($this->manifestService->getCssUrls($entry) as $url) {
            $tags .= "<link rel=\"stylesheet\" href=\"{$url}\">";
        }
        return $tags;
    }

    /**
     * Returns the URL for an asset referenced in the manifest.
     *
     * @param string $assetPath The path to the asset (e.g., '/assets/logo.png')
     * @return string The full URL to the asset
     */
    public function asset(string $assetPath): string
    {
        if ($this->devMode) {
            return $this->viteHost . '/' . ltrim($assetPath, '/');
        }

        // In production, find asset in manifest
        $found = $this->manifestService->findAsset($assetPath);
        if ($found !== null) {
            return $found;
        }

        // Fallback path
        return $this->baseUrl . 'dist/assets/' . basename($assetPath);
    }
}
