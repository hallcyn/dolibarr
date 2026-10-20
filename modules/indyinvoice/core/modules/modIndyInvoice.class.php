<?php
/* Copyright (C) 2026 Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/** Native invoice presentation, without a business table or numbering scheme. */
class modIndyInvoice extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 500987; // Project-local ID; not a DoliStore registration.
        $this->rights_class = 'indyinvoice';
        $this->family = 'financial';
        $this->module_position = '95';
        $this->name = 'IndyInvoice';
        $this->description = 'IndyInvoiceDescription';
        $this->editor_name = 'Hallcyn';
        $this->editor_url = 'https://github.com/hallcyn/dolibarr';
        $this->version = '1.3.0';
        $this->const_name = 'MAIN_MODULE_INDYINVOICE';
        $this->picto = 'bill';
        $this->module_parts = array('models' => 1);
        $this->config_page_url = array('setup.php@indyinvoice');
        $this->langfiles = array('indyinvoice@indyinvoice');
        $this->depends = array('modFacture');
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(24, 0);
        $this->dirs = array();
        $this->const = array();
        $this->rights = array();
        $this->menu = array();
    }

    public function init($options = '')
    {
        global $conf;
        require_once __DIR__.'/../../lib/recipient.lib.php';
        if (indyEnsureBillingRecipientField($this->db) <= 0) {
            $this->error = 'Unable to create the billing recipient field';
            return -1;
        }
        $entity = (int) $conf->entity;
        return $this->_init(array(
            "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom,type,entity) SELECT 'indy','invoice',".$entity.
            " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom='indy' AND type='invoice' AND entity=".$entity.")",
            "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom,type,entity) SELECT 'indyorder','order',".$entity.
            " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom='indyorder' AND type='order' AND entity=".$entity.")"
        ), $options);
    }

    public function remove($options = '')
    {
        global $conf;
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        if (getDolGlobalString('FACTURE_ADDON_PDF') === 'indy') {
            $previous = getDolGlobalString('INDYINVOICE_PREVIOUS_MODEL', 'sponge');
            // A removed third-party generator must not become the fallback.
            if (!in_array($previous, array('sponge', 'octopus'), true)) {
                $previous = 'sponge';
            }
            dolibarr_set_const($this->db, 'FACTURE_ADDON_PDF', $previous, 'chaine', 0, '', $conf->entity);
        }
        if (getDolGlobalString('COMMANDE_ADDON_PDF') === 'indyorder') {
            $previous = getDolGlobalString('INDYINVOICE_PREVIOUS_ORDER_MODEL', 'eratosthene');
            $res = $this->db->query("SELECT nom FROM ".MAIN_DB_PREFIX."document_model WHERE type='order' AND nom='".$this->db->escape($previous)."' AND entity=".((int)$conf->entity));
            if (!$res || !$this->db->num_rows($res)) $previous = 'eratosthene';
            dolibarr_set_const($this->db, 'COMMANDE_ADDON_PDF', $previous, 'chaine', 0, '', $conf->entity);
        }
        return $this->_remove(array(
            "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE ((nom='indy' AND type='invoice') OR (nom='indyorder' AND type='order')) AND entity=".((int) $conf->entity)
        ), $options);
    }
}
