<?php
/* Copyright (C) 2026 Hallcyn
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

// Eldy owns bootstrap, session, language, RTL, responsive menus and headers.
// Its asset paths still point to Eldy; no core file is patched or duplicated.
require __DIR__.'/../eldy/style.css.php';
require __DIR__.'/theme_vars.inc.php';
if (defined('THEME_ONLY_CONSTANT')) {
    return;
}
readfile(__DIR__.'/indy.css');
