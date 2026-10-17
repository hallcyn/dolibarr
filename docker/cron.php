<?php
declare(strict_types=1);
$settings = require '/run/dolibarr-cron.php';
$argv = ['/var/www/scripts/cron/cron_run_jobs.php', $settings['key'], $settings['user']];
$argc = count($argv);
require '/var/www/scripts/cron/cron_run_jobs.php';
