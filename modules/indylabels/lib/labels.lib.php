<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/indyinvoice/lib/recipient.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

function indyLabelsError($key, $value = '')
{
    global $langs;
    return $langs->trans($key, $value);
}

function indyLabelsNormalize($value)
{
    return trim(preg_replace('/\s+/u', ' ', (string) $value));
}

/** Preserve address lines from both plain text and imported HTML breaks. */
function indyLabelsAddressLines($value)
{
    $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $text);
    $text = strip_tags($text);
    return array_values(array_filter(array_map('indyLabelsNormalize', preg_split('/\r\n|\r|\n/', $text)), static function ($line) { return $line !== ''; }));
}

/** Native entity and salesperson restrictions, shared by manual and group selection. */
function indyLabelsScope($user)
{
    if (!empty($user->socid) || !$user->hasRight('societe', 'lire')) throw new RuntimeException(indyLabelsError('IndyLabelsErrorAccess'));
    $where = 's.entity IN ('.getEntity('societe').')';
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $where .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc=s.rowid AND sc.fk_user='.((int) $user->id).')';
    }
    return $where;
}

/** Compact POST representation avoids PHP max_input_vars truncating large selections. */
function indyLabelsParseSelection($compact, $fallback = array())
{
    if ($compact !== '') {
        if (!is_string($compact) || !preg_match('/^[0-9]+(?:,[0-9]+)*$/D', $compact)) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorSelection'));
        $fallback = explode(',', $compact);
    }
    if (!is_array($fallback) || count($fallback) > 10000) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorSelection'));
    $ids = array_values(array_unique(array_map('intval', $fallback)));
    if (count($ids) > 5000 || ($ids && min($ids) <= 0)) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorSelection'));
    return $ids;
}

/** Read just address data in one query; do not evaluate unrelated computed extrafields 800 times. */
function indyLabelsRecipients($db, $user, $ids)
{
    $scope = indyLabelsScope($user);
    $ids = indyLabelsParseSelection('', $ids);
    if (!$ids) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorSelection'));
    $sql = 'SELECT s.rowid,s.nom,s.address,s.zip,s.town,ef.destinataire_facturation AS recipient';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'societe s LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields ef ON ef.fk_object=s.rowid';
    $sql .= ' WHERE '.$scope.' AND s.rowid IN ('.implode(',', $ids).') ORDER BY s.nom,s.rowid';
    $res = $db->query($sql);
    if (!$res) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLoad'));
    $result = array();
    while ($row = $db->fetch_object($res)) {
        $name = trim((string) $row->recipient);
        $addressLines = indyLabelsAddressLines($row->address);
        $result[] = array('id'=>(int) $row->rowid, 'name'=>$row->nom,
            'lines'=>array_merge(array(indyLabelsNormalize($name !== '' ? $name : $row->nom)), $addressLines, array(indyLabelsNormalize($row->zip.' '.$row->town))),
            'complete'=>count($addressLines) > 0 && trim((string) $row->zip) !== '' && trim((string) $row->town) !== '');
    }
    if (count($result) !== count($ids)) throw new RuntimeException(indyLabelsError('IndyLabelsErrorUnavailable'));
    return $result;
}

function indyLabelsCategories($db)
{
    if (!isModEnabled('categorie')) return array();
    $res = $db->query('SELECT rowid,label FROM '.MAIN_DB_PREFIX.'categorie WHERE type=2 AND entity IN ('.getEntity('category').') ORDER BY label,rowid');
    if (!$res) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLoad'));
    $result = array(); while ($row = $db->fetch_object($res)) $result[(int) $row->rowid] = $row->label;
    return $result;
}

/** Real latest order takes priority; manual date/year is used only without a real dated order. */
function indyLabelsManualYear($value, $type)
{
    if (in_array($type, array('date','datetime'), true) && preg_match('/^([0-9]{4})-[0-9]{2}-[0-9]{2}/', (string) $value, $m)) return (int) $m[1];
    if (in_array($type, array('int','double','varchar'), true) && is_numeric($value) && (float) $value == (int) $value && (int) $value >= 1000 && (int) $value <= 9999) return (int) $value;
    return null;
}

