<?php
// Fix paths for running from project root
require_once 'backend/vendor/autoload.php';
define('WRITEPATH', __DIR__ . '/backend/writable/');

// Mocking some CI4 constants/services if needed
if (!defined('ROOTPATH')) define('ROOTPATH', __DIR__ . '/backend/');

try {
    $service = new \App\Services\EncryptionService();
    
    echo "Checking keys at: " . WRITEPATH . "keys/\n";
    if (!$service->keysExist()) {
        echo "Keys NOT found via service.\n";
    } else {
        echo "Keys found!\n";
    }

    $testSource = WRITEPATH . 'test_source.txt';
    $testDest = WRITEPATH . 'test_dest.enc';
    
    file_put_contents($testSource, 'Hello World Encryption Test');
    
    echo "Encrypting...\n";
    $service->encryptFile($testSource, $testDest);
    echo "Success!\n";
    
    @unlink($testSource);
    @unlink($testDest);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
