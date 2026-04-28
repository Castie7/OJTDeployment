<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * CSRF Protection Method
     * --------------------------------------------------------------------------
     * Protection Method for Cross Site Request Forgery protection.
     *
     * @var string 'cookie' or 'session'
     */
    public string $csrfProtection = 'cookie';

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Randomization
     * --------------------------------------------------------------------------
     * Randomize the CSRF Token for added security.
     */
    public bool $tokenRandomize = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Name
     * --------------------------------------------------------------------------
     * Token name for Cross Site Request Forgery protection.
     */
    public string $tokenName = 'csrf_test_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Header Name
     * --------------------------------------------------------------------------
     * Header name for Cross Site Request Forgery protection.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * --------------------------------------------------------------------------
     * CSRF Cookie Name
     * --------------------------------------------------------------------------
     * Cookie name for Cross Site Request Forgery protection.
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Expires
     * --------------------------------------------------------------------------
     * Expiration time for Cross Site Request Forgery protection cookie.
     * Defaults to two hours (in seconds).
     */
    public int $expires = 7200;

    /**
     * --------------------------------------------------------------------------
     * CSRF Regenerate
     * --------------------------------------------------------------------------
     * ⚠️ FIXED: Must be FALSE for Single Page Apps (Vue/React).
     * If TRUE, the token changes between the 'verify' call and the 'login' call,
     * causing the login to fail.
     */
    public bool $regenerate = false; 

    /**
     * --------------------------------------------------------------------------
     * CSRF Redirect
     * --------------------------------------------------------------------------
     * ⚠️ FIXED: Set to FALSE to return a 403 JSON error instead of an HTML redirect.
     */
    public bool $redirect = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF SameSite
     * --------------------------------------------------------------------------
     * ⚠️ ADDED: Allows cookie sharing across ports (5173 -> 443).
     */
    public string $samesite = 'Lax';

    /**
     * --------------------------------------------------------------------------
     * CSRF HTTPOnly
     * --------------------------------------------------------------------------
     * ⚠️ ADDED: Must be FALSE so JavaScript (api.ts) can read the cookie
     * and send it in the header.
     */
    public bool $httponly = false;
}