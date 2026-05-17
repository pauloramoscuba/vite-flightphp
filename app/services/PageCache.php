<?php

declare(strict_types=1);

namespace app\services;

use flight\Engine;
use Tracy\Debugger;

/**
 * PageCache - Simple file-based page cache with data hash validation.
 *
 * Features:
 * - Single cache file per page (content + metadata + hash)
 * - Per-user caching option
 * - Disable caching option
 * - Automatic regeneration when data changes
 * - View file modification detection (mtime-based)
 * - Stampede protection via file locks
 * - Content-Type preservation on cache hits
 *
 * @mago-ignore cyclomatic-complexity, kan-defect, no-empty-catch-clause
 */
class PageCache
{
    private Engine $app;
    private string $cacheDir;
    private bool $devMode;

    /**
     * Constructor.
     *
     * @param Engine $app Flight engine instance
     * @param string $cacheDir Directory path for cache files
     */
    public function __construct(Engine $app, string $cacheDir)
    {
        $this->app = $app;
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->devMode = $app->get('production') !== true;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o755, true);
        }
    }

    /**
     * Render template with caching support.
     *
     * Cache is automatically invalidated when:
     * - Template file mtime changes
     * - Data passed to template changes
     * - TTL expires (24 hours by default)
     *
     * Caching is skipped in development mode.
     *
     * @param string $template Template name (without extension)
     * @param array<string, mixed> $data Data to pass to template
     * @param bool $perUser Create separate cache per user (uses session user_id)
     * @param bool $disabled Skip caching completely
     * @param string $contentType Response content type (default: text/html)
     * @return void
     */
    public function render(
        string $template,
        array $data = [],
        bool $perUser = false,
        bool $disabled = false,
        string $contentType = 'text/html; charset=UTF-8',
    ): void {
        if ($disabled || $this->devMode) {
            $this->app->render($template, $data);
            return;
        }

        $this->serveCached(
            $this->generateDataHash($template, $data),
            $perUser,
            $contentType,
            function () use ($template, $data) {
                ob_start();
                $this->app->render($template, $data);
                return ob_get_clean();
            },
        );
    }

    /**
     * Serve content from cache or render and cache it.
     *
     * Handles stampede protection, timing headers, and cache hit/miss logic.
     *
     * @param string $dataHash Hash for cache validation
     * @param bool $perUser Per-user caching
     * @param string $contentType Response content type
     * @param callable $renderer Callable that renders and returns the output string
     * @return void
     */
    private function serveCached(string $dataHash, bool $perUser, string $contentType, callable $renderer): void
    {
        $uri = $this->app->request()->url ?? '';
        $cacheKey = $this->buildCacheKey($uri, $perUser);

        $start = microtime(true);
        $cached = $this->readCache($cacheKey, $dataHash);

        if ($cached !== null) {
            $this->sendCachedResponse($cached, $start);
            return;
        }

        // Stampede protection: acquire lock before rendering
        $lockPath = $this->cacheDir . $cacheKey . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                flock($lock, LOCK_SH);
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            $cached = $this->readCache($cacheKey, $dataHash);
            if ($cached !== null) {
                $this->sendCachedResponse($cached, $start);
                return;
            }
        }

        $output = $renderer();

        $this->writeCache($cacheKey, $output, 86_400, $dataHash, ['uri' => $uri, 'content_type' => $contentType]);

        if ($lock !== false) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        if (file_exists($lockPath)) {
            unlink($lockPath);
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $this->app->response()->header('X-Cache', 'MISS');
        $this->app->response()->header('X-Render-Time', $elapsed . 'ms');
        echo $output;
    }

    /**
     * Send a cached response with appropriate headers.
     *
     * @param array{content: string, content_type?: string} $cached Cached data
     * @param float $start Request start time
     * @return void
     */
    private function sendCachedResponse(array $cached, float $start): void
    {
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $this->app->response()->header('X-Cache', 'HIT');
        $this->app->response()->header('X-Render-Time', $elapsed . 'ms');
        if (is_string($cached['content_type'] ?? null)) {
            $this->app->response()->header('Content-Type', $cached['content_type']);
        }
        echo $cached['content'];
    }

    /**
     * Invalidate cache for a specific URI.
     *
     * @param string $uri The URI to invalidate
     * @param bool $perUser Also invalidate user-specific cache variants
     * @return void
     */
    public function invalidate(string $uri, bool $perUser = false): void
    {
        if ($perUser) {
            $files = glob($this->cacheDir . 'cr_' . md5($uri) . '*');
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    continue;
                }
                unlink($file);
            }
        }

        $cacheKey = $this->buildCacheKey($uri, false);
        $this->removeCacheFile($cacheKey);
    }

    /**
     * Invalidate all cached pages.
     *
     * @return void
     */
    public function invalidateAll(): void
    {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            unlink($file);
        }
    }

    /**
     * List all cached page URIs.
     *
     * @return array<string> List of cached URIs
     */
    public function listCached(): array
    {
        $cached = [];
        $files = glob($this->cacheDir . '*.cache');

        foreach ($files as $file) {
            $basename = basename($file, '.cache');
            if (!str_starts_with($basename, 'cr_')) {
                continue;
            }
            $data = $this->readCacheRaw($file);
            $uri = $data['uri'] ?? null;
            if ($uri !== null && !in_array($uri, $cached, true)) {
                $cached[] = $uri;
            }
        }

        return $cached;
    }

    /**
     * Build cache key from URI and user settings.
     *
     * @param string $uri Request URI
     * @param bool $perUser Include user ID in key
     * @return string Cache key
     */
    private function buildCacheKey(string $uri, bool $perUser): string
    {
        $key = 'cr_' . md5($uri);
        if ($perUser) {
            $userId = $this->getUserId();
            $key .= '_u_' . md5($userId);
        }
        return $key;
    }

    /**
     * Get current user ID from session.
     *
     * Returns user_id for logged-in users, or guest_id for anonymous users.
     * This ensures per-user cache works for both registered and guest visitors.
     *
     * @return string User or guest ID
     */
    private function getUserId(): string
    {
        try {
            $session = $this->app->get('session');
            if ($session !== null && method_exists($session, 'get')) {
                $userId = $session->get('user_id');
                if ($userId !== null) {
                    return (string) $userId;
                }
                $guestId = $session->get('guest_id');
                if ($guestId !== null) {
                    return (string) $guestId;
                }
            }
        } catch (\Throwable) {
            // Session not available, fallback to guest ID
            Debugger::log('PageCache: session not available', Debugger::WARNING);
        }
        return 'guest';
    }

    /**
     * Get file path for a cache key.
     *
     * @param string $key Cache key
     * @return string File path
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . $key . '.cache';
    }

    /**
     * Remove a cache file if it exists.
     *
     * @param string $key Cache key
     * @return void
     */
    private function removeCacheFile(string $key): void
    {
        $path = $this->getFilePath($key);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Read and validate cached data.
     *
     * Returns the full cache data array if valid, null otherwise.
     * Automatically removes expired or stale entries.
     *
     * @param string $key Cache key
     * @param string|null $dataHash Expected data hash (null to skip validation)
     * @return array{content: string, content_type: string}|null Cache data or null
     */
    private function readCache(string $key, ?string $dataHash = null): ?array
    {
        $path = $this->getFilePath($key);
        if (!file_exists($path)) {
            return null;
        }

        $data = $this->readCacheRaw($path);
        if ($data === null) {
            return null;
        }

        $expires = $data['expires'] ?? 0;
        if ($expires > 0 && $expires < time()) {
            unlink($path);
            return null;
        }

        if ($dataHash !== null && ($data['data_hash'] ?? null) !== $dataHash) {
            unlink($path);
            return null;
        }

        return $data;
    }

    /**
     * Read raw cache data from a file path.
     *
     * @param string $path Cache file path
     * @return array<string, mixed>|null Decoded data or null
     */
    private function readCacheRaw(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $data = unserialize($content, ['allowed_classes' => false]);
        if ($data === false || !is_array($data)) {
            return null;
        }
        return $data;
    }

    /**
     * Write content to cache.
     *
     * @param string $key Cache key
     * @param string $content Content to cache
     * @param int $ttl Time to live in seconds
     * @param string $dataHash Data hash for validation
     * @param array{uri?: string, content_type?: string} $meta Optional metadata
     * @return void
     */
    private function writeCache(string $key, string $content, int $ttl, string $dataHash, array $meta = []): void
    {
        $data = [
            'content' => $content,
            'content_type' => $meta['content_type'] ?? null,
            'data_hash' => $dataHash,
            'expires' => time() + $ttl,
            'created' => time(),
            'uri' => $meta['uri'] ?? null,
        ];

        $path = $this->getFilePath($key);
        file_put_contents($path, serialize($data), LOCK_EX);
    }

    /**
     * Generate a stable hash for an object value.
     *
     * Falls back to class name when serialization fails (e.g. Closures).
     *
     * @param object $value Object to hash
     * @return string Hash string
     */
    private function hashObject(object $value): string
    {
        try {
            return md5(serialize($value));
        } catch (\Throwable) {
            return get_class($value);
        }
    }

    /**
     * Generate hash from template mtime and data.
     *
     * Includes template file modification time in hash for automatic
     * cache invalidation when the view file is modified.
     *
     * @param string $template Template name
     * @param array<string, mixed> $data Data to include in hash
     * @return string MD5 hash
     */
    private function generateDataHash(string $template, array $data): string
    {
        $viewsPath = $this->app->get('flight.views.path') ?? '';
        $viewsExt = $this->app->get('flight.views.extension') ?? '.php';
        $templatePath = $viewsPath . DIRECTORY_SEPARATOR . $template;
        if (!str_ends_with($templatePath, $viewsExt)) {
            $templatePath .= $viewsExt;
        }

        $mtime = '0';
        if (file_exists($templatePath)) {
            $mtime = (string) filemtime($templatePath);
        }

        $hashableData = ['mtime' => $mtime];
        foreach ($data as $key => $value) {
            $hashableData[$key] = match (true) {
                is_scalar($value) => $value,
                is_array($value) => md5(json_encode($value)),
                is_object($value) => $this->hashObject($value),
            };
        }
        return md5(json_encode($hashableData));
    }
}
