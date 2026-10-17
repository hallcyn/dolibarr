<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Bounded wait, also works when the private hostname initially has no DNS record.
$wait = in_array('--wait', $argv ?? [], true);
$deadline = time() + ($wait ? 120 : 0);
do {
    try {
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        @$db->real_connect(getenv('DOLI_DB_HOST'), getenv('DOLI_DB_USER'), getenv('DOLI_DB_PASSWORD'),
            getenv('DOLI_DB_NAME'), (int)(getenv('DOLI_DB_HOST_PORT') ?: 3306));
        $db->query('SELECT 1');
        $db->close();
        exit(0);
    } catch (Throwable $error) {
        if (!$wait || time() >= $deadline) {
            fwrite(STDERR, "[railway] Database unavailable. Check the private host, credentials and database volume.\n");
            exit(1);
        }
        sleep(2);
    }
} while (true);
