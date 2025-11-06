<?php

declare(strict_types=1);

namespace app\middlewares;

use flight\Engine;
use Tracy\Debugger;

/**
 * Sets a set of security headers (CSP, HSTS, X-Frame, etc).
 *
 * The CSP is built dynamically using:
 * - a nonce provided by the application (`csp_nonce`)
 * - whether the app is in production (`production`)
 * - Tracy debug bar presence (to allow inline styles when the bar is present)
 *
 * This class centralises header construction and reduces repeated calls to the response API.
 */
class SecurityHeadersMiddleware
{
    protected Engine $app;
    private string $viteHost;
    protected bool $production;

    /**
     *
     * @param Engine $app
     */
    public function __construct(Engine $app)
    {
        $this->app = $app;
        $this->production = (bool) $this->app->get('production');
        $this->viteHost = $this->app->get('vite_host');
    }

    /**
     * Flight "before" middleware entry point.
     *
     * @param array $params
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
     * @return array<string,string> header-name => header-value
     */
    protected function buildHeaders(): array
    {
        $nonce = (string) $this->app->get('csp_nonce');

        $headers = [];

        // Content-Security-Policy
        $headers['Content-Security-Policy'] = $this->buildCsp($nonce);

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
     * @param string $nonce
     * @return string
     */
    protected function buildCsp(string $nonce): string
    {
        $directives = [];

        // default
        $directives['default-src'] = ["'self'"];

        // script-src
        $scriptSrc = ["'self'"];
        if ($nonce !== '') {
            $scriptSrc[] = "'nonce-{$nonce}'";
        }
        // allow strict-dynamic which requires nonce-based scripts
        $scriptSrc[] = "'strict-dynamic'";
        // only allow unsafe-eval in non-production (helps dev tools / source maps)
        if ($this->production === false) {
            $scriptSrc[] = "'unsafe-eval'";
        }
        $directives['script-src'] = $scriptSrc;

        // style-src: normally use nonce, but allow Tracy debug bar to use inline styles in development
        if (Debugger::$showBar === true) {
            $directives['style-src'] = ["'self'", "'unsafe-inline'"];
        } else {
            $styleSrc = ["'self'"];
            if ($nonce !== '') {
                $styleSrc[] = "'nonce-{$nonce}'";
            }
            $directives['style-src'] = $styleSrc;
        }

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

        // Build the final CSP string by joining directive values with spaces and directives with semicolons.
        $parts = [];
        foreach ($directives as $name => $values) {
            // Flatten and trim values, ignore empty arrays
            $values = array_filter(array_map('trim', $values), static fn($v) => $v !== '');
            if (count($values) === 0) {
                continue;
            }
            $parts[] = $name . ' ' . implode(' ', $values);
        }

        return implode('; ', $parts) . ';';
    }
}
