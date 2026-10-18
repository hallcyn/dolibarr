<?php
/* Copyright (C) 2026 Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
/** Presentation-only companion for release-specific theme assets. */
class modIndyUI extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 500988; // Project-local ID, not a DoliStore registration.
        $this->rights_class = 'indyui';
        $this->family = 'interface';
        $this->name = 'IndyUI';
        $this->description = 'Indy theme: versioned UI assets';
        $this->version = '1.5.3';
        $this->const_name = 'MAIN_MODULE_INDYUI';
        $this->picto = 'paint-brush';
        $this->module_parts = array('hooks' => array('main'));
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(24, 0);
        $this->dirs = $this->const = $this->rights = $this->menu = array();
    }
    public function init($options = '') { return $this->_init(array(), $options); }
    public function remove($options = '') { return $this->_remove(array(), $options); }
}
