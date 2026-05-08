<?php

declare(strict_types=1);

namespace app\middlewares;

use flight\Engine;
use Tracy\Debugger;

/**
 * Sets a set of security headers (CSP, HSTS, X-Frame, etc).
 *
 * In production with page caching enabled, nonce is disabled to avoid CSP conflicts.
 * Instead, 'self' is used which works with Vite's hashed filenames in production.
 *
 * This class centralises header construction and reduces repeated calls to the response API.
 */
class SecurityHeadersMiddleware
{
    protected Engine $app;
    private string $viteHost;
    protected bool $production;

    /**
     * Constructor
     *
     * @param Engine $app The Flight engine instance
     * @return void
     */
    public function __construct(Engine $app)
    {
        $this->app = $app;
        $this->production = $this->app->get('production') === true;
        $this->viteHost = $this->app->get('vite_host');
    }

    /**
     * Flight "before" middleware entry point.
     *
     * Sets all security headers on the response before the route is executed.
     *
     * @param array $params Route parameters
     * @return void
     */
    public function before(array $params): void
    {
        $response = $this->app->response();
        foreach ($this->buildHeaders() as $name => $value) {
            $response->header($name, $value);
        }
    }

    /**
     * Build all headers to be sent.
     *
     * Constructs and returns an array of security headers including CSP, HSTS, X-Frame-Options, etc.
     *
     * @return array<string,string> Array of header-name => header-value pairs
     */
    protected function buildHeaders(): array
    {
        $headers = [];

        // Content-Security-Policy
        $headers['Content-Security-Policy'] = $this->buildCsp();

        // Common security headers
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'no-referrer-when-downgrade';
        $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        $headers['Permissions-Policy'] = 'geolocation=()';

        return $headers;
    }

    /**
     * Build a CSP string from directives.
     *
     * Constructs the Content-Security-Policy header value based on the current environment.
     * In production, nonce is disabled because page cache stores HTML with embedded scripts.
     *
     * @return string The complete CSP header value
     */
    protected function buildCsp(): string
    {
        $directives = [];

        // default
        $directives['default-src'] = ["'self'"];

        // script-src
        // In dev: nonce + unsafe-eval + strict-dynamic
        // In prod: 'self' only (strict-dynamic blocks 'self' so we don't use it)
        $scriptSrc = ["'self'"];
        if ($this->production === false) {
            $nonce = (string) $this->app->get('csp_nonce');
            if ($nonce !== '') {
                $scriptSrc[] = "'nonce-{$nonce}'";
            }
            $scriptSrc[] = "'unsafe-eval'";
            $scriptSrc[] = "'strict-dynamic'";
        }
        $directives['script-src'] = $scriptSrc;

        // script-src-elem: in dev allow nonce, in prod only self
        if ($this->production === false) {
            $directives['script-src-elem'] = $scriptSrc;
        } else {
            $directives['script-src-elem'] = ["'self'"];
        }

        // style-src
        $styleSrc = ["'self'"];
        if (Debugger::$showBar === true) {
            $styleSrc[] = "'unsafe-inline'";
        } elseif ($this->production === false) {
            $nonce = (string) $this->app->get('csp_nonce');
            if ($nonce !== '') {
                $styleSrc[] = "'nonce-{$nonce}'";
            }
        }
        $directives['style-src'] = $styleSrc;

        // img-src: allow data URIs; in dev allow Vite origin for HMR
        $imgSrc = ["'self'", 'data:'];
        if ($this->production === false) {
            $imgSrc[] = "http://{$this->viteHost}";
        }
        $directives['img-src'] = $imgSrc;

        // connect-src: in dev include Vite HMR/ws endpoints
        $connectSrc = ["'self'"];
        if ($this->production === false) {
            $connectSrc[] = "ws://{$this->viteHost}";
            $connectSrc[] = "http://{$this->viteHost}";
        }
        $directives['connect-src'] = $connectSrc;

        // Build the final CSP string
        $parts = [];
        foreach ($directives as $name => $values) {
            $values = array_filter(array_map('trim', $values), static fn($v) => $v !== '');
            if (count($values) === 0) {
                continue;
            }
            $parts[] = $name . ' ' . implode(' ', $values);
        }

        return implode('; ', $parts) . ';';
    }
}
