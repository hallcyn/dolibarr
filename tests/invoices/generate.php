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
$cases = array('vat-discounts', 'no-vat', 'credit-note', 'long-invoice', 'grid', 'unicode', 'sage', 'banner', 'native-specimen', 'serif');
// A fictitious, native-format panoramic banner for the image scenario.
$bannerDir = DOL_DATA_ROOT.'/indyinvoice'; dol_mkdir($bannerDir);
$im = imagecreatetruecolor(1680,160); imagefill($im,0,0,imagecolorallocate($im,42,99,75));
imagefilledellipse($im,1380,80,360,300,imagecolorallocate($im,176,209,187));
imagepng($im,$bannerDir.'/banner-'.((int)$conf->entity).'.png'); imagedestroy($im);
$conf->global->INVOICE_CATEGORY_OF_OPERATION = 1;
$conf->global->INDYINVOICE_BANNER_HEIGHT = 32;
$conf->global->INDYINVOICE_FONT_SIZE = 11;
$conf->global->INDYINVOICE_LOGO_HEIGHT = 18;
foreach ($cases as $case) {
    $conf->global->INDYINVOICE_PAPER_COLOR = $case === 'sage' ? 'F4F7F2' : 'FFFFFF';
    $conf->global->INDYINVOICE_TABLE_COLOR = $case === 'sage' ? '286347' : 'FCEBD8';
    $conf->global->INDYINVOICE_BANNER_COLOR = $case === 'sage' ? 'EDF5EF' : 'FCEBD8';
    $conf->global->INDYINVOICE_ACCENT_COLOR = $case === 'sage' ? '286347' : 'D82B68';
    $conf->global->INDYINVOICE_USE_BANNER_IMAGE = $case === 'banner' ? 1 : 0;
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
    if ($case === 'native-specimen') {
        $invoice = new Facture($db); $invoice->initAsSpecimen();
        $invoice->mode_reglement_id = $mode->id; $invoice->mode_reglement_code = 'CB';
        $invoice->note_public = 'Note publique de démonstration pour vérifier la catégorie et les chevauchements.';
    }
    $invoice->specimen = 1;
    $conf->global->INDYINVOICE_TABLE_STYLE = $case === 'grid' ? 'grid' : 'light';
    $conf->global->INDYINVOICE_FONT = $case === 'unicode' ? 'dejavusans' : ($case === 'serif' ? 'dejavuserif' : 'indyinter');
    $snapshot = array();
    foreach (array('MAIN_PDF_FORCE_FONT', 'MAIN_PDF_FORCE_FONT_SIZE', 'MAIN_PDF_NO_SENDER_FRAME', 'MAIN_PDF_NO_RECIPENT_FRAME', 'MAIN_PDF_TITLE_BACKGROUND_COLOR', 'MAIN_DOCUMENTS_LOGO_HEIGHT', 'INVOICE_CATEGORY_OF_OPERATION') as $key) {
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
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/indyinvoice/core/modules/commande/doc/pdf_indyorder.modules.php';
$conf->global->INDYINVOICE_PAPER_COLOR = 'FFFFFF';
$conf->global->INDYINVOICE_TABLE_COLOR = 'FCEBD8';
$conf->global->INDYINVOICE_BANNER_COLOR = 'FCEBD8';
$conf->global->INDYINVOICE_USE_BANNER_IMAGE = 0;
$conf->global->INDYINVOICE_FONT = 'indyinter';
foreach (array('order', 'long-order') as $case) {
    $order = new Commande($db); $order->fetch(1); $order->fetch_thirdparty(); $order->fetch_lines();
    if ($case === 'long-order') {
        for ($i=1; $i<=35; $i++) $order->addline('Commande ligne '.$i.' - Description détaillée pour vérifier les pages.',30+$i,2,20);
        $order->fetch(1); $order->fetch_thirdparty(); $order->fetch_lines();
    }
    $order->specimen=1; $order->note_public='Données fictives : commande de démonstration.';
    $totals=array_map('floatval',array($order->total_ht,$order->total_tva,$order->total_ttc));
    $model=new pdf_indyorder($db);
    if ($model->write_file($order,$langs)<=0) throw new RuntimeException($model->error);
    $file=$conf->commande->multidir_output[$conf->entity].'/SPECIMEN.pdf';
    if (!copy($file,$out.'/'.$case.'.pdf')) throw new RuntimeException('Order PDF missing');
    if ($totals!==array_map('floatval',array($order->total_ht,$order->total_tva,$order->total_ttc))) throw new RuntimeException('Order totals changed');
    $manifest[$case]=array('ht'=>$totals[0],'vat'=>$totals[1],'ttc'=>$totals[2],'lines'=>count($order->lines));
}
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "PASS: twelve native invoice/order PDF scenarios, totals and global PDF settings preserved.\n";
