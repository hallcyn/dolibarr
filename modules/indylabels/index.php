<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
$loaded = false;
foreach (array($_SERVER['CONTEXT_DOCUMENT_ROOT'] ?? '', $_SERVER['DOCUMENT_ROOT'] ?? '', dirname(__DIR__), dirname(__DIR__, 2)) as $root) {
    if ($root && is_readable(rtrim($root, '/').'/main.inc.php')) { require rtrim($root, '/').'/main.inc.php'; $loaded = true; break; }
}
if (!$loaded) { http_response_code(500); exit('Dolibarr bootstrap unavailable'); }
require_once __DIR__.'/lib/labels.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
if (!isModEnabled('indylabels') || !empty($user->socid) || !$user->hasRight('indylabels', 'read') || !$user->hasRight('societe', 'lire')) accessforbidden();
$langs->loadLangs(array('companies', 'indylabels@indylabels'));
$action = GETPOST('action', 'aZ09');
$ids = GETPOST('thirdparties', 'array:int');
$start = GETPOSTISSET('start') ? GETPOSTINT('start') : 1;
$recipients = array(); $error = ''; $pdf = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('preview', 'download'), true)) {
    try {
        if ($start < 1 || $start > 16) throw new InvalidArgumentException($langs->trans('IndyLabelsInvalidStart'));
        $recipients = indyLabelsRecipients($db, $user, $ids);
        $pdf = indyLabelsPdf($recipients, $start); // Validate every line before sending PDF headers.
        if ($action === 'download') {
            $pdf->Output('etiquettes-enveloppes-105x37.pdf', 'D');
            exit;
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
llxHeader('', $langs->trans('IndyLabelsTitle'), '', '', 0, 0,
    array('/indylabels/js/labels.js'), array('/indylabels/css/labels.css'));
print load_fiche_titre($langs->trans('IndyLabelsTitle'), '', 'barcode');
print '<main class="indy-labels"><p>'.$langs->trans('IndyLabelsIntro').'</p>';
print '<div class="info">'.$langs->trans('IndyLabelsPrintHelp').'</div>';
if ($error !== '') print '<div class="error" role="alert">'.dol_escape_htmltag($error).'</div>';
print '<form method="post" id="indy-labels-form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<section class="indy-labels-panel"><h2>'.$langs->trans('IndyLabelsChoose').'</h2><label for="thirdparties">'.$langs->trans('IndyLabelsThirdparties').'</label>';
$form = new Form($db);
// Direct native list supports multiselect even when single-company AJAX search is enabled.
print $form->select_thirdparty_list($ids, 'thirdparties', '', '', 0, 0, array(), '', 0, 0, 'widthcentpercent', '', true);
print '<div class="indy-labels-actions"><button type="button" id="indy-labels-all" class="button">'.$langs->trans('IndyLabelsAll').'</button>';
print '<button type="button" id="indy-labels-clear" class="button">'.$langs->trans('IndyLabelsClear').'</button></div>';
print '<p class="opacitymedium">'.$langs->trans('IndyLabelsLimit').'</p>';
print '<label for="start">'.$langs->trans('IndyLabelsFirst').'</label><select id="start" name="start">';
for ($i = 1; $i <= 16; $i++) print '<option value="'.$i.'"'.($start === $i ? ' selected' : '').'>'.$i.' — '.(intdiv($i - 1, 2) + 1).' / '.(($i % 2) ? $langs->trans('IndyLabelsLeft') : $langs->trans('IndyLabelsRight')).'</option>';
print '</select><p class="opacitymedium">'.$langs->trans('IndyLabelsOrder').'</p>';
print '<p id="indy-labels-count" role="status" aria-live="polite"></p>';
print '<div class="indy-labels-actions"><button class="button" type="submit" name="action" value="preview">'.$langs->trans('IndyLabelsPreview').'</button>';
print '<button class="button button-save" type="submit" name="action" value="download">'.$langs->trans('IndyLabelsDownload').'</button></div></section></form>';
if ($recipients && !$error) {
    print '<section class="indy-labels-panel"><h2>'.$langs->trans('IndyLabelsPreview').'</h2>';
    print '<p>'.count($recipients).' '.$langs->trans('IndyLabelsSelected').' · '.ceil((count($recipients) + $start - 1) / 16).' page(s)</p><div class="indy-labels-preview">';
    foreach ($recipients as $recipient) {
        print '<article class="indy-envelope-label">';
        foreach ($recipient['lines'] as $i => $line) print ($i === 0 ? '<strong>' : '<span>').dol_escape_htmltag($line).($i === 0 ? '</strong>' : '</span>');
        print '</article>';
    }
    print '</div></section>';
}
print '</main>'; llxFooter(); $db->close();
