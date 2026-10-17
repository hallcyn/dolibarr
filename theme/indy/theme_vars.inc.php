<?php
/* Copyright (C) 2026 Hallcyn
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require __DIR__.'/../eldy/theme_vars.inc.php';

$colorbackbody = '248,247,251';
$colorbackhmenu1 = '255,255,255';
$colorbackvmenu1 = '47,36,73';
$colorbacktitle1 = '248,247,251';
$colorbacktabcard1 = '255,255,255';
$colorbacktabactive = '240,234,250';
$colortext = '43,41,51';
$colortextlink = '105,66,164';
$colortexttitle = '43,41,51';
$colortexttitlelink = '105,66,164';
$colortexttitlenotab = '43,41,51';
$colortexttitlenotab2 = '105,66,164';
$colorbacklinepairhover = '248,245,253';
$colorbacklinepairchecked = '240,234,250';
$butactionbg = '105,66,164';
$textbutaction = '255,255,255';
$theme_bgcolor = array(248, 247, 251);
$theme_bgcoloronglet = array(240, 234, 250);
$theme_bordercolor = array(233, 229, 239);

// Keep Dolibarr's accessible graph palettes when color-blind mode is selected.
if (function_exists('getDolGlobalString') && !getDolGlobalString('MAIN_OPTIMIZEFORCOLORBLIND')) {
    $theme_datacolor = array(array(0, 145, 125), array(218, 132, 159), array(128, 83, 181), array(236, 176, 76), array(74, 144, 171), array(123, 145, 92));
}
