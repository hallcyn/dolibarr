<?php
/* Copyright (C) 2026 Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
// DOCUMENT_ROOT also works when the bundled module is reached through a symlink.
$loaded = false;
foreach (array($_SERVER['CONTEXT_DOCUMENT_ROOT'] ?? '', $_SERVER['DOCUMENT_ROOT'] ?? '', dirname(__DIR__, 3)) as $root) {
    if ($root && is_readable(rtrim($root, '/').'/main.inc.php')) {
        require rtrim($root, '/').'/main.inc.php';
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    http_response_code(500);
    exit('Dolibarr bootstrap unavailable');
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (!$user->admin || !isModEnabled('indyinvoice')) {
    accessforbidden();
}
$langs->loadLangs(array('admin', 'bills', 'indyinvoice@indyinvoice'));
$action = GETPOST('action', 'aZ09');
$fields = array(
    'INDYINVOICE_BANNER_COLOR' => array('color', 'FCEBD8'),
    'INDYINVOICE_TABLE_COLOR' => array('color', 'FCEBD8'),
    'INDYINVOICE_BANNER_HEIGHT' => array('height', '32'),
    'INDYINVOICE_FONT' => array('font', 'indyinter'),
    'INDYINVOICE_FONT_SIZE' => array('size', '11'),
    'INDYINVOICE_TABLE_STYLE' => array('style', 'light'),
);
$values = array();
foreach ($fields as $key => $definition) {
    $values[$key] = getDolGlobalString($key, $definition[1]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $valid = true;
    foreach ($fields as $key => $definition) {
        $value = GETPOST($key, 'alphanohtml');
        $type = $definition[0];
        if ($type === 'color') {
            $value = strtoupper(ltrim($value, '#'));
            $ok = preg_match('/^[A-F0-9]{6}$/', $value);
        } elseif ($type === 'font') {
            $ok = in_array($value, array('indyinter', 'helvetica', 'dejavusans'), true);
        } elseif ($type === 'style') {
            $ok = in_array($value, array('light', 'grid'), true);
        } else {
            $ok = ctype_digit($value) && ($type === 'height' ? ((int) $value <= 35) : ((int) $value >= 9 && (int) $value <= 12));
        }
        $valid = $valid && $ok;
        $values[$key] = $value;
    }
    if (!$valid) {
        setEventMessages($langs->trans('IndyInvalidSettings'), null, 'errors');
    } else {
        $db->begin();
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity) > 0 && $ok;
        }
        if (GETPOSTINT('make_default') === 1) {
            if (getDolGlobalString('FACTURE_ADDON_PDF') !== 'indy') {
                $ok = dolibarr_set_const($db, 'INDYINVOICE_PREVIOUS_MODEL', getDolGlobalString('FACTURE_ADDON_PDF', 'sponge'), 'chaine', 0, '', $conf->entity) > 0 && $ok;
            }
            $ok = dolibarr_set_const($db, 'FACTURE_ADDON_PDF', 'indy', 'chaine', 0, '', $conf->entity) > 0 && $ok;
        }
        if ($ok) {
            $db->commit();
            header('Location: '.dol_buildpath('/indyinvoice/admin/setup.php', 1).'?saved=1');
            exit;
        }
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}
llxHeader('', $langs->trans('IndyInvoiceSettings'));
print '<style>@media(max-width:800px){#indy-invoice-settings table,#indy-invoice-settings tbody{display:block;width:100%}#indy-invoice-settings tr{display:flex;flex-direction:column;height:auto!important}#indy-invoice-settings td{display:block;box-sizing:border-box;width:100%!important;min-width:0;max-width:100%;height:auto!important;padding:8px 0}#indy-invoice-settings input,#indy-invoice-settings select{box-sizing:border-box;max-width:100%}}</style>';
print load_fiche_titre($langs->trans('IndyInvoiceSettings'), '', 'bill');
if (GETPOSTINT('saved') === 1) {
    print '<div class="ok">'.$langs->trans('SetupSaved').'</div>';
}
print '<p>'.$langs->trans('IndyInvoiceScope').'</p>';
print '<p><a href="'.DOL_URL_ROOT.'/admin/company.php">'.$langs->trans('IndyCompanySettings').'</a> · ';
print '<a href="'.DOL_URL_ROOT.'/admin/invoice.php">'.$langs->trans('IndyInvoiceNativeSettings').'</a> · ';
print '<a href="'.DOL_URL_ROOT.'/admin/pdf.php">'.$langs->trans('IndyPDFSettings').'</a></p>';
print '<form id="indy-invoice-settings" method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<div class="tabBar"><table class="border centpercent tableforfield">';
foreach ($fields as $key => $definition) {
    print '<tr><td><label for="'.$key.'">'.$langs->trans($key).'</label></td><td>';
    $value = dol_escape_htmltag($values[$key]);
    if ($definition[0] === 'color') {
        print '<input type="color" name="'.$key.'" id="'.$key.'" value="#'.$value.'">';
    } elseif ($definition[0] === 'font' || $definition[0] === 'style') {
        $options = $definition[0] === 'font' ? array('indyinter' => 'Inter (latin)', 'helvetica' => 'Helvetica', 'dejavusans' => 'DejaVu Sans (Unicode)') : array('light' => $langs->trans('IndyTableLight'), 'grid' => $langs->trans('IndyTableGrid'));
        print '<select name="'.$key.'" id="'.$key.'">';
        foreach ($options as $id => $label) {
            print '<option value="'.$id.'"'.($values[$key] === $id ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
        }
        print '</select>';
    } else {
        print '<input type="number" min="'.($definition[0] === 'height' ? 0 : 9).'" max="'.($definition[0] === 'height' ? 35 : 12).'" name="'.$key.'" id="'.$key.'" value="'.$value.'">';
    }
    print '</td></tr>';
}
print '</table><p><label><input type="checkbox" name="make_default" value="1"> '.$langs->trans('IndyMakeDefault').'</label></p>';
print '<p>'.$langs->trans('IndyInvoiceExistingPDF').'</p></div>';
print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div></form>';
print '<p>'.$langs->trans('IndyFontHelp').'</p>';
llxFooter();
$db->close();
