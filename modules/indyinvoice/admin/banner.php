<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require __DIR__.'/../../main.inc.php';
if (!$user->admin || !isModEnabled('indyinvoice')) accessforbidden();
$file = DOL_DATA_ROOT.'/indyinvoice/banner-'.((int)$conf->entity).'.png';
if (!is_readable($file)) { http_response_code(404); exit; }
header('Content-Type: image/png'); header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff'); readfile($file);
