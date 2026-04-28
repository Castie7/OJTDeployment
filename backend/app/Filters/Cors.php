<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        $corsConfig = config('Cors');
        $settings = $corsConfig->default ?? [];

        if ($request->getMethod(true) === 'OPTIONS') {
            $response = service('response');
            
            if ($this->isOriginAllowed($origin, $settings)) {
                $this->applyCorsHeaders($response, $origin, $settings);
            }
            
            $response->setStatusCode(204);
            return $response;
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        $corsConfig = config('Cors');
        $settings = $corsConfig->default ?? [];

        if ($this->isOriginAllowed($origin, $settings)) {
            $this->applyCorsHeaders($response, $origin, $settings);
        }
        
        // --- NATIVE AXIOS XSRF INJECTION ---
        // Expose a non-HttpOnly cookie for Axios to read (mirrors Laravel Sanctum pattern).
        // Wrapped in try-catch: csrf_hash() can fail on routes where CSRF isn't initialized.
        try {
            $tokenName = config('Security')->cookieName;
            $cookieValue = $_COOKIE[$tokenName] ?? csrf_hash();
            
            // CI4 setCookie signature: name, value, expire, domain, path, prefix, secure, httponly
            $response->setCookie(
                'XSRF-TOKEN',   // name
                $cookieValue,    // value
                7200,           // expire (seconds)
                '',              // domain (empty = current host)
                '/',             // path
                '',              // prefix
                false,           // secure (false for localhost)
                false            // httponly (FALSE — Axios MUST read this)
            );
        } catch (\Throwable $e) {
            // Silently skip on routes where CSRF isn't active
            log_message('debug', 'XSRF-TOKEN cookie skipped: ' . $e->getMessage());
        }
    }

    private function isOriginAllowed(string $origin, array $settings): bool
    {
        if (empty($origin)) {
            return false;
        }

        if (in_array('*', $settings['allowedOrigins'] ?? [], true)) {
            return true;
        }

        if (in_array($origin, $settings['allowedOrigins'] ?? [], true)) {
            return true;
        }

        foreach ($settings['allowedOriginsPatterns'] ?? [] as $pattern) {
            if (preg_match('#' . $pattern . '#', $origin)) {
                return true;
            }
        }

        return false;
    }

    private function applyCorsHeaders(ResponseInterface &$response, string $origin, array $settings): void
    {
        if (in_array('*', $settings['allowedOrigins'] ?? [], true)) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        } else {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        if (isset($settings['allowedMethods'])) {
            $response->setHeader('Access-Control-Allow-Methods', implode(', ', $settings['allowedMethods']));
        }

        if (isset($settings['allowedHeaders'])) {
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $settings['allowedHeaders']));
        }

        $supportsCredentials = (bool) ($settings['supportsCredentials'] ?? false);
        if ($supportsCredentials) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        if (isset($settings['maxAge'])) {
            $response->setHeader('Access-Control-Max-Age', (string) $settings['maxAge']);
        }
    }
}
