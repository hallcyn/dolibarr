<?php
/* Copyright (C) 2026 Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
/** Keep Dolibarr's native nonce/script loader, with an immutable cache key. */
class ActionsIndyUI
{
    public $resPrint = '';
    public $results = array();
    public $errors = array();
    public function setHtmlTitle($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        if ($conf->theme !== 'indy' || !getDolGlobalInt('ALLOW_THEME_JS')) return 0;
        $file = DOL_DOCUMENT_ROOT.'/theme/indy/indy.js';
        if (!is_file($file)) return 0;
        $asset = '/theme/indy/indy.js?indy_version='.substr(hash_file('sha256', $file), 0, 16);
        // This hook runs before the native module-JS loader. That loader
        // retains its nonce, ordering and page-level disablejs behaviour.
        $conf->modules_parts['js']['indyui'] = array($asset);
        // Request-local only; never change the persisted ALLOW_THEME_JS.
        $conf->global->ALLOW_THEME_JS = 0;
        return 0;
    }
}
