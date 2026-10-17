<?php
/* Copyright (C) 2026 Hallcyn
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require __DIR__.'/../eldy/theme_vars.inc.php';

$colorbackbody = '255,255,255';
$colorbackhmenu1 = '253,247,237';
$colorbackvmenu1 = '255,255,255';
$colorbacktitle1 = '255,255,255';
$colorbacktabcard1 = '255,255,255';
$colorbacktabactive = '255,236,211';
$colortext = '39,51,62';
$colortextlink = '53,77,97';
$colortexttitle = '39,51,62';
$colortexttitlelink = '53,77,97';
$colortexttitlenotab = '39,51,62';
$colortexttitlenotab2 = '53,77,97';
$colorbacklinepairhover = '255,251,246';
$colorbacklinepairchecked = '255,236,211';
$butactionbg = '255,84,137';
$textbutaction = '255,255,255';
$theme_bgcolor = array(255, 255, 255);
$theme_bgcoloronglet = array(255, 236, 211);
$theme_bordercolor = array(215, 225, 233);

// Keep Dolibarr's accessible graph palettes when color-blind mode is selected.
if (function_exists('getDolGlobalString') && !getDolGlobalString('MAIN_OPTIMIZEFORCOLORBLIND')) {
    $theme_datacolor = array(array(0, 145, 125), array(218, 132, 159), array(128, 83, 181), array(236, 176, 76), array(74, 144, 171), array(123, 145, 92));
}
