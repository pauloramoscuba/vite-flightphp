<?php

namespace app\controllers;

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

    /**
     * @param Engine $app
     */
    public function __construct(Engine $app)
    {
        $this->app = $app;
        $this->viteHost = "http://" . $app->get('vite_host');
        $this->baseUrl = $app->get('flight.base_url');
    }

    /**
     * Prints all the html entries needed for Vite
     */
    public function entry(string $entry): string
    {
        return "\n" . $this->jsTag($entry)
            . "\n" . $this->jsPreloadImports($entry)
            . "\n" . $this->cssTag($entry);
    }

    /**
     * Some dev/prod mechanism would exist in your project
     *
     * This method checks if the Vite dev server is up by attempting
     * to reach VITE_HOST/<entry>. The result is cached for the lifetime
     * of the request (static).
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
        curl_close($handle);

        return $exists = !$error;
    }

    /**
     * Helpers to print tags
     */
    public function jsTag(string $entry): string
    {
        $url = $this->isDev($entry)
            ? "{$this->viteHost}/{$entry}"
            : $this->assetUrl($entry);

        if (!$url) {
            return '';
        }

        $nonce = $this->app->get('csp_nonce');
        if ($this->isDev($entry)) {
            return "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$this->viteHost}/@vite/client\"></script>\n"
            . '<script type="module" nonce="' . $nonce . '" src="' . $url . '"></script>';
        }
        return "<script type=\"module\" nonce=\"{$nonce}\" src=\"{$url}\"></script>";
    }

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
     * Helpers to locate files
     */

    /**
     * Returns the decoded manifest as an associative array.
     * If the manifest cannot be read or parsed, returns an empty array.
     * @return array
     */
    public function getManifest(): array
    {
        // The original helper used __DIR__ inside public/helpers.php.
        // To keep the same behavior, point to public/dist/.vite/manifest.json
        $projectRoot = dirname(__DIR__, 2); // app/controllers -> app -> project root
        $manifestPath = "{$projectRoot}/public/dist/.vite/manifest.json";

        if (!file_exists($manifestPath)) {
            return [];
        }

        $content = file_get_contents($manifestPath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return [];
        }
        return $data;
    }

    public function assetUrl(string $entry): string
    {
        $manifest = $this->getManifest();

        return isset($manifest[$entry])
            ? $this->baseUrl . 'dist/' . $manifest[$entry]['file']
            : '';
    }

    public function importsUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->getManifest();

        if (!empty($manifest[$entry]['imports'])) {
            foreach ($manifest[$entry]['imports'] as $imports) {
                if (isset($manifest[$imports]['file'])) {
                    $urls[] = $this->baseUrl . 'dist/' . $manifest[$imports]['file'];
                }
            }
        }
        return $urls;
    }

    public function cssUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->getManifest();

        if (!empty($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $file) {
                $urls[] = $this->baseUrl . "dist/{$file}";
            }
        }
        return $urls;
    }
}
