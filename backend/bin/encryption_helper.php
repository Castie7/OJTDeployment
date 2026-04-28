<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This helper must be run from the CLI.\n");
    exit(1);
}

$backendRoot = dirname(__DIR__);

require_once $backendRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!defined('WRITEPATH')) {
    define('WRITEPATH', $backendRoot . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR);
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
});

$command = $argv[1] ?? null;

if (!is_string($command) || $command === '') {
    fwrite(STDERR, "Usage: php encryption_helper.php <encrypt|decrypt-stdout> <path> [dest]\n");
    exit(1);
}

$service = new \App\Services\EncryptionService();

switch ($command) {
    case 'encrypt':
        $sourcePath = $argv[2] ?? null;
        $destPath = $argv[3] ?? null;

        if (!is_string($sourcePath) || $sourcePath === '' || !is_string($destPath) || $destPath === '') {
            fwrite(STDERR, "encrypt requires <sourcePath> and <destPath>\n");
            exit(1);
        }

        $service->encryptFile($sourcePath, $destPath);
        exit(0);

    case 'decrypt-stdout':
        $encryptedFilePath = $argv[2] ?? null;

        if (!is_string($encryptedFilePath) || $encryptedFilePath === '') {
            fwrite(STDERR, "decrypt-stdout requires <encryptedFilePath>\n");
            exit(1);
        }

        $service->streamDecryptToOutput($encryptedFilePath);
        exit(0);

    default:
        fwrite(STDERR, "Unknown command: {$command}\n");
        exit(1);
}
