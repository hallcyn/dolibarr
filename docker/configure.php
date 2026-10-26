<?php
declare(strict_types=1);

function required(string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '' || str_contains($value, '${{')) {
        throw new RuntimeException("Set $name to a real value before deploying.");
    }
    return $value;
}

try {
    foreach (['DOLI_DB_HOST', 'DOLI_DB_NAME', 'DOLI_DB_USER', 'DOLI_DB_PASSWORD',
              'DOLI_ADMIN_LOGIN', 'DOLI_ADMIN_PASSWORD', 'DOLI_INSTANCE_UNIQUE_ID'] as $name) {
        required($name);
    }
    if (strlen(required('DOLI_ADMIN_PASSWORD')) < 16) {
        throw new RuntimeException('DOLI_ADMIN_PASSWORD must contain at least 16 characters.');
    }
    if (!preg_match('/^[a-zA-Z0-9_.@-]+$/', required('DOLI_ADMIN_LOGIN'))) {
        throw new RuntimeException('Use letters, digits, _, ., @ or - for the initial admin login.');
    }
    $url = rtrim(required('DOLI_URL_ROOT'), '/');
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || !empty($parts['query']) || !empty($parts['fragment']) || !empty($parts['user'])
        || !empty($parts['pass']) || !empty($parts['path'])) {
        throw new RuntimeException('DOLI_URL_ROOT must be an http(s) origin, without a path, query or credentials.');
    }
    if (getenv('RAILWAY_ENVIRONMENT_ID') && ($parts['scheme'] ?? '') !== 'https') {
        throw new RuntimeException('Use an https:// DOLI_URL_ROOT on Railway.');
    }
    $port = getenv('DOLI_DB_HOST_PORT') ?: '3306';
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        throw new RuntimeException('DOLI_DB_HOST_PORT must be a valid TCP port.');
    }
    $instanceId = required('DOLI_INSTANCE_UNIQUE_ID');
    if (strlen($instanceId) < 32) {
        throw new RuntimeException('DOLI_INSTANCE_UNIQUE_ID must contain at least 32 characters.');
    }
    $idFile = '/data/secrets/instance-id';
    if (is_file($idFile) && !hash_equals(trim(file_get_contents($idFile)), $instanceId)) {
        throw new RuntimeException('DOLI_INSTANCE_UNIQUE_ID differs from the persistent key. Restore the original value.');
    }
    if (!is_file($idFile)) {
        file_put_contents($idFile, $instanceId."\n", LOCK_EX);
        chmod($idFile, 0600);
    }
    $config = [
        'dolibarr_main_url_root' => $url,
        'dolibarr_main_document_root' => '/var/www/html',
        'dolibarr_main_url_root_alt' => '/custom',
        'dolibarr_main_document_root_alt' => '/var/www/html/custom',
        'dolibarr_main_data_root' => '/var/www/documents',
        'dolibarr_main_db_host' => required('DOLI_DB_HOST'),
        'dolibarr_main_db_port' => $port,
        'dolibarr_main_db_name' => required('DOLI_DB_NAME'),
        'dolibarr_main_db_prefix' => 'llx_',
        'dolibarr_main_db_user' => required('DOLI_DB_USER'),
        'dolibarr_main_db_pass' => required('DOLI_DB_PASSWORD'),
        'dolibarr_main_db_type' => 'mysqli',
        'dolibarr_main_db_character_set' => 'utf8mb4',
        'dolibarr_main_db_collation' => 'utf8mb4_unicode_ci',
        'dolibarr_main_authentication' => 'dolibarr',
        'dolibarr_main_prod' => '1',
        'dolibarr_main_instance_unique_id' => $instanceId,
        'dolibarr_main_force_https' => ($parts['scheme'] === 'https' ? '1' : '0'),
        // Keep Dolibarr 24's restricted evaluator; add the read-only helper and PHP date formatting.
        'dolibarr_main_restrict_eval_methods' => 'getDolGlobalString, getDolGlobalInt, getDolCurrency, getDolEntity, getDolDBType, fetchNoCompute, hasRight, isAdmin, isExternalUser, isModEnabled, isStringVarMatching, abs, min, max, round, dol_now, preg_match, indyLastOrderDate, date',
    ];
    $php = "<?php\n// Generated at startup from Railway variables.\n";
    foreach ($config as $key => $value) {
        $php .= '$'.$key.' = '.var_export($value, true).";\n";
    }
    $php .= "require_once '/opt/railway/computed-fields.php';\n";
    if (!is_dir('/var/www/html/conf')) { mkdir('/var/www/html/conf', 0755, true); }
    file_put_contents('/var/www/html/conf/conf.php', $php, LOCK_EX);
    chown('/var/www/html/conf/conf.php', 'www-data');
    chgrp('/var/www/html/conf/conf.php', 'www-data');
    chmod('/var/www/html/conf/conf.php', 0400);

    $secure = $parts['scheme'] === 'https' ? '1' : '0';
    file_put_contents(getenv('PHP_INI_DIR').'/conf.d/zz-railway.ini',
        "session.save_path=/data/sessions\nsession.cookie_httponly=1\nsession.cookie_samesite=Lax\n".
        "session.cookie_secure=$secure\nsession.gc_probability=1\nsession.gc_divisor=100\n");

    if ((getenv('DOLI_RAILWAY_CRON') ?: '0') === '1') {
        $cron = ['key' => required('DOLI_CRON_KEY'), 'user' => getenv('DOLI_CRON_USER') ?: 'firstadmin'];
        file_put_contents('/run/dolibarr-cron.php', '<?php return '.var_export($cron, true).";\n");
        chown('/run/dolibarr-cron.php', 'www-data');
        chmod('/run/dolibarr-cron.php', 0400);
    }
    echo "[railway] Configuration ready; secrets were not printed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, '[railway] '.$error->getMessage()."\n");
    exit(1);
}
