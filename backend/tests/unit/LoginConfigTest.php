<?php

use PHPUnit\Framework\TestCase;
use Config\Cookie;
use Config\Security;

// Define basic constants if missing
if (!defined('APPPATH')) {
    define('APPPATH', realpath(__DIR__ . '/../../app') . DIRECTORY_SEPARATOR);
}
if (!defined('CI_DEBUG')) {
    define('CI_DEBUG', true);
}

/**
 * @internal
 * @group unit
 */
final class LoginConfigTest extends TestCase
{
    public function testCookieHttpOnlyIsTrue()
    {
        $config = new Cookie();
        $this->assertTrue($config->httponly, 'Cookie::httponly must be true to protect session cookies from JavaScript access.');
    }

    public function testSecurityTokenRandomizeIsFalse()
    {
        $config = new Security();
        $this->assertFalse($config->tokenRandomize, 'Security::tokenRandomize must be false to match frontend raw token usage.');
    }

    public function testCsrfProtectionIsCookie()
    {
        $config = new Security();
        $this->assertSame('cookie', $config->csrfProtection, 'Security::csrfProtection must be set to cookie.');
    }
}
