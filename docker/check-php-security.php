<?php
// Reject images predating the 21 October 2026 critical PHP security fixes.
// https://www.php.net/archive/2026.php
$minimum = array('8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12');
$branch = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
if (!isset($minimum[$branch]) || version_compare(PHP_VERSION, $minimum[$branch], '<')) {
    fwrite(STDERR, 'Refusing unverified PHP runtime: '.PHP_VERSION.'. Rebuild using a patched official Dolibarr image.'.PHP_EOL);
    exit(1);
}
echo 'PHP security baseline verified: '.PHP_VERSION.' ('.PHP_SAPI.')'.PHP_EOL;
