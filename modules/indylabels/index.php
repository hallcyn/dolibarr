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
$start = GETPOSTISSET('start') ? GETPOSTINT('start') : 1;
$padding = GETPOSTISSET('padding') ? str_replace(',', '.', GETPOST('padding', 'alphanohtml')) : 10;
$year = GETPOSTISSET('group_year') ? GETPOSTINT('group_year') : (int) dol_print_date(dol_now(), '%Y') - 1;
$groupMode = GETPOST('group_mode', 'aZ09') ?: 'last_order_year';
$category = GETPOSTINT('category');
$skipIncomplete = GETPOSTINT('skip_incomplete');
$recipients = array(); $ids = array(); $error = ''; $notice = ''; $pdf = null; $excluded = 0;
try {
    $ids = indyLabelsParseSelection(GETPOST('selection_ids', 'alphanohtml'), GETPOST('thirdparties', 'array:int'));
    // Legacy/no-JS form submissions must never silently turn a large selection into a partial export.
    if (!GETPOST('selection_ids', 'alphanohtml') && count($ids) >= max(1, (int) ini_get('max_input_vars') - 20)) throw new InvalidArgumentException($langs->trans('IndyLabelsEnableJS'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('replace_group','add_group'), true)) {
        $group = indyLabelsGroup($db, $user, $groupMode, $year, $category);
        $ids = $action === 'replace_group' ? $group : indyLabelsParseSelection('', array_merge($ids, $group));
        $notice = $langs->trans('IndyLabelsGroupResult', count($group), count($ids));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, array('preview', 'download'), true)) {
        if ($start < 1 || $start > 16) throw new InvalidArgumentException($langs->trans('IndyLabelsInvalidStart'));
        if (!is_numeric($padding) || $padding < 5 || $padding > 20) throw new InvalidArgumentException($langs->trans('IndyLabelsInvalidPadding'));
        $recipients = indyLabelsRecipients($db, $user, $ids);
        $incomplete = array_filter($recipients, function ($r) { return !$r['complete']; });
        if ($skipIncomplete) {
            $excluded = count($incomplete);
            $recipients = array_values(array_filter($recipients, function ($r) { return $r['complete']; }));
            if ($excluded) $notice = $langs->trans('IndyLabelsExcluded', $excluded);
        } elseif ($incomplete) {
            $error = $langs->trans('IndyLabelsIncompleteCount', count($incomplete));
        }
        if (!$error) $pdf = indyLabelsPdf($recipients, $start, (float) $padding);
        if ($action === 'download' && !$error) {
            $pdf->Output('etiquettes-enveloppes-105x37.pdf', 'D');
            exit;
        }
    }
    $categories = indyLabelsCategories($db);
} catch (Throwable $e) { $error = $e->getMessage(); $categories = array(); }
llxHeader('', $langs->trans('IndyLabelsTitle'), '', '', 0, 0,
    array('/indylabels/js/labels.js?v=1.1.0'), array('/indylabels/css/labels.css?v=1.1.0'));
print load_fiche_titre($langs->trans('IndyLabelsTitle'), '', 'barcode');
print '<main class="indy-labels"><p>'.$langs->trans('IndyLabelsIntro').'</p>';
print '<div class="info">'.$langs->trans('IndyLabelsPrintHelp').'</div>';
if ($notice !== '') print '<div class="ok" role="status">'.dol_escape_htmltag($notice).'</div>';
if ($error !== '') print '<div class="error" role="alert">'.dol_escape_htmltag($error).'</div>';
print '<form method="post" id="indy-labels-form" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="selection_ids" id="selection_ids" value="">';
print '<section class="indy-labels-panel"><h2>'.$langs->trans('IndyLabelsGroups').'</h2><div class="indy-labels-group-grid">';
print '<div><label for="group_mode">'.$langs->trans('IndyLabelsGroupMode').'</label><select id="group_mode" name="group_mode">';
foreach (array('last_order_year'=>'IndyLabelsLastOrderYear','orders_in_year'=>'IndyLabelsOrdersInYear','all'=>'IndyLabelsAny') as $value=>$label) print '<option value="'.$value.'"'.($groupMode === $value ? ' selected' : '').'>'.$langs->trans($label).'</option>';
print '</select></div><div><label for="group_year">'.$langs->trans('IndyLabelsYear').'</label><input type="number" id="group_year" name="group_year" min="1900" max="2100" value="'.((int) $year).'"></div>';
if ($categories) {
    print '<div><label for="category">'.$langs->trans('IndyLabelsCategory').'</label><select id="category" name="category"><option value="0">'.$langs->trans('IndyLabelsAllCategories').'</option>';
    foreach ($categories as $id=>$label) print '<option value="'.$id.'"'.($category === $id ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
    print '</select></div>';
}
print '</div><p class="opacitymedium">'.$langs->trans('IndyLabelsHistoryHelp').'</p><div class="indy-labels-actions">';
print '<button class="button" type="submit" name="action" value="replace_group">'.$langs->trans('IndyLabelsReplaceGroup').'</button>';
print '<button class="button" type="submit" name="action" value="add_group">'.$langs->trans('IndyLabelsAddGroup').'</button></div></section>';
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
print '<label for="padding">'.$langs->trans('IndyLabelsPadding').'</label><input type="number" id="padding" name="padding" min="5" max="20" step="0.5" value="'.dol_escape_htmltag((string) $padding).'">';
print '<p class="opacitymedium">'.$langs->trans('IndyLabelsPaddingHelp').'</p>';
print '<label class="indy-labels-checkbox"><input type="checkbox" name="skip_incomplete" value="1"'.($skipIncomplete ? ' checked' : '').'> '.$langs->trans('IndyLabelsSkipIncomplete').'</label>';
print '<p id="indy-labels-count" role="status" aria-live="polite"></p>';
print '<div class="indy-labels-actions"><button class="button" type="submit" name="action" value="preview">'.$langs->trans('IndyLabelsPreview').'</button>';
print '<button class="button button-save" type="submit" name="action" value="download">'.$langs->trans('IndyLabelsDownload').'</button></div></section></form>';
if ($recipients) {
    print '<section class="indy-labels-panel"><h2>'.$langs->trans('IndyLabelsPreview').'</h2>';
    print '<p>'.count($recipients).' '.$langs->trans('IndyLabelsSelected').' · '.ceil((count($recipients) + $start - 1) / 16).' page(s)</p><div class="indy-labels-preview">';
    foreach ($recipients as $recipient) {
        print '<article class="indy-envelope-label'.(!$recipient['complete'] ? ' indy-labels-incomplete' : '').'">';
        foreach ($recipient['lines'] as $i => $line) print ($i === 0 ? '<strong>' : '<span>').dol_escape_htmltag($line).($i === 0 ? '</strong>' : '</span>');
        if (!$recipient['complete']) print '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$recipient['id'].'&action=edit" target="_blank" rel="noopener">'.$langs->trans('IndyLabelsCorrectAddress').'</a>';
        print '</article>';
    }
    print '</div></section>';
}
print '</main>'; llxFooter(); $db->close();
