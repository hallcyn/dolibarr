<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    if (!is_file('/var/www/documents/install.lock')) {
        throw new RuntimeException('Installation lock is missing');
    }
    if (!is_writable('/data/documents') || !is_writable('/data/custom')) {
        throw new RuntimeException('Persistent directories are not writable by Apache');
    }
    require '/var/www/html/conf/conf.php';
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$db->real_connect($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass,
        $dolibarr_main_db_name, (int)$dolibarr_main_db_port);
    $result = $db->query('SELECT rowid FROM llx_user WHERE admin = 1 AND statut = 1 LIMIT 1');
    if (!$result->fetch_row()) { throw new RuntimeException('No administrator'); }
    $db->close();
    echo '{"status":"ok"}';
} catch (Throwable $error) {
    // Details stay in server logs; never include credentials in the HTTP reply.
    error_log('[railway-health] '.($error instanceof mysqli_sql_exception ? 'Database check failed' : $error->getMessage()));
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
