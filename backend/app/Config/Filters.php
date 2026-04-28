<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use App\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'throttler'     => \App\Filters\Throttler::class,
        'cors'          => \App\Filters\Cors::class, 
        'auth'          => \App\Filters\AuthFilter::class,
    ];

    public array $required = [
        'before' => [
            'pagecache',  
        ],
        'after' => [
            'pagecache', 
            'performance', 
            'toolbar',   
        ],
    ];

    public array $globals = [
        'before' => [
            'cors', 
            'csrf' => ['except' => [
                'auth/verify', // 🔓 Bootstrap endpoint: seeds the initial CSRF cookie
            ]], 
            'invalidchars', 
        ],
        'after' => [
            'cors',
            'secureheaders',
        ],
    ];

    public array $methods = [];
    public array $filters = [
        'throttler' => ['before' => ['api/*', 'research/*']], 
    ];
}
