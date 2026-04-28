<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GenerateKeys extends BaseCommand
{
    protected $group       = 'Encryption';
    protected $name        = 'keys:generate';
    protected $description = 'Generates RSA public and private keys for file encryption.';

    public function run(array $params)
    {
        $keysDir = WRITEPATH . 'keys';
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0777, true);
        }

        $privateKeyPath = $keysDir . '/private.pem';
        $publicKeyPath = $keysDir . '/public.pem';

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            CLI::write('Keys already exist in ' . $keysDir, 'yellow');
            return;
        }

        $config = [
            'digest_alg' => 'sha512',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Ensure openssl.cnf is findable if running on Windows XAMPP
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $config['config'] = env('OPENSSL_CONF', 'C:\xampp\apache\conf\openssl.cnf');
        }

        $res = openssl_pkey_new($config);

        if (!$res) {
            CLI::error('Failed to generate key: ' . openssl_error_string());
            return;
        }

        openssl_pkey_export($res, $privKey, null, $config);
        $pubKey = openssl_pkey_get_details($res)['key'];

        file_put_contents($privateKeyPath, $privKey);
        file_put_contents($publicKeyPath, $pubKey);

        CLI::write("Keys successfully generated in: {$keysDir}", 'green');
    }
}
