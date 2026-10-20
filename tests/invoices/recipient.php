<?php
// Only run against the disposable CI database, never on production.
if (PHP_SAPI !== 'cli') exit(1);
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/indyinvoice/core/modules/facture/doc/pdf_indy.modules.php';
require_once DOL_DOCUMENT_ROOT.'/indyinvoice/core/modules/commande/doc/pdf_indyorder.modules.php';
$user->fetch(0, 'admin');
$activated = activateModule('modIndyInvoice');
if (!empty($activated['errors'])) throw new RuntimeException(json_encode($activated['errors']));
$conf->setValues($db);
$user->loadRights('', 1);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'bills', 'companies', 'orders'));
if (indyEnsureBillingRecipientField($db) <= 0 || indyEnsureBillingRecipientField($db) <= 0) throw new RuntimeException('Field provisioning is not idempotent');
$party = new Societe($db);
$party->name = 'ALBER Frédéric et Isabelle'; $party->client = 1; $party->code_client = 'auto';
$party->address = '17 rue des Vignes'; $party->zip = '69000'; $party->town = 'Lyon'; $party->country_id = 1;
if ($party->create($user) <= 0) throw new RuntimeException($party->error);
$contact = new Contact($db); $contact->socid = $party->id; $contact->lastname = 'Contact distinct'; $contact->firstname = 'Camille';
if ($contact->create($user) <= 0) throw new RuntimeException($contact->error);
$mysoc->name = 'Maison émettrice';
$out = '/tmp/indy-recipient-fixtures'; dol_mkdir($out); $manifest = array();
foreach (array('invoice', 'order', 'credit') as $kind) {
    $document = $kind === 'order' ? new Commande($db) : new Facture($db);
    $document->socid = $party->id; $document->date = dol_now(); $document->cond_reglement_id = 1; $document->mode_reglement_id = 2;
    if ($kind === 'credit') $document->type = 2;
    if ($document->create($user) <= 0 || $document->addline('Bouteilles de démonstration', 25, 12, 20) <= 0) throw new RuntimeException($document->error);
    if ($document->add_contact($contact->id, $kind === 'order' ? 'CUSTOMER' : 'BILLING', 'external') <= 0) throw new RuntimeException($document->error);
    foreach (array('absent' => null, 'custom' => 'Mme Isabelle ALBER et M. Frédéric ALBER', 'spaces' => '   ', 'empty' => '',
        'iso-inverted' => 'Mme Isabelle ALBER et M. Frédéric ALBER',
        'long' => 'Mme Isabelle ALBER et M. Frédéric ALBER — Famille et destinataire de facturation personnalisé') as $case => $value) {
        $party->array_options['options_destinataire_facturation'] = $value;
        if ($party->insertExtraFields() < 0) throw new RuntimeException($party->error);
        $document->fetch($document->id); $document->fetch_thirdparty(); $document->fetch_lines();
        $document->specimen = 1;
        $before = $document->thirdparty->name;
        $conf->global->MAIN_PDF_USE_ISO_LOCATION = $case === 'iso-inverted' ? 1 : 0;
        $conf->global->MAIN_INVERT_SENDER_RECIPIENT = $case === 'iso-inverted' ? 1 : 0;
        $conf->global->SALES_ORDER_SHOW_SHIPPING_ADDRESS = 1;
        $conf->global->INVOICE_SHOW_SHIPPING_ADDRESS = 1;
        $model = $kind === 'order' ? new pdf_indyorder($db) : new pdf_indy($db);
        if ($model->write_file($document, $langs) <= 0) throw new RuntimeException($model->error);
        if ($document->thirdparty->name !== $before) throw new RuntimeException('Third-party name mutated by PDF');
        $reloaded = new Societe($db); $reloaded->fetch($party->id);
        if ($reloaded->name !== $before || ($reloaded->array_options['options_destinataire_facturation'] ?? null) != $value) throw new RuntimeException('Persisted third party mutated by PDF');
        $name = $kind.'-'.$case;
        $dir = $kind === 'order' ? $conf->commande->multidir_output[$conf->entity] : $conf->facture->multidir_output[$conf->entity];
        if (!copy($dir.'/SPECIMEN.pdf', $out.'/'.$name.'.pdf')) throw new RuntimeException('PDF missing');
        $manifest[$name] = array('recipient' => trim((string) $value) !== '' ? trim($value) : $before, 'custom' => trim((string) $value) !== '');
    }
}
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "PASS: field provisioning and 18 native invoice/order/credit-note PDFs; third-party values unchanged.\n";
