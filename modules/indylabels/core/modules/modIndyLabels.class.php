<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modIndyLabels extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db; $this->numero = 500988;
        $this->rights_class = 'indylabels'; $this->family = 'crm'; $this->module_position = '96';
        $this->name = 'IndyLabels'; $this->description = 'IndyLabelsDescription';
        $this->version = '1.0.0'; $this->const_name = 'MAIN_MODULE_INDYLABELS'; $this->picto = 'barcode';
        $this->editor_name = 'Hallcyn'; $this->editor_url = 'https://github.com/hallcyn/dolibarr';
        $this->depends = array('modSociete'); $this->phpmin = array(8, 1); $this->need_dolibarr_version = array(24, 0);
        $this->langfiles = array('indylabels@indylabels'); $this->dirs = array(); $this->const = array();
        $this->rights = array(array(5009881, 'Generate envelope labels for accessible third parties', 'r', 0, 'read'));
        $this->menu = array(array('fk_menu' => 'fk_mainmenu=companies', 'type' => 'left',
            'titre' => 'IndyLabelsTitle', 'mainmenu' => 'companies', 'leftmenu' => 'indylabels',
            'url' => '/indylabels/index.php', 'langs' => 'indylabels@indylabels', 'position' => 110,
            'enabled' => 'isModEnabled("indylabels")',
            'perms' => '$user->hasRight("indylabels", "read") && $user->hasRight("societe", "lire") && empty($user->socid)', 'user' => 2));
    }
    public function init($options = '')
    {
        require_once DOL_DOCUMENT_ROOT.'/indyinvoice/lib/recipient.lib.php';
        if (indyEnsureBillingRecipientField($this->db) <= 0) return -1;
        return $this->_init(array(), $options);
    }
    public function remove($options = '') { return $this->_remove(array(), $options); }
}
