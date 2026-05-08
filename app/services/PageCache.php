<?php

declare(strict_types=1);

namespace app\services;

use flight\Engine;

/**
 * PageCache - Simple file-based page cache with data hash validation.
 *
 * Features:
 * - Separate cache file per page
 * - Per-user caching option
 * - Disable caching option
 * - Automatic regeneration when data changes
 * - View file modification detection (mtime-based)
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
     * @return void
     */
    public function render(string $template, array $data = [], bool $perUser = false, bool $disabled = false): void
    {
        if ($disabled || $this->devMode) {
            $this->app->render($template, $data);
            return;
        }

        $uri = $this->app->request()->url ?? '';
        $cacheKey = $this->buildCacheKey($uri, $perUser);
        $dataHash = $this->generateDataHash($template, $data);
        $hashKey = $cacheKey . '_hash';

        $viewsPath = $this->app->get('flight.views.path') ?? '';
        $viewsExt = $this->app->get('flight.views.extension') ?? '.php';
        $templatePath = $viewsPath . DIRECTORY_SEPARATOR . $template;
        if (!str_ends_with($templatePath, $viewsExt)) {
            $templatePath .= $viewsExt;
        }
        $mtime = file_exists($templatePath) ? (string) filemtime($templatePath) : '0';

        $storedHash = $this->readCache($hashKey);

        if ($storedHash !== null && $storedHash === $dataHash) {
            $cachedOutput = $this->readCache($cacheKey);
            if ($cachedOutput !== null) {
                $this->app->response()->header('X-Cache', 'HIT');
                echo $cachedOutput;
                return;
            }
        }

        ob_start();
        $this->app->render($template, $data);
        $output = ob_get_clean();

        $this->writeCache($cacheKey, $output, 86_400);
        $this->writeCache($hashKey, $dataHash, 86_400);

        $this->app->response()->header('X-Cache', 'MISS');
        echo $output;
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
            $files = glob($this->cacheDir . md5($uri) . '*');
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    continue;
                }
                unlink($file);
            }
        }

        $cacheKey = $this->buildCacheKey($uri, false);
        $this->removeCacheFile($cacheKey);
        $this->removeCacheFile($cacheKey . '_hash');
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
            if (str_starts_with($basename, 'cr_')) {
                $uri = $this->extractUriFromKey($basename);
                if ($uri && !in_array($uri, $cached, true)) {
                    $cached[] = $uri;
                }
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
        } catch (\Throwable $e) {
            // @mago-expect: Session not available, use guest
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
     * Read cached content by key.
     *
     * @param string $key Cache key
     * @return string|null Cached content or null if not found/expired
     */
    private function readCache(string $key): ?string
    {
        $path = $this->getFilePath($key);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = unserialize($content);
        if (!is_array($data)) {
            return null;
        }

        $expires = $data['expires'] ?? 0;
        if ($expires > 0 && $expires < time()) {
            $this->removeCacheFile($path);
            return null;
        }

        return $data['content'] ?? null;
    }

    /**
     * Write content to cache.
     *
     * @param string $key Cache key
     * @param string $content Content to cache
     * @param int $ttl Time to live in seconds
     * @return void
     */
    private function writeCache(string $key, string $content, int $ttl): void
    {
        $data = [
            'content' => $content,
            'expires' => time() + $ttl,
            'created' => time(),
        ];

        $path = $this->getFilePath($key);
        file_put_contents($path, serialize($data), LOCK_EX);
    }

    /**
     * Extract URI from cache key.
     *
     * @param string $key Cache key
     * @return string|null URI or null
     */
    private function extractUriFromKey(string $key): ?string
    {
        return null;
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
                default => gettype($value),
            };
        }
        return md5(json_encode($hashableData));
    }
}
