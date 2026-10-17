<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    if (!is_file('/var/www/documents/install.lock') || !is_writable('/data/documents') || !is_writable('/data/custom')) {
        throw new RuntimeException('Storage or installation not ready');
    }
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$db->real_connect(getenv('DOLI_DB_HOST'), getenv('DOLI_DB_USER'), getenv('DOLI_DB_PASSWORD'),
        getenv('DOLI_DB_NAME'), (int)(getenv('DOLI_DB_HOST_PORT') ?: 3306));
    $result = $db->query('SELECT rowid FROM llx_user WHERE admin = 1 AND statut = 1 LIMIT 1');
    if (!$result->fetch_row()) { throw new RuntimeException('No administrator'); }
    $db->close();
    echo '{"status":"ok"}';
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