/** Year filters use validated/in-progress/closed orders, excluding drafts and cancellations. */
function indyLabelsGroup($db, $user, $mode, $year, $category = 0)
{
    $scope = indyLabelsScope($user);
    if (!in_array($mode, array('last_order_year','orders_in_year','all'), true) || ($mode !== 'all' && ($year < 1900 || $year > 2100))) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorYear'));
    $category = (int) $category;
    if ($category < 0 || ($category > 0 && !isset(indyLabelsCategories($db)[$category]))) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorCategory'));
    if ($category > 0) $scope .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'categorie_societe cs WHERE cs.fk_soc=s.rowid AND cs.fk_categorie='.$category.')';
    $manualColumn = 'NULL AS manual'; $manualType = null;
    if ($mode === 'last_order_year') {
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        $fields = new ExtraFields($db); $fields->fetch_name_optionals_label('societe', true);
        $manualType = $fields->attributes['societe']['type']['date_derniere_commande_manuelle'] ?? null;
        if ($manualType && empty($fields->attributes['societe']['computed']['date_derniere_commande_manuelle'])) $manualColumn = 'ef.date_derniere_commande_manuelle AS manual';
    }
    $sql = 'SELECT s.rowid,'.$manualColumn;
    if ($mode === 'last_order_year') $sql .= ',latest.last_date';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'societe s';
    if ($mode === 'last_order_year') {
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields ef ON ef.fk_object=s.rowid';
        $sql .= ' LEFT JOIN (SELECT fk_soc,MAX(date_commande) AS last_date FROM '.MAIN_DB_PREFIX.'commande WHERE entity IN ('.getEntity('commande').') AND fk_statut > 0 GROUP BY fk_soc) latest ON latest.fk_soc=s.rowid';
    }
    $sql .= ' WHERE '.$scope;
    if ($mode === 'orders_in_year') {
        $sql .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'commande c WHERE c.fk_soc=s.rowid AND c.entity IN ('.getEntity('commande').') AND c.fk_statut > 0';
        $sql .= " AND c.date_commande >= '".((int) $year)."-01-01 00:00:00' AND c.date_commande < '".((int) $year + 1)."-01-01 00:00:00')";
    }
    $sql .= ' ORDER BY s.nom,s.rowid';
    $res = $db->query($sql);
    if (!$res) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLoad'));
    $ids = array();
    while ($row = $db->fetch_object($res)) {
        if ($mode === 'last_order_year') {
            $lastYear = !empty($row->last_date) ? (int) substr($row->last_date,0,4) : indyLabelsManualYear($row->manual, $manualType);
            if ($lastYear !== (int) $year) continue;
        }
        $ids[] = (int) $row->rowid;
        if (count($ids) > 5000) throw new RuntimeException(indyLabelsError('IndyLabelsErrorSelection'));
    }
    return $ids;
}

/** Real A4: two 105mm columns, eight 37mm rows, 0.5mm top/bottom; no print scaling. */
function indyLabelsPdf($recipients, $start = 1, $padding = 10)
{
    if (!$recipients || $start < 1 || $start > 16 || !is_numeric($padding) || $padding < 5 || $padding > 20) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorPosition'));
    $pdf = pdf_getInstance(array(210, 297));
    $pdf->SetCreator('Dolibarr - Indy Labels'); $pdf->SetTitle('Étiquettes enveloppes 105 × 37 mm');
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0); $pdf->setCellPaddings(0, 0, 0, 0);
    $pdf->SetDisplayMode('real', 'SinglePage');
    // Ask PDF viewers to keep the real paper size; the print dialog still needs 100%.
    $pdf->setViewerPreferences(array('PrintScaling' => 'None'));
    $slot = $start - 1;
    foreach ($recipients as $i => $recipient) {
        if (empty($recipient['complete'])) throw new RuntimeException(indyLabelsError('IndyLabelsErrorAddress', $recipient['name']));
        $lineCount = count($recipient['lines']);
        if ($lineCount > 6) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLines', $recipient['name']));
        if ($i === 0 || $slot % 16 === 0) $pdf->AddPage('P', array(210, 297));
        $position = $slot % 16;
        $x = ($position % 2) * 105 + $padding;
        $width = 105 - $padding - 5;
        $y = 0.5 + intdiv($position, 2) * 37 + 11.5 - max(0, $lineCount - 3) * 2.3;
        foreach ($recipient['lines'] as $line => $text) {
            $size = $line === 0 ? 10.5 : 10;
            $style = $line === 0 ? 'B' : '';
            do {
                $pdf->SetFont('dejavusans', $style, $size);
                if ($pdf->GetStringWidth($text) <= $width) break;
                $size -= 0.25;
            } while ($size >= 6);
            if ($size < 6) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLength', $recipient['name']));
            $pdf->SetXY($x, $y + $line * 4.6);
            $pdf->Cell($width, 4.6, $text, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        }
        $slot++;
    }
    return $pdf;
}
