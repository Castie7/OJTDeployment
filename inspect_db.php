<?php
// Project root script to inspect DB
require_once 'backend/vendor/autoload.php';

// Mock paths for CI4 bootstrap
if (!defined('FCPATH')) define('FCPATH', __DIR__ . '/backend/public/');
if (!defined('WRITEPATH')) define('WRITEPATH', __DIR__ . '/backend/writable/');

// Load CI4
$bootstrap = require 'backend/system/Test/bootstrap.php';

$db = \Config\Database::connect();

echo "--- Researches Table ---\n";
try {
    $fields = $db->getFieldNames('researches');
    print_r($fields);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- Research Details Table ---\n";
try {
    $fields = $db->getFieldNames('research_details');
    print_r($fields);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
