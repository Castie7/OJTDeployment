<?php

namespace App\Services;

use CodeIgniter\Config\Services;
use RuntimeException;

class EncryptionService
{
    private string $privateKeyPath;
    private string $publicKeyPath;

    // We use an 8MB chunk size for reading the unencrypted data
    private const CHUNK_SIZE = 8192 * 1024;
    private const PROCESS_IO_CHUNK_SIZE = 8192;
    private const CLI_HELPER_ENV = 'OJT2_ENCRYPTION_HELPER';

    public function __construct()
    {
        $this->privateKeyPath = WRITEPATH . 'keys/private.pem';
        $this->publicKeyPath = WRITEPATH . 'keys/public.pem';
    }

    public function keysExist(): bool
    {
        return file_exists($this->privateKeyPath) && file_exists($this->publicKeyPath);
    }

    private function getMissingCryptoRuntimes(): array
    {
        $missing = [];

        if (
            !\function_exists('openssl_pkey_get_public')
            || !\function_exists('openssl_public_encrypt')
            || !\function_exists('openssl_pkey_get_private')
            || !\function_exists('openssl_private_decrypt')
        ) {
            $missing[] = 'OpenSSL';
        }

        if (
            !\function_exists('sodium_crypto_secretstream_xchacha20poly1305_keygen')
            || !\function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')
            || !\function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_pull')
        ) {
            $missing[] = 'Sodium';
        }

        return $missing;
    }

    private function hasNativeCryptoRuntime(): bool
    {
        return $this->getMissingCryptoRuntimes() === [];
    }

    private function isCliHelperInvocation(): bool
    {
        return (string) getenv(self::CLI_HELPER_ENV) === '1';
    }

