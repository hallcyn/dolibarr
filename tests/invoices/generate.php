<?php
// Disposable Compose database ONLY. This file is never part of the web image.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/indyinvoice/core/modules/facture/doc/pdf_indy.modules.php';
$user->fetch(0, 'admin');
$activated = activateModule('modIndyInvoice');
if (!empty($activated['errors'])) {
    throw new RuntimeException(json_encode($activated['errors']));
}
$conf->setValues($db);
$user->loadRights('', 1);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'bills', 'companies'));
$mysoc->name = 'Maison Démonstration';
$mysoc->address = '1 rue de l’Exemple';
$mysoc->zip = '69000';
$mysoc->town = 'Lyon';
$mysoc->country_id = 1;
$mysoc->country_code = 'FR';
$mysoc->tva_assuj = 1;
$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS = 1;
$conf->global->INVOICE_FREE_TEXT = 'Données fictives — document de démonstration uniquement.';
$logoDir = $conf->mycompany->multidir_output[$conf->entity] ?? $conf->mycompany->dir_output;
dol_mkdir($logoDir.'/logos/thumbs');
$im = imagecreatetruecolor(480, 125);
$white = imagecolorallocatealpha($im, 255, 255, 255, 127);
imagealphablending($im, false);
imagesavealpha($im, true);
imagefill($im, 0, 0, $white);
imagealphablending($im, true);
$ttf = tempnam(sys_get_temp_dir(), 'indy-font-');
file_put_contents($ttf, gzuncompress(file_get_contents(DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/fonts/indyinterb.z')));
imagettftext($im, 58, 0, 8, 91, imagecolorallocate($im, 39, 51, 62), $ttf, 'DÉMO');
imagepng($im, $logoDir.'/logos/thumbs/indy-demo.png');
imagedestroy($im);
unlink($ttf);
$mysoc->logo = 'indy-demo.png';
$mysoc->logo_small = 'indy-demo.png';
$out = $argv[1] ?? '/tmp/indy-invoice-fixtures';
dol_mkdir($out);
$manifest = array();
$cases = array('vat-discounts', 'no-vat', 'credit-note', 'long-invoice', 'grid', 'unicode');
foreach ($cases as $case) {
    $invoice = new Facture($db);
    $invoice->socid = 1;
    $invoice->date = dol_mktime(12, 0, 0, 10, 18, 2026);
    $invoice->cond_reglement_id = 1;
    $mode = $db->fetch_object($db->query("SELECT id FROM ".MAIN_DB_PREFIX."c_paiement WHERE code='CB'"));
    $invoice->mode_reglement_id = $mode->id;
    $invoice->note_public = 'Projet de démonstration. Toutes les informations proviennent de Dolibarr.';
    if ($case === 'credit-note') {
        $invoice->type = 2;
        $invoice->fk_facture_source = 1;
    }
    if ($invoice->create($user) <= 0) {
        throw new RuntimeException($invoice->error);
    }
    if ($case === 'no-vat') {
        $invoice->addline('Prestation de démonstration — remise 10 %', 240, 3, 0, 0, 0, 0, 10);
    } elseif ($case === 'long-invoice') {
        for ($i = 1; $i <= 48; $i++) {
            $invoice->addline('Ligne '.$i.' — Prestation détaillée. '.str_repeat('Description avec accents, quantité et montant gérés par Dolibarr. ', 3), 24 + $i, 2, $i % 2 ? 20 : 5.5);
        }
    } elseif ($case === 'credit-note') {
        $invoice->addline('Avoir de démonstration', -120, 1, 20);
    } else {
        $invoice->addline('Accompagnement — évaluation et conception', 280, 4, 20, 0, 0, 0, 10);
        $invoice->addline('Documentation et transmission des éléments', 180, 2, 5.5);
        $invoice->addline('Prestation complémentaire à taux nul', 75, 1, 0);
    }
    $invoice->fetch($invoice->id);
    $invoice->fetch_thirdparty();
    $invoice->fetch_lines();
    $invoice->specimen = 1;
    $conf->global->INDYINVOICE_TABLE_STYLE = $case === 'grid' ? 'grid' : 'light';
    $conf->global->INDYINVOICE_FONT = $case === 'unicode' ? 'dejavusans' : 'indyinter';
    $snapshot = array();
    foreach (array('MAIN_PDF_FORCE_FONT', 'MAIN_PDF_FORCE_FONT_SIZE', 'MAIN_PDF_NO_SENDER_FRAME', 'MAIN_PDF_NO_RECIPENT_FRAME', 'MAIN_PDF_TITLE_BACKGROUND_COLOR') as $key) {
        $snapshot[$key] = array(property_exists($conf->global, $key), $conf->global->$key ?? null);
    }
    $totals = array_map('floatval', array($invoice->total_ht, $invoice->total_tva, $invoice->total_ttc));
    $model = new pdf_indy($db);
    if ($model->write_file($invoice, $langs) <= 0) {
        throw new RuntimeException($model->error);
    }
    foreach ($snapshot as $key => $before) {
        if ($before !== array(property_exists($conf->global, $key), $conf->global->$key ?? null)) {
            throw new RuntimeException('Global PDF setting was changed: '.$key);
        }
    }
    if ($totals !== array_map('floatval', array($invoice->total_ht, $invoice->total_tva, $invoice->total_ttc))) {
        throw new RuntimeException('Invoice totals changed during PDF generation');
    }
    $file = $conf->facture->multidir_output[$conf->entity].'/SPECIMEN.pdf';
    if (!is_file($file) || !copy($file, $out.'/'.$case.'.pdf')) {
        throw new RuntimeException('Generated PDF missing: '.$file);
    }
    $manifest[$case] = array('ht' => $totals[0], 'vat' => $totals[1], 'ttc' => $totals[2], 'lines' => count($invoice->lines));
}
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "PASS: six native PDF scenarios, invoice totals and global PDF settings preserved.\n";
