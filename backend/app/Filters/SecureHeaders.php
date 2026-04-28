<?php namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class SecureHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Do nothing before the request
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Anti-Clickjacking
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        // Prevents MIME sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Legacy XSS filter (still useful for older browsers)
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        // Restrict browser features
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Content Security Policy — tailored for Vue SPA
        // 'unsafe-inline' is needed for Vue's scoped style injection;
        // 'unsafe-eval' is NOT included to block eval()-based attacks.
        $response->setHeader('Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self'; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data: blob:; "
            . "connect-src 'self'; "
            . "frame-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self';"
        );

        // HSTS — uncomment in production when HTTPS is enforced
        // $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}