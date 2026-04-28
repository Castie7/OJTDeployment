<?php
/**
 * Quick diagnostic script to verify decryption capability.
 * Run: php test_decrypt.php
 */

define('WRITEPATH', __DIR__ . '/writable/');
define('FCPATH', __DIR__ . '/public/');

// Check 1: Keys exist
$privPath = WRITEPATH . 'keys/private.pem';
$pubPath = WRITEPATH . 'keys/public.pem';

echo "=== Key Diagnostics ===\n";
echo "Private key exists: " . (file_exists($privPath) ? 'YES' : 'NO') . "\n";
echo "Public key exists: " . (file_exists($pubPath) ? 'YES' : 'NO') . "\n";

if (!file_exists($privPath) || !file_exists($pubPath)) {
    echo "ERROR: Keys are missing!\n";
    exit(1);
}

// Check 2: Keys are valid
$privKey = openssl_pkey_get_private(file_get_contents($privPath));
echo "Private key valid: " . ($privKey ? 'YES' : 'NO - ' . openssl_error_string()) . "\n";

$pubKey = openssl_pkey_get_public(file_get_contents($pubPath));
echo "Public key valid: " . ($pubKey ? 'YES' : 'NO - ' . openssl_error_string()) . "\n";

if (!$privKey || !$pubKey) {
    echo "ERROR: One or both keys are invalid!\n";
    exit(1);
}

// Check 3: Key pair matches (encrypt with public, decrypt with private)
echo "\n=== Key Pair Match Test ===\n";
$testData = 'Hello, this is a test string for key verification.';
$encrypted = '';
$success = openssl_public_encrypt($testData, $encrypted, $pubKey);
echo "Public encrypt test: " . ($success ? 'OK' : 'FAILED - ' . openssl_error_string()) . "\n";

if ($success) {
    $decrypted = '';
    $decSuccess = openssl_private_decrypt($encrypted, $decrypted, $privKey);
    echo "Private decrypt test: " . ($decSuccess ? 'OK' : 'FAILED - ' . openssl_error_string()) . "\n";
    if ($decSuccess) {
        echo "Round-trip match: " . ($decrypted === $testData ? 'YES ✓' : 'NO ✗') . "\n";
    }
}

// Check 4: Sodium extension
echo "\n=== Sodium Extension ===\n";
echo "Sodium loaded: " . (extension_loaded('sodium') ? 'YES' : 'NO') . "\n";

// Check 5: Try to read header of first encrypted file
echo "\n=== Encrypted File Test ===\n";
$researchDir = WRITEPATH . 'uploads/research/';
$files = glob($researchDir . '*.pdf');
echo "Encrypted files found: " . count($files) . "\n";

if (count($files) > 0) {
    $testFile = $files[0];
    echo "Testing file: " . basename($testFile) . " (" . filesize($testFile) . " bytes)\n";
    
    $fh = fopen($testFile, 'rb');
    if ($fh) {
        // Read encrypted key length (2 bytes)
        $lenData = fread($fh, 2);
        if (strlen($lenData) === 2) {
            $keyLength = unpack('v', $lenData)[1];
            echo "Encrypted key length in file: {$keyLength} bytes\n";
            
            // RSA 2048-bit key produces 256 bytes encrypted output
            if ($keyLength < 128 || $keyLength > 512) {
                echo "WARNING: Key length {$keyLength} is unusual for RSA 2048-bit. File may be corrupted or not encrypted.\n";
                
                // Check if this might be a plain PDF (starts with %PDF)
                fseek($fh, 0);
                $header = fread($fh, 5);
                if (strpos($header, '%PDF') === 0) {
                    echo "*** FILE IS AN UNENCRYPTED PDF! ***\n";
                    echo "This means the files were never encrypted, or were uploaded before encryption was enabled.\n";
                }
            } else {
                // Read the encrypted symmetric key
                $encryptedSymKey = fread($fh, $keyLength);
                echo "Read encrypted sym key: " . strlen($encryptedSymKey) . " bytes\n";
                
                // Try to decrypt it
                $symKey = '';
                $decResult = openssl_private_decrypt($encryptedSymKey, $symKey, $privKey);
                echo "Symmetric key decrypt: " . ($decResult ? 'SUCCESS ✓' : 'FAILED ✗ - ' . openssl_error_string()) . "\n";
                
                if ($decResult) {
                    echo "Decrypted sym key length: " . strlen($symKey) . " bytes (expected 32 for XChaCha20)\n";
                    
                    // Read sodium header (24 bytes)
                    $header = fread($fh, 24);
                    echo "Sodium header length: " . strlen($header) . " bytes\n";
                    
                    // Try to initialize sodium stream
                    try {
                        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $symKey);
                        echo "Sodium stream init: SUCCESS ✓\n";
                        
                        // Try reading first chunk
                        $chunkSize = 8192 * 1024 + 17; // CHUNK_SIZE + MAC tag
                        $encChunk = fread($fh, min($chunkSize, 8192)); // Read small sample
                        if ($encChunk !== false && $encChunk !== '') {
                            [$decChunk, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $encChunk);
                            if ($decChunk !== false) {
                                echo "First chunk decrypt: SUCCESS ✓\n";
                                // Check if output looks like a PDF
                                if (strpos($decChunk, '%PDF') === 0) {
                                    echo "Decrypted content starts with %PDF: YES ✓\n";
                                } else {
                                    echo "Decrypted content does NOT start with %PDF\n";
                                    echo "First 20 bytes (hex): " . bin2hex(substr($decChunk, 0, 20)) . "\n";
                                }
                            } else {
                                echo "First chunk decrypt: FAILED ✗\n";
                            }
                        }
                        
                        sodium_memzero($symKey);
                    } catch (\Throwable $e) {
                        echo "Sodium stream error: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "\n*** KEY MISMATCH DETECTED ***\n";
                    echo "The private key on disk CANNOT decrypt the symmetric keys in the encrypted files.\n";
                    echo "This means the files were encrypted with a DIFFERENT key pair.\n";
                    echo "You need the original private key, or re-encrypt all files with new keys.\n";
                }
            }
        } else {
            echo "Cannot read file header (got " . strlen($lenData) . " bytes)\n";
        }
        fclose($fh);
    }
}

echo "\n=== DONE ===\n";
