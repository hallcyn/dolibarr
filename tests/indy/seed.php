<?php
// Disposable CI database ONLY. Never run against a real business instance.
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$user->fetch(0, 'admin');
foreach (array('modSociete', 'modFacture', 'modPropale', 'modBanque', 'modService') as $module) {
    $result = activateModule($module);
    if (!empty($result['errors'])) {
        throw new RuntimeException(json_encode($result['errors']));
    }
}
dolibarr_set_const($db, 'MAIN_THEME', 'indy', 'chaine', 0, '', 1);
dolibarr_set_const($db, 'MAIN_LANG_DEFAULT', 'fr_FR', 'chaine', 0, '', 1);
dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_NOM', 'Atelier Hallcyn', 'chaine', 0, '', 1);
dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_COUNTRY', '1:FR:France', 'chaine', 0, '', 1);
$conf->setValues($db);
$user->loadRights('', 1);

$names = array('Maison Aubépine', 'Studio Horizon', 'Les Jardins de Louise', 'Atelier Mistral', 'Collectif Rivage', 'Bureau Aurore');
foreach ($names as $index => $name) {
    $soc = new Societe($db);
    $soc->name = $name;
    $soc->client = 1;
    $soc->code_client = 'auto';
    $soc->status = 1;
    $soc->email = 'bonjour'.($index + 1).'@example.test';
    $soc->town = array('Lyon', 'Paris', 'Nantes')[$index % 3];
    $soc->country_id = 1;
    if ($soc->create($user) <= 0) {
        throw new RuntimeException($soc->error.' '.json_encode($soc->errors));
    }
    $invoice = new Facture($db);
    $invoice->socid = $soc->id;
    $invoice->date = dol_mktime(12, 0, 0, 9, 5 + $index * 3, 2026);
    $invoice->cond_reglement_id = 1;
    $invoice->mode_reglement_id = 2;
    $invoice->note_public = 'Merci pour votre confiance.';
    if ($invoice->create($user) <= 0) {
        throw new RuntimeException($invoice->error);
    }
    if ($invoice->addline('Accompagnement et conseil — septembre', 650 + $index * 125, 2, 20) <= 0) {
        throw new RuntimeException($invoice->error);
    }
    if ($index < 4 && $invoice->validate($user, 'FA2609-'.sprintf('%04d', $index + 1)) <= 0) {
        throw new RuntimeException($invoice->error);
    }
}
echo "Seeded six fictional clients and invoices.\n";
