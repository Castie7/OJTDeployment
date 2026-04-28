<?php
// 1. Automatically detect the server's current IP or Hostname
$currentHost = $_SERVER['HTTP_HOST'];

// 2. Remove port number if it exists (e.g., "192.168.1.5:8080" -> "192.168.1.5")
$currentHost = explode(':', $currentHost)[0];

// 3. Define the Vite Server URL dynamically
$viteUrl = "http://{$currentHost}:5173";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="/favicon.ico">
</head>
<body>

    <div id="app"></div>

    <script type="module" src="<?= $viteUrl ?>/@vite/client"></script>
    <script type="module" src="<?= $viteUrl ?>/src/main.ts"></script>

</body>
</html>