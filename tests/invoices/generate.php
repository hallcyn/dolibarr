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
$cases = array('vat-discounts', 'no-vat', 'credit-note', 'long-invoice', 'grid', 'unicode', 'sage', 'banner', 'native-specimen', 'serif', 'bank-transfer', 'bank-empty', 'bank-document', 'bank-card-hidden', 'bank-card-shown', 'bank-credit', 'mentions-long', 'mentions-long-pages', 'mentions-html', 'text-colors');
// Deliberately invalid example IBANs: disposable database, never payable.
$bankIds = array();
foreach (array('A', 'B') as $suffix) {
    $ref = 'INDYTEST'.$suffix;
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."bank_account WHERE ref='".$ref."'");
    if (!$db->query("INSERT INTO ".MAIN_DB_PREFIX."bank_account (ref,label,bank,bic,iban_prefix,fk_pays,proprio,currency_code,courant) VALUES ('".$ref."','DEMONSTRATION ".$suffix."','BANQUE FICTIVE ".$suffix."','DEMOFRPPXXX','FR000000000000000000000000".($suffix === 'A' ? '1' : '2')."',1,'TITULAIRE FICTIF ".$suffix."','EUR',1)")) throw new RuntimeException($db->lasterror());
    $bankIds[$suffix] = $db->last_insert_id(MAIN_DB_PREFIX.'bank_account');
}
$longText = 'Données fictives — CONDITIONS DE DÉMONSTRATION. '.str_repeat('Les articles de cet exemple doivent être conservés dans un local sec, à température stable, à l’abri de la lumière et des variations de chaleur pendant le transport. ', 4).'FIN PREMIER PARAGRAPHE.';
$longText .= "\n\n".str_repeat('Le destinataire vérifie la livraison, respecte les conditions de conservation et contacte la société fictive pour toute demande relative à cette démonstration. ', 4).'FIN DES CONDITIONS TEST.';
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
    $conf->global->MAIN_PDF_DONOTREPEAT_HEAD = $case === 'long-invoice' ? 1 : 0;
    $conf->global->FACTURE_RIB_NUMBER = strpos($case, 'bank-') === 0 ? $bankIds['A'] : 0;
    $conf->global->INDYINVOICE_SHOW_BANK_DETAILS = in_array($case, array('bank-card-shown', 'bank-credit'), true) ? 1 : 0;
    $conf->global->INVOICE_FREE_TEXT = strpos($case, 'mentions-') === 0 ? $longText : 'Données fictives — document de démonstration uniquement.';
    $conf->global->PDF_ALLOW_HTML_FOR_FREE_TEXT = $case === 'mentions-html' ? 1 : 0;
    if ($case === 'mentions-html') $conf->global->INVOICE_FREE_TEXT = '<b>Données fictives — MENTION HTML TEST.</b><br>Texte libre avec mise en forme native.<br>FIN MENTION HTML.';
    $conf->global->FACTURE_DRAFT_WATERMARK = $case === 'mentions-long' ? 'BROUILLON TEST' : '';
    foreach (array('HEADER', 'TABLE', 'TOTAL') as $zone) $conf->global->{'INDYINVOICE_'.$zone.'_TEXT_AUTO'} = $case === 'text-colors' ? 0 : 1;
    $conf->global->INDYINVOICE_TEXT_COLOR = $case === 'text-colors' ? '452060' : '27333E';
    $conf->global->INDYINVOICE_HEADER_TEXT_COLOR = 'FFF4DB';
    $conf->global->INDYINVOICE_TABLE_TEXT_COLOR = '354080';
    $conf->global->INDYINVOICE_TOTAL_TEXT_COLOR = 'FFF4DB';
    $conf->global->INDYINVOICE_FOOTER_TEXT_COLOR = $case === 'text-colors' ? '354080' : '5B6770';
    $conf->global->INDYINVOICE_PAPER_COLOR = in_array($case, array('sage', 'long-invoice', 'mentions-long-pages'), true) ? 'EAF4EE' : ($case === 'grid' ? 'FFF0F5' : 'FFFFFF');
    $conf->global->INDYINVOICE_TABLE_COLOR = $case === 'sage' ? '286347' : 'FCEBD8';
    $conf->global->INDYINVOICE_BANNER_COLOR = $case === 'sage' ? 'EDF5EF' : 'FCEBD8';
    $conf->global->INDYINVOICE_ACCENT_COLOR = $case === 'sage' ? '286347' : 'D82B68';
    if ($case === 'text-colors') { $conf->global->INDYINVOICE_BANNER_COLOR = '452060'; $conf->global->INDYINVOICE_ACCENT_COLOR = '452060'; }
    $conf->global->INDYINVOICE_USE_BANNER_IMAGE = $case === 'banner' ? 1 : 0;
    $invoice = new Facture($db);
    $invoice->socid = 1;
    $invoice->date = dol_mktime(12, 0, 0, 10, 18, 2026);
    $invoice->cond_reglement_id = 1;
    $mode = $db->fetch_object($db->query("SELECT id FROM ".MAIN_DB_PREFIX."c_paiement WHERE code='CB'"));
    $invoice->mode_reglement_id = $mode->id;
    if (in_array($case, array('bank-transfer', 'bank-document', 'mentions-long', 'mentions-long-pages'), true)) {
        $transfer = $db->fetch_object($db->query("SELECT id FROM ".MAIN_DB_PREFIX."c_paiement WHERE code='VIR'"));
        $invoice->mode_reglement_id = $transfer->id;
        if (strpos($case, 'mentions-') === 0) $conf->global->FACTURE_RIB_NUMBER = $bankIds['A'];
    }
    if ($case === 'bank-empty') $invoice->mode_reglement_id = 0;
    if ($case === 'bank-document') $invoice->fk_account = $bankIds['B'];
    $invoice->note_public = 'Projet de démonstration. Toutes les informations proviennent de Dolibarr.';
    if (in_array($case, array('credit-note', 'bank-credit'), true)) {
        $invoice->type = 2;
        $invoice->fk_facture_source = 1;
    }
    if ($invoice->create($user) <= 0) {
        throw new RuntimeException($invoice->error);
    }
    if ($case === 'no-vat') {
        $invoice->addline('Prestation de démonstration — remise 10 %', 240, 3, 0, 0, 0, 0, 10);
    } elseif (in_array($case, array('long-invoice', 'mentions-long-pages'), true)) {
        for ($i = 1; $i <= 48; $i++) {
            $invoice->addline('Ligne '.$i.' — Prestation détaillée. '.str_repeat('Description avec accents, quantité et montant gérés par Dolibarr. ', 3), 24 + $i, 2, $i % 2 ? 20 : 5.5);
        }
    } elseif (in_array($case, array('credit-note', 'bank-credit'), true)) {
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
    foreach (array('MAIN_PDF_FORCE_FONT', 'MAIN_PDF_FORCE_FONT_SIZE', 'MAIN_PDF_NO_SENDER_FRAME', 'MAIN_PDF_NO_RECIPENT_FRAME', 'MAIN_PDF_TITLE_BACKGROUND_COLOR', 'MAIN_DOCUMENTS_LOGO_HEIGHT', 'INVOICE_CATEGORY_OF_OPERATION', 'MAIN_USE_AUTOWRAP_ON_FREETEXT', 'MAIN_PDF_FREETEXT_HEIGHT', 'PDF_FOOTER_TEXT_COLOR', 'INVOICE_FREE_TEXT', 'ORDER_FREE_TEXT', 'FACTURE_RIB_NUMBER', 'FACTURE_DRAFT_WATERMARK') as $key) {
        $snapshot[$key] = array(property_exists($conf->global, $key), $conf->global->$key ?? null);
    }
    $totals = array_map('floatval', array($invoice->total_ht, $invoice->total_tva, $invoice->total_ttc));
    $originalNote = $invoice->note_public;
    $model = new pdf_indy($db);
    if ($model->write_file($invoice, $case === 'no-vat' ? null : $langs) <= 0) {
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
    if ($invoice->note_public !== $originalNote) throw new RuntimeException('Public note was changed');
    $file = $conf->facture->multidir_output[$conf->entity].'/SPECIMEN.pdf';
    if (!is_file($file) || !copy($file, $out.'/'.$case.'.pdf')) {
        throw new RuntimeException('Generated PDF missing: '.$file);
    }
    $manifest[$case] = array('id' => $invoice->id, 'ht' => $totals[0], 'vat' => $totals[1], 'ttc' => $totals[2], 'lines' => count($invoice->lines));
}
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/indyinvoice/core/modules/commande/doc/pdf_indyorder.modules.php';
$conf->global->INDYINVOICE_PAPER_COLOR = 'FFFFFF';
$conf->global->INDYINVOICE_TABLE_COLOR = 'FCEBD8';
$conf->global->INDYINVOICE_BANNER_COLOR = 'FCEBD8';
$conf->global->INDYINVOICE_USE_BANNER_IMAGE = 0;
$conf->global->INDYINVOICE_FONT = 'indyinter';
$conf->global->INDYINVOICE_TEXT_COLOR = '27333E';
$conf->global->INDYINVOICE_FOOTER_TEXT_COLOR = '5B6770';
foreach (array('HEADER', 'TABLE', 'TOTAL') as $zone) $conf->global->{'INDYINVOICE_'.$zone.'_TEXT_AUTO'} = 1;
$conf->global->PDF_ALLOW_HTML_FOR_FREE_TEXT = 0;
$conf->global->FACTURE_RIB_NUMBER = $bankIds['A'];
foreach (array('order', 'order-shared', 'order-own', 'order-bank', 'long-order') as $case) {
    $conf->global->INDYINVOICE_PAPER_COLOR = $case === 'long-order' ? 'EAF4EE' : 'FFFFFF';
    $conf->global->ORDER_FREE_TEXT = $case === 'order-own' ? 'Données fictives — MENTION COMMANDE PRIORITAIRE.' : '';
    $conf->global->INDYINVOICE_USE_INVOICE_TEXT_FOR_ORDERS = in_array($case, array('order-shared', 'order-own'), true) ? 1 : 0;
    $conf->global->INVOICE_FREE_TEXT = 'Données fictives — MENTION FACTURE PARTAGÉE.';
    $conf->global->INDYINVOICE_SHOW_BANK_DETAILS = $case === 'order-bank' ? 1 : 0;
    $order = new Commande($db); $order->fetch(1); $order->fetch_thirdparty(); $order->fetch_lines();
    if ($case === 'long-order') {
        for ($i=1; $i<=35; $i++) $order->addline('Commande ligne '.$i.' - Description détaillée pour vérifier les pages.',30+$i,2,20);
        $order->fetch(1); $order->fetch_thirdparty(); $order->fetch_lines();
    }
    $order->specimen=1; $order->note_public='Données fictives : commande de démonstration.';
    $order->mode_reglement_code = 'CB';
    $originalOrderText = $conf->global->ORDER_FREE_TEXT;
    $totals=array_map('floatval',array($order->total_ht,$order->total_tva,$order->total_ttc));
    $model=new pdf_indyorder($db);
    if ($model->write_file($order,$langs)<=0) throw new RuntimeException($model->error);
    $file=$conf->commande->multidir_output[$conf->entity].'/SPECIMEN.pdf';
    if (!copy($file,$out.'/'.$case.'.pdf')) throw new RuntimeException('Order PDF missing');
    if ($totals!==array_map('floatval',array($order->total_ht,$order->total_tva,$order->total_ttc))) throw new RuntimeException('Order totals changed');
    if ($conf->global->ORDER_FREE_TEXT !== $originalOrderText) throw new RuntimeException('Native order free text was changed');
    $manifest[$case]=array('ht'=>$totals[0],'vat'=>$totals[1],'ttc'=>$totals[2],'lines'=>count($order->lines));
}
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "PASS: ".count($manifest)." native invoice/order PDF scenarios, totals and global PDF settings preserved.\n";