    private function getHelperScriptPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'encryption_helper.php';
    }

    private function getCliPhpBinary(): ?string
    {
        $binaryDir = dirname(PHP_BINARY);
        $candidates = [
            $binaryDir . DIRECTORY_SEPARATOR . 'php.exe',
            $binaryDir . DIRECTORY_SEPARATOR . 'php-cgi.exe',
            PHP_BINARY,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function canUseCliCryptoFallback(): bool
    {
        if ($this->hasNativeCryptoRuntime() || $this->isCliHelperInvocation()) {
            return false;
        }

        if (!\function_exists('proc_open')) {
            return false;
        }

        return $this->getCliPhpBinary() !== null && is_file($this->getHelperScriptPath());
    }

    private function ensureCryptoRuntimeAvailable(): void
    {
        if ($this->hasNativeCryptoRuntime() || $this->canUseCliCryptoFallback()) {
            return;
        }

        $missing = $this->getMissingCryptoRuntimes();

        if (\function_exists('log_message')) {
            log_message(
                'critical',
                sprintf(
                    '[EncryptionService] Missing PHP crypto runtime support: %s | binary=%s | sapi=%s | ini=%s',
                    implode(', ', $missing),
                    \defined('PHP_BINARY') ? PHP_BINARY : 'unknown',
                    PHP_SAPI,
                    \php_ini_loaded_file() ?: 'none'
                )
            );
        }

        $label = implode(' and ', $missing);
        $suffix = count($missing) > 1 ? 'extensions' : 'extension';
        throw new RuntimeException(
            "Server crypto support is unavailable. Enable the PHP {$label} {$suffix} for the IIS/FastCGI runtime and restart IIS."
        );
    }

    private function startCliHelperProcess(array $arguments)
    {
        $phpBinary = $this->getCliPhpBinary();
        $helperScript = $this->getHelperScriptPath();

        if ($phpBinary === null || !is_file($helperScript)) {
            throw new RuntimeException('CLI encryption helper is unavailable.');
        }

        $command = array_merge([$phpBinary, $helperScript], $arguments);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $_ENV;
        $env[self::CLI_HELPER_ENV] = '1';

        $process = \proc_open(
            $command,
            $descriptors,
            $pipes,
            dirname($helperScript),
            $env,
            ['bypass_shell' => true]
        );

        if (!\is_resource($process)) {
            throw new RuntimeException('Failed to start the CLI encryption helper.');
        }

        return [$process, $pipes];
    }

    private function closeProcessPipes(array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }

    private function runCliEncrypt(string $sourcePath, string $destPath): void
    {
        [$process, $pipes] = $this->startCliHelperProcess(['encrypt', $sourcePath, $destPath]);
        fclose($pipes[0]);

        try {
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], self::PROCESS_IO_CHUNK_SIZE);
                if ($chunk === false || $chunk === '') {
                    break;
                }
            }

            $stderr = stream_get_contents($pipes[2]);
        } finally {
            $this->closeProcessPipes($pipes);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $message = trim((string) $stderr);
            throw new RuntimeException(
                'CLI encryption helper failed.' . ($message !== '' ? ' ' . $message : '')
            );
        }
    }

    private function runCliDecryptToOutput(string $encryptedFilePath): void
    {
        [$process, $pipes] = $this->startCliHelperProcess(['decrypt-stdout', $encryptedFilePath]);
        fclose($pipes[0]);

        try {
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], self::PROCESS_IO_CHUNK_SIZE);
                if ($chunk === false) {
                    break;
                }

                if ($chunk === '') {
                    usleep(1000);
                    continue;
                }

                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            $stderr = stream_get_contents($pipes[2]);
        } finally {
            $this->closeProcessPipes($pipes);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $message = trim((string) $stderr);
            throw new RuntimeException(
                'CLI decryption helper failed.' . ($message !== '' ? ' ' . $message : '')
            );
        }
    }

    private function encryptSymmetricKey(string $symKey, $publicKey): string
    {
        $encryptedSymKey = '';
        if (\openssl_public_encrypt($symKey, $encryptedSymKey, $publicKey)) {
            return $encryptedSymKey;
        }

        $primaryError = \openssl_error_string() ?: 'Unknown OpenSSL error.';
        $details = \openssl_pkey_get_details($publicKey);
        $keyBytes = isset($details['bits']) ? (int) ceil(((int) $details['bits']) / 8) : 0;

        if ($keyBytes > 0 && strlen($symKey) <= $keyBytes) {
            $paddedKey = str_pad($symKey, $keyBytes, "\0", STR_PAD_LEFT);
            if (\openssl_public_encrypt($paddedKey, $encryptedSymKey, $publicKey, OPENSSL_NO_PADDING)) {
                return $encryptedSymKey;
            }
        }

        $secondaryError = \openssl_error_string();
        throw new RuntimeException(
            'Failed to encrypt symmetric key: ' . trim($primaryError . ' ' . ($secondaryError ?: ''))
        );
    }

    private function decryptSymmetricKey(string $encryptedSymKey, $privateKey): string
    {
        $symKey = '';
        if (\openssl_private_decrypt($encryptedSymKey, $symKey, $privateKey)) {
            if (strlen($symKey) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
                return $symKey;
            }
        }

        $rawDecrypted = '';
        if (\openssl_private_decrypt($encryptedSymKey, $rawDecrypted, $privateKey, OPENSSL_NO_PADDING)) {
            $symKey = substr($rawDecrypted, -SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
            if (strlen($symKey) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
                return $symKey;
            }
        }

        throw new RuntimeException('Decryption failed. Unrecognized key or corrupted file.');
    }

    /**
     * Encrypts a source file to a destination file using Envelope Encryption.
     * Uses Sodium's streaming (XChaCha20-Poly1305) for strict memory efficiency
     * and RSA to securely lock the symmetric key.
     */
    public function encryptFile(string $sourcePath, string $destPath): void
    {
        $this->ensureCryptoRuntimeAvailable();

        if (!$this->hasNativeCryptoRuntime()) {
            $this->runCliEncrypt($sourcePath, $destPath);
            return;
        }

        if (!$this->keysExist()) {
            throw new RuntimeException("Encryption keys not found. Run 'php spark keys:generate' first.");
        }

        $publicKey = \openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
        if (!$publicKey) {
            throw new RuntimeException("Invalid public key.");
        }

        if (!file_exists($sourcePath)) {
            throw new RuntimeException("Source file does not exist: {$sourcePath}");
        }

        // Generate strong symmetric key for Sodium streaming
        $symKey = \sodium_crypto_secretstream_xchacha20poly1305_keygen();

        // Encrypt the symmetric key with the RSA Public Key
        $encryptedSymKey = $this->encryptSymmetricKey($symKey, $publicKey);

        $sourceFile = fopen($sourcePath, 'rb');
        $destFile = fopen($destPath, 'wb');
        if (!$sourceFile || !$destFile) {
            if ($sourceFile) fclose($sourceFile);
            if ($destFile) fclose($destFile);
            throw new RuntimeException("Failed to open file handles for encryption.");
        }

        try {
            // Write Header: [2 bytes: Encrypted Key Length] [Encrypted Symmetric Key]
            $keyLength = strlen($encryptedSymKey);
            fwrite($destFile, pack('v', $keyLength)); // 16-bit little endian
            fwrite($destFile, $encryptedSymKey);

            // Initialize Sodium Streaming Crypto
            [$state, $header] = \sodium_crypto_secretstream_xchacha20poly1305_init_push($symKey);
            // Write sodium header (24 bytes)
            fwrite($destFile, $header);

            // Stream and encrypt file in chunks
            while (!feof($sourceFile)) {
                $chunk = fread($sourceFile, self::CHUNK_SIZE);
                if ($chunk === false) break;
                if ($chunk === '') continue; // skip empty reads
                
                $isFinal = feof($sourceFile);
                $tag = $isFinal ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0;
                
                $encryptedChunk = \sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($destFile, $encryptedChunk);
            }
        } finally {
            fclose($sourceFile);
            fclose($destFile);
            
            // Explicitly destroy sensitive key variables
            \sodium_memzero($symKey);
        }
    }

    /**
     * Streams an encrypted file directly to PHP output (HTTP Response body), decrypting inline.
     * Prevents loading massive files entirely into memory.
     */
    public function streamDecryptToOutput(string $encryptedFilePath): void
    {
        $this->ensureCryptoRuntimeAvailable();

        if (!$this->hasNativeCryptoRuntime()) {
            $this->runCliDecryptToOutput($encryptedFilePath);
            return;
        }

        if (!$this->keysExist()) {
            throw new RuntimeException("Encryption keys not found.");
        }

        $privateKey = \openssl_pkey_get_private(file_get_contents($this->privateKeyPath));
        if (!$privateKey) {
            throw new RuntimeException("Invalid private key.");
        }

        if (!file_exists($encryptedFilePath)) {
            throw new RuntimeException("Encrypted file not found.");
        }

        $file = fopen($encryptedFilePath, 'rb');
        if (!$file) {
            throw new RuntimeException("Failed to open encrypted file.");
        }

        try {
            // Read Encrypted Key Length (2 bytes)
            $lenData = fread($file, 2);
            if (strlen($lenData) !== 2) throw new RuntimeException("Invalid file format.");
            $keyLength = unpack('v', $lenData)[1];

            // Read Encrypted Symmetric Key
            $encryptedSymKey = fread($file, $keyLength);
            
            // Decrypt Symmetric Key using RSA Private Key
            $symKey = $this->decryptSymmetricKey($encryptedSymKey, $privateKey);

            // Read Sodium Header (24 bytes)
            $header = fread($file, 24);
            
            // Initialize Sodium Streaming Crypto for Pull (Decryption)
            $state = \sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $symKey);
            
            // Erase the decrypted symmetric key from memory since state is built
            \sodium_memzero($symKey);

            // Output Buffering Flush explicitly (optional depending on CI4 response structure, 
            // but setting up direct echo streaming here is very fast and efficient)
            
            // Sodium chunk size = original byte length + 17 bytes MAC tag
            $readSize = self::CHUNK_SIZE + 17;
            
            while (!feof($file)) {
                $encryptedChunk = fread($file, $readSize);
                if ($encryptedChunk === false || $encryptedChunk === '') break;
                
                [$decryptedChunk, $tag] = \sodium_crypto_secretstream_xchacha20poly1305_pull($state, $encryptedChunk);
                
                if ($decryptedChunk === false) {
                    throw new RuntimeException("Corrupted chunk. Decryption failed.");
                }
                
                echo $decryptedChunk;
                // Optional: flush to force chunks out to client
                if (ob_get_level() > 0) ob_flush();
                flush();
                
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            fclose($file);
        }

    }
}
