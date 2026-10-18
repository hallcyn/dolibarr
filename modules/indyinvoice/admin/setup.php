<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
$loaded = false;
foreach (array($_SERVER['CONTEXT_DOCUMENT_ROOT'] ?? '', $_SERVER['DOCUMENT_ROOT'] ?? '', dirname(__DIR__, 2), dirname(__DIR__, 3)) as $root) {
    if ($root && is_readable(rtrim($root, '/').'/main.inc.php')) {
        require rtrim($root, '/').'/main.inc.php';
        $loaded = true;
        break;
    }
}
if (!$loaded) { http_response_code(500); exit('Dolibarr bootstrap unavailable'); }
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
if (!$user->admin || !isModEnabled('indyinvoice')) accessforbidden();
$langs->loadLangs(array('admin', 'bills', 'orders', 'indyinvoice@indyinvoice'));
$fields = array(
    'INDYINVOICE_BANNER_COLOR' => array('color', 'FCEBD8'),
    'INDYINVOICE_TABLE_COLOR' => array('color', 'FCEBD8'),
    'INDYINVOICE_ACCENT_COLOR' => array('color', 'D82B68'),
    'INDYINVOICE_PAPER_COLOR' => array('color', 'FFFFFF'),
    'INDYINVOICE_BANNER_HEIGHT' => array('height', '32'),
    'INDYINVOICE_FONT' => array('font', 'indyinter'),
    'INDYINVOICE_FONT_SIZE' => array('size', '11'),
    'INDYINVOICE_LOGO_HEIGHT' => array('logo', '18'),
    'INDYINVOICE_TABLE_STYLE' => array('style', 'light'),
);
$defaults = array(
    'make_default' => array('FACTURE_ADDON_PDF', 'indy', 'sponge', 'INDYINVOICE_PREVIOUS_MODEL', 'invoice'),
    'make_default_order' => array('COMMANDE_ADDON_PDF', 'indyorder', 'eratosthene', 'INDYINVOICE_PREVIOUS_ORDER_MODEL', 'order'),
);
// Dolibarr's order constant is COMMANDE_ADDON_PDF (independent of invoices).
$fontOptions = array('indyinter' => 'Inter (latin)', 'helvetica' => 'Helvetica', 'dejavusans' => 'DejaVu Sans (Unicode)', 'times' => 'Times', 'dejavuserif' => 'DejaVu Serif (Unicode)');
$styleOptions = array('light' => $langs->trans('IndyTableLight'), 'grid' => $langs->trans('IndyTableGrid'));
$values = array();
foreach ($fields as $key => $definition) $values[$key] = getDolGlobalString($key, $definition[1]);
$banner = DOL_DATA_ROOT.'/indyinvoice/banner-'.((int) $conf->entity).'.png';
$useBanner = getDolGlobalInt('INDYINVOICE_USE_BANNER_IMAGE') && is_readable($banner);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('action', 'aZ09') === 'save') {
    $valid = true;
    foreach ($fields as $key => $definition) {
        $value = GETPOST($key, 'alphanohtml');
        $type = $definition[0];
        if ($type === 'color') {
            $value = strtoupper(ltrim($value, '#'));
            $ok = preg_match('/^[A-F0-9]{6}$/', $value);
            if ($ok && in_array($key, array('INDYINVOICE_BANNER_COLOR', 'INDYINVOICE_PAPER_COLOR'), true)) {
                // Native address/reference text uses dark ink. Keep its contrast.
                $lightness = (hexdec(substr($value, 0, 2))*0.2126 + hexdec(substr($value, 2, 2))*0.7152 + hexdec(substr($value, 4, 2))*0.0722)/255;
                $ok = $lightness >= 0.72;
            }
        } elseif ($type === 'font') $ok = isset($fontOptions[$value]);
        elseif ($type === 'style') $ok = isset($styleOptions[$value]);
        else {
            $ranges = array('height' => array(0,35), 'size' => array(9,12), 'logo' => array(8,24));
            $ok = ctype_digit($value) && (int) $value >= $ranges[$type][0] && (int) $value <= $ranges[$type][1];
        }
        $valid = $valid && $ok;
        $values[$key] = $value;
    }
    $imageBytes = null;
    $upload = $_FILES['banner_image'] ?? array('error' => UPLOAD_ERR_NO_FILE);
    if ($upload['error'] !== UPLOAD_ERR_NO_FILE) {
        $ok = $upload['error'] === UPLOAD_ERR_OK && $upload['size'] <= 2*1024*1024 && is_uploaded_file($upload['tmp_name']);
        $info = $ok ? @getimagesize($upload['tmp_name']) : false;
        $ok = $ok && $info && in_array($info[2], array(IMAGETYPE_PNG, IMAGETYPE_JPEG), true) && $info[0] <= 6000 && $info[1] <= 2000;
        if ($ok) {
            $source = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($upload['tmp_name']) : @imagecreatefromjpeg($upload['tmp_name']);
            $ok = (bool) $source;
            if ($ok) {
                $target = imagecreatetruecolor(1680,160);
                $white = imagecolorallocate($target,255,255,255); imagefill($target,0,0,$white);
                $scale = max(1680/$info[0],160/$info[1]);
                $sw = 1680/$scale; $sh = 160/$scale;
                imagecopyresampled($target,$source,0,0,(int)(($info[0]-$sw)/2),(int)(($info[1]-$sh)/2),1680,160,(int)$sw,(int)$sh);
                ob_start(); imagepng($target); $imageBytes = ob_get_clean();
                imagedestroy($source); imagedestroy($target);
            }
        }
        $valid = $valid && $ok;
    }
    $useBanner = ($imageBytes !== null || (GETPOSTINT('use_banner') === 1 && is_readable($banner)));
    if (!$valid) setEventMessages($langs->trans('IndyInvalidSettings'), null, 'errors');
    else {
        $db->begin(); $ok = true;
        foreach ($values as $key => $value) $ok = dolibarr_set_const($db,$key,$value,'chaine',0,'',$conf->entity) > 0 && $ok;
        $ok = dolibarr_set_const($db,'INDYINVOICE_USE_BANNER_IMAGE',$useBanner ? '1' : '0','chaine',0,'',$conf->entity) > 0 && $ok;
        foreach ($defaults as $checkbox => $model) {
            if ($checkbox === 'make_default_order' && !isModEnabled('commande')) continue;
            if (GETPOSTINT($checkbox) === 1) {
                if (getDolGlobalString($model[0]) !== $model[1]) $ok = dolibarr_set_const($db,$model[3],getDolGlobalString($model[0],$model[2]),'chaine',0,'',$conf->entity) > 0 && $ok;
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom,type,entity) SELECT '".$model[1]."','".$model[4]."',".((int)$conf->entity)." WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom='".$model[1]."' AND type='".$model[4]."' AND entity=".((int)$conf->entity).")";
                $ok = (bool)$db->query($sql) && $ok;
                $ok = dolibarr_set_const($db,$model[0],$model[1],'chaine',0,'',$conf->entity) > 0 && $ok;
            } elseif (getDolGlobalString($model[0]) === $model[1]) {
                $previous = getDolGlobalString($model[3],$model[2]);
                $res = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."document_model WHERE type='".$model[4]."' AND entity=".((int)$conf->entity)." AND nom='".$db->escape($previous)."'");
                if (!$res || !$db->num_rows($res)) $previous = $model[2];
                $ok = dolibarr_set_const($db,$model[0],$previous,'chaine',0,'',$conf->entity) > 0 && $ok;
            }
        }
        if ($imageBytes !== null && $ok) {
            dol_mkdir(dirname($banner));
            $temp = tempnam(dirname($banner),'banner-');
            $ok = $temp && file_put_contents($temp,$imageBytes) !== false && rename($temp,$banner);
            if ($temp && file_exists($temp)) unlink($temp);
            if ($ok) chmod($banner,0640);
        }
        if ($ok) { $db->commit(); header('Location: '.dol_buildpath('/indyinvoice/admin/setup.php',1).'?saved=1'); exit; }
        $db->rollback(); setEventMessages($langs->trans('Error'),null,'errors');
    }
}
llxHeader('', $langs->trans('IndyInvoiceSettings'));
print '<link rel="stylesheet" href="'.dol_buildpath('/indyinvoice/css/settings.css',1).'?v=110">';
print load_fiche_titre($langs->trans('IndyInvoiceSettings'), '', 'bill');
if (GETPOSTINT('saved') === 1) print '<div class="ok">'.$langs->trans('SetupSaved').'</div>';
print '<p>'.$langs->trans('IndyInvoiceScope').'</p>';
print '<p><a href="'.DOL_URL_ROOT.'/admin/company.php">'.$langs->trans('IndyCompanySettings').'</a> · <a href="'.DOL_URL_ROOT.'/admin/invoice.php">'.$langs->trans('IndyInvoiceNativeSettings').'</a> · <a href="'.DOL_URL_ROOT.'/admin/order.php">'.$langs->trans('IndyOrderNativeSettings').'</a></p>';
print '<form id="indy-invoice-settings" method="post" enctype="multipart/form-data" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<div class="indy-document-layout"><div class="indy-document-options"><div class="tabBar">';
print '<h2>'.$langs->trans('IndyChoosePalette').'</h2><div class="indy-palettes">';
foreach (array('cream'=>'Crème','rose'=>'Rose','sage'=>'Sauge','clay'=>'Terracotta','minimal'=>'Minimal') as $id=>$label) print '<button type="button" class="indy-palette" data-palette="'.$id.'">'.$label.'</button>';
print '</div><div class="indy-document-fields">';
foreach ($fields as $key => $definition) {
    print '<div class="indy-document-field"><label for="'.$key.'">'.$langs->trans($key).'</label>';
    $value = dol_escape_htmltag($values[$key]);
    if ($definition[0] === 'color') print '<input type="color" name="'.$key.'" id="'.$key.'" value="#'.$value.'">';
    elseif ($definition[0] === 'font' || $definition[0] === 'style') {
        print '<select name="'.$key.'" id="'.$key.'">';
        foreach ($definition[0] === 'font' ? $fontOptions : $styleOptions as $id=>$label) print '<option value="'.$id.'"'.($values[$key] === $id ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
        print '</select>';
    } else {
        $ranges=array('height'=>array(0,35),'size'=>array(9,12),'logo'=>array(8,24));
        print '<input type="number" min="'.$ranges[$definition[0]][0].'" max="'.$ranges[$definition[0]][1].'" name="'.$key.'" id="'.$key.'" value="'.$value.'">';
    }
    print '</div>';
}
print '</div><p class="opacitymedium">'.$langs->trans('IndyLightBackgroundHelp').'</p>';
print '<h2>'.$langs->trans('IndyBannerImage').'</h2><label for="banner_image">'.$langs->trans('IndyBannerUploadHelp').'</label><p><input type="file" id="banner_image" name="banner_image" accept="image/png,image/jpeg"></p>';
print '<p><label><input type="checkbox" name="use_banner" value="1"'.($useBanner?' checked':'').'> '.$langs->trans('IndyUseBanner').'</label></p>';
print '<h2>'.$langs->trans('IndyDefaultModels').'</h2>';
foreach ($defaults as $checkbox=>$model) {
    if ($checkbox === 'make_default_order' && !isModEnabled('commande')) continue;
    print '<p><label><input type="checkbox" name="'.$checkbox.'" value="1"'.(getDolGlobalString($model[0]) === $model[1]?' checked':'').'> '.$langs->trans($checkbox === 'make_default'?'IndyMakeDefault':'IndyMakeOrderDefault').'</label></p>';
    print '<p class="opacitymedium">'.$langs->trans('IndyCurrentModel').' : <strong>'.dol_escape_htmltag(getDolGlobalString($model[0],$model[2])).'</strong></p>';
}
print '<p>'.$langs->trans('IndyInvoiceExistingPDF').'</p><input type="submit" class="button" value="'.$langs->trans('Save').'"></div></div>';
print '<aside class="indy-document-preview"><h2>'.$langs->trans('IndyStylePreview').'</h2><div class="indy-paper"><img class="indy-preview-photo" alt=""'.($useBanner?' src="'.dol_buildpath('/indyinvoice/admin/banner.php',1).'"':' hidden').'><div class="indy-preview-header"><b>SOCIÉTÉ</b><strong>Facture EXEMPLE</strong></div><div class="indy-preview-body"><div class="indy-preview-addresses"><p>Société de démonstration<br>Coordonnées Dolibarr</p><p>Client de démonstration<br>Adresse de facturation</p></div><div class="indy-preview-table"><b>Désignation</b><b>Qté</b><b>Total HT</b><span>Prestation de démonstration</span><span>1</span><span>120,00 €</span><span>Autre prestation</span><span>2</span><span>80,00 €</span></div><div class="indy-preview-totals">Total HT <b>200,00 €</b><br>TVA <b>40,00 €</b><hr>Total TTC <b>240,00 €</b></div><p class="indy-preview-footer">Conditions et mentions issues de Dolibarr</p></div></div><p class="opacitymedium">'.$langs->trans('IndyPreviewHelp').'</p>';
print '<p><a class="butAction" href="'.DOL_URL_ROOT.'/admin/invoice.php?action=specimen&module=indy&token='.newToken().'">'.$langs->trans('IndyInvoicePreview').'</a></p>';
if (isModEnabled('commande')) print '<p><a class="butAction" href="'.DOL_URL_ROOT.'/admin/order.php?action=specimen&module=indyorder&token='.newToken().'">'.$langs->trans('IndyOrderPreview').'</a></p>';
print '</aside></div></form><p>'.$langs->trans('IndyFontHelp').'</p>';
print '<script nonce="'.getNonce().'" src="'.dol_buildpath('/indyinvoice/js/settings.js',1).'?v=110"></script>';
llxFooter(); $db->close();
