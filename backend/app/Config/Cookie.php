<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use DateTimeInterface;

class Cookie extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Cookie Prefix
     * --------------------------------------------------------------------------
     * Set a prefix if you need to avoid collisions.
     */
    public string $prefix = '';

    /**
     * --------------------------------------------------------------------------
     * Cookie Expires Timestamp
     * --------------------------------------------------------------------------
     * Default expiration time in seconds. 0 = Session cookie (expires on close).
     */
    public int $expires = 0;

    /**
     * --------------------------------------------------------------------------
     * Cookie Path
     * --------------------------------------------------------------------------
     * Typically this is '/', but if your app is in a subfolder, you might set it there.
     * Keep as '/' to ensure it works across the entire domain.
     */
    public string $path = '/';

    /**
     * --------------------------------------------------------------------------
     * Cookie Domain
     * --------------------------------------------------------------------------
     * Leave blank to allow it on the current domain (192.168.60.56).
     */
    public string $domain = '';

    /**
     * --------------------------------------------------------------------------
     * Cookie Secure
     * --------------------------------------------------------------------------
     * 🔒 SECURED: Only allowed over HTTPS.
     * REQUIRED if SameSite is set to 'None'.
     */
    public bool $secure = false;

    /**
     * --------------------------------------------------------------------------
     * Cookie HTTPOnly
     * --------------------------------------------------------------------------
     * 🔒 SECURED: Prevents JavaScript (XSS) from accessing the cookie.
     */
    public bool $httponly = true;

    /**
     * --------------------------------------------------------------------------
     * Cookie SameSite
     * --------------------------------------------------------------------------
     * ⚠️ CHANGED: Set to 'None' to allow Cross-Port (5173 -> 443) cookie sharing.
     * 'Strict' works well for traditional apps, but 'None' is often required 
     * for decoupled frontends (Vue/React) using 'credentials: include'.
     * * Allowed values: 'None', 'Lax', 'Strict', ''.
     */
    public string $samesite = 'Lax';

    /**
     * --------------------------------------------------------------------------
     * Cookie Raw
     * --------------------------------------------------------------------------
     * false = URL encoded (Standard)
     */
    public bool $raw = false;

    public function __construct()
    {
        parent::__construct();

        $secureEnv = env('cookie.secure');
        if ($secureEnv !== null) {
            $parsed = filter_var($secureEnv, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                $this->secure = $parsed;
                return;
            }
        }

        if (ENVIRONMENT === 'production') {
            $this->secure = true;
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        if ($isHttps) {
            $this->secure = true;
        }
    }
}
