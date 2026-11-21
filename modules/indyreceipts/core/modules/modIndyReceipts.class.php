<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modIndyReceipts extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 500989;
        $this->rights_class = 'indyreceipts';
        $this->family = 'financial';
        $this->name = 'IndyReceipts';
        $this->description = 'IndyReceiptsDescription';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_INDYRECEIPTS';
        $this->picto = 'payment';
        $this->depends = array('modFacture');
        $this->langfiles = array('indyreceipts@indyreceipts');
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(24, 0);
        $this->menu = array(array(
            'fk_menu' => 'fk_mainmenu=billing,fk_leftmenu=customers_bills_payment',
            'type' => 'left',
            'titre' => 'IndyReceiptsTitle',
            'mainmenu' => 'billing',
            'leftmenu' => 'customers_bills_payment_indyreceipts',
            'url' => '/indyreceipts/index.php',
            'langs' => 'indyreceipts@indyreceipts',
            'position' => 101,
            'enabled' => 'isModEnabled("indyreceipts") && isModEnabled("invoice")',
            'perms' => '$user->hasRight("facture", "lire") && !$user->isExternalUser()',
            'user' => 0,
        ));
    }

    public function init($options = '')
    {
        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
