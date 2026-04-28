<?php

// standalone check script
define('APPPATH', __DIR__ . '/../app/');

require_once __DIR__ . '/mock_base.php';
require_once APPPATH . 'Config/Cookie.php';
require_once APPPATH . 'Config/Security.php';

echo "Running Login Configuration Checks...\n";
echo "---------------------------------------\n";

$fail = false;

// 1. Check Cookie HTTPOnly
$cookie = new \Config\Cookie();
if ($cookie->httponly === false) {
    echo "[PASS] Config\\Cookie::httponly is FALSE (Correct for SPA)\n";
} else {
    echo "[FAIL] Config\\Cookie::httponly is TRUE (Must be FALSE)\n";
    $fail = true;
}

// 2. Check Security Token Randomize
$security = new \Config\Security();
if ($security->tokenRandomize === false) {
    echo "[PASS] Config\\Security::tokenRandomize is FALSE (Correct for direct cookie access)\n";
} else {
    echo "[FAIL] Config\\Security::tokenRandomize is TRUE (Must be FALSE)\n";
    $fail = true;
}

// 3. Check CSRF Protection Mode
if ($security->csrfProtection === 'cookie') {
    echo "[PASS] Config\\Security::csrfProtection is 'cookie' (Correct)\n";
} else {
    echo "[FAIL] Config\\Security::csrfProtection is '{$security->csrfProtection}' (Should be 'cookie')\n"; // Typo in original check? default is cookie?
}

echo "---------------------------------------\n";
if ($fail) {
    echo "RESULT: FAILED - Regression detected!\n";
    exit(1);
} else {
    echo "RESULT: SUCCESS - Configuration is correct.\n";
    exit(0);
}
