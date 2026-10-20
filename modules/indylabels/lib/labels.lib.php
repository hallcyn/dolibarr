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

/** Recheck every requested ID, including entity and assigned-salesperson restrictions. */
function indyLabelsRecipients($db, $user, $ids)
{
    if (!empty($user->socid) || !$user->hasRight('societe', 'lire')) throw new RuntimeException(indyLabelsError('IndyLabelsErrorAccess'));
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids || count($ids) > 500 || min($ids) <= 0) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorSelection'));
    $sql = 'SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.entity IN ('.getEntity('societe').') AND s.rowid IN ('.implode(',', $ids).')';
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $sql .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc=s.rowid AND sc.fk_user='.((int) $user->id).')';
    }
    $sql .= ' ORDER BY s.nom, s.rowid';
    $res = $db->query($sql);
    if (!$res) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLoad'));
    $allowed = array(); while ($row = $db->fetch_object($res)) $allowed[] = (int) $row->rowid;
    if (count($allowed) !== count($ids)) throw new RuntimeException(indyLabelsError('IndyLabelsErrorUnavailable'));
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $result = array();
    foreach ($allowed as $id) {
        $party = new Societe($db);
        if ($party->fetch($id) <= 0) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLoad'));
        $recipient = indyBillingRecipient($party);
        $result[] = array('id' => $id, 'name' => $party->name,
            'lines' => array(indyLabelsNormalize($recipient !== '' ? $recipient : $party->name),
                indyLabelsNormalize($party->address), indyLabelsNormalize($party->zip.' '.$party->town)),
            'complete' => trim((string) $party->address) !== '' && trim((string) $party->zip) !== '' && trim((string) $party->town) !== '');
    }
    return $result;
}

/** Real A4: two 105mm columns, eight 37mm rows, 0.5mm top/bottom; no print scaling. */
function indyLabelsPdf($recipients, $start = 1)
{
    if (!$recipients || $start < 1 || $start > 16) throw new InvalidArgumentException(indyLabelsError('IndyLabelsErrorPosition'));
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
        if ($i === 0 || $slot % 16 === 0) $pdf->AddPage('P', array(210, 297));
        $position = $slot % 16;
        $x = ($position % 2) * 105 + 5;
        $y = 0.5 + intdiv($position, 2) * 37 + 11.5;
        foreach ($recipient['lines'] as $line => $text) {
            $size = $line === 0 ? 10.5 : 10;
            $style = $line === 0 ? 'B' : '';
            do {
                $pdf->SetFont('dejavusans', $style, $size);
                if ($pdf->GetStringWidth($text) <= 95) break;
                $size -= 0.25;
            } while ($size >= 6);
            if ($size < 6) throw new RuntimeException(indyLabelsError('IndyLabelsErrorLength', $recipient['name']));
            $pdf->SetXY($x, $y + $line * 4.6);
            $pdf->Cell(95, 4.6, $text, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        }
        $slot++;
    }
    return $pdf;
}
