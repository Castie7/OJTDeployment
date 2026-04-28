<?php
require_once 'backend/vendor/autoload.php';
define('FCPATH', __DIR__ . '/backend/public/');
define('WRITEPATH', __DIR__ . '/backend/writable/');
define('ROOTPATH', __DIR__ . '/backend/');
define('APPPATH', __DIR__ . '/backend/app/');

// Simple test for EncryptionService
try {
    $service = new \App\Services\EncryptionService();
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
