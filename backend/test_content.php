<?php
$f = fopen('writable/uploads/research/1770972895_14774a3650c929b5a46c.pdf', 'rb');
$len = unpack('v', fread($f, 2))[1];
$ek = fread($f, $len);
$pk = openssl_pkey_get_private(file_get_contents('writable/keys/private.pem'));
openssl_private_decrypt($ek, $sk, $pk);
$hdr = fread($f, 24);
$state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($hdr, $sk);
$chunk = fread($f, 8192*1024+17);
[$dec, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
fclose($f);
echo 'Decrypted size: ' . strlen($dec) . PHP_EOL;
echo 'First 10 bytes hex: ' . bin2hex(substr($dec, 0, 10)) . PHP_EOL;
echo 'First 20 bytes ascii: ' . substr($dec, 0, 20) . PHP_EOL;
echo 'Has %PDF anywhere in first 1000: ' . (strpos(substr($dec, 0, 1000), '%PDF') !== false ? 'YES at pos ' . strpos($dec, '%PDF') : 'NO') . PHP_EOL;
