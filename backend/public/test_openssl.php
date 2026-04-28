<?php
header('Content-Type: text/plain');
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP Binary: " . PHP_BINARY . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n";
echo "Loaded php.ini: " . php_ini_loaded_file() . "\n";
echo "Extension Dir: " . ini_get('extension_dir') . "\n";
echo "enable_dl: " . ini_get('enable_dl') . "\n";
echo "dl() exists: " . (function_exists('dl') ? 'YES' : 'NO') . "\n";
echo "proc_open() exists: " . (function_exists('proc_open') ? 'YES' : 'NO') . "\n";
echo "shell_exec() exists: " . (function_exists('shell_exec') ? 'YES' : 'NO') . "\n";
echo "PATH: " . getenv('PATH') . "\n";
echo "----------------------------------------\n";
echo "OpenSSL loaded: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "\n";
echo "Sodium loaded: " . (extension_loaded('sodium') ? 'YES' : 'NO') . "\n";
echo "----------------------------------------\n";
echo "Loaded Extensions:\n";
print_r(get_loaded_extensions());

if (isset($_GET['crypto_test']) && $_GET['crypto_test'] === '1') {
    echo "\n----------------------------------------\n";
    echo "Web Encryption Fallback Test:\n";

    require_once dirname(__DIR__) . '/vendor/autoload.php';

    if (!defined('WRITEPATH')) {
        define('WRITEPATH', dirname(__DIR__) . '/writable/');
    }

    $sourcePath = WRITEPATH . 'crypto_test_source.txt';
    $destPath = WRITEPATH . 'crypto_test_output.bin';

    try {
        file_put_contents($sourcePath, "CLI fallback encryption test\n");
        $service = new \App\Services\EncryptionService();
        $service->encryptFile($sourcePath, $destPath);
        echo "Fallback encryption: SUCCESS\n";
        echo "Output exists: " . (is_file($destPath) ? 'YES' : 'NO') . "\n";
        echo "Output size: " . (is_file($destPath) ? filesize($destPath) : 0) . "\n";
    } catch (Throwable $e) {
        echo "Fallback encryption: FAILED\n";
        echo $e->getMessage() . "\n";
    } finally {
        if (is_file($sourcePath)) {
            @unlink($sourcePath);
        }
        if (is_file($destPath)) {
            @unlink($destPath);
        }
    }
}
