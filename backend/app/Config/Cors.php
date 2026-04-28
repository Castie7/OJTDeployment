<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $default = [
        'allowedOrigins' => [],
        'allowedOriginsPatterns' => [],
        'supportsCredentials' => true,
        'allowedHeaders' => ['Content-Type', 'X-CSRF-TOKEN', 'Authorization', 'X-Requested-With'],
        'exposedHeaders' => [],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge' => 7200,
    ];

    public function __construct()
    {
        parent::__construct();

        $configuredOrigins = $this->parseCsv((string) env('cors.allowedOrigins', ''));
        if ($configuredOrigins !== []) {
            $this->default['allowedOrigins'] = $configuredOrigins;
        } elseif (ENVIRONMENT !== 'production') {
            $this->default['allowedOrigins'] = [
                'http://localhost:5173',
                'https://localhost:5173',
                'http://127.0.0.1:5173',
                'https://127.0.0.1:5173',
            ];
        }

        $configuredPatterns = $this->parseCsv((string) env('cors.allowedOriginsPatterns', ''));
        if ($configuredPatterns !== []) {
            $this->default['allowedOriginsPatterns'] = $configuredPatterns;
        } elseif (ENVIRONMENT !== 'production') {
            $this->default['allowedOriginsPatterns'] = [
                'https?://192\\.168\\.\\d{1,3}\\.\\d{1,3}:5173',
                'https?://10\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}:5173',
            ];
        }

        $supportsCredentials = env('cors.supportsCredentials');
        if ($supportsCredentials !== null) {
            $parsed = filter_var($supportsCredentials, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                $this->default['supportsCredentials'] = $parsed;
            }
        }

        $maxAge = env('cors.maxAge');
        if ($maxAge !== null && ctype_digit((string) $maxAge)) {
            $this->default['maxAge'] = max(0, (int) $maxAge);
        }
    }

    private function parseCsv(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

        return array_values(array_unique($items));
    }
}
