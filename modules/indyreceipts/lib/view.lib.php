<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */

function indyReceiptEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function indyReceiptMoney($amount)
{
    global $conf, $langs;
    return indyReceiptEscape(price($amount, 0, $langs).' '.$conf->currency);
}

/** Use the same escaped invoice block on screen and in TCPDF. */
function indyReceiptBlock($invoice)
{
    global $db, $langs;
    $html = '<h3>'.indyReceiptEscape($invoice['ref']).' — '.indyReceiptEscape($invoice['customer']).'</h3>';
    $html .= '<p>'.$langs->trans('ReceiptInvoiceTotal').' · '.indyReceiptMoney($invoice['total']).'<br>';
    $html .= $langs->trans('ReceiptAllocated').' · <b>'.indyReceiptMoney($invoice['received']).'</b></p>';
    $html .= '<table class="noborder centpercent" cellpadding="4"><thead><tr>';
    $html .= '<th width="78%">'.$langs->trans('ReceiptItem').'</th><th width="22%">'.$langs->trans('ReceiptQuantity').'</th></tr></thead><tbody>';
    foreach ($invoice['lines'] as $line) {
        $description = trim(dol_string_nohtmltag((string) $line->description));
        $name = $line->label ?: ($line->product_label ?: $description);
        $item = trim(($line->ref ? $line->ref.' · ' : '').$name);
        $html .= '<tr><td width="78%">'.indyReceiptEscape($item).'</td><td width="22%">'.indyReceiptEscape(price2num($line->qty)).'</td></tr>';
    }
    $html .= '</tbody></table><br><table class="noborder centpercent" cellpadding="4"><thead><tr>';
    foreach (array('ReceiptPaymentDate', 'ReceiptMethod', 'ReceiptReference', 'ReceiptAmount') as $key) {
        $html .= '<th>'.$langs->trans($key).'</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($invoice['payments'] as $row) {
        $translation = 'PaymentType'.$row->code;
        $method = $langs->transnoentitiesnoconv($translation);
        if ($method === $translation) $method = $row->libelle ?: $row->code;
        $reference = $row->num_paiement !== null && $row->num_paiement !== '' ? $row->num_paiement : $row->payment_ref;
        $html .= '<tr><td>'.dol_print_date($db->jdate($row->datep), 'day').'</td><td>'.indyReceiptEscape($method).'</td>';
        $html .= '<td>'.indyReceiptEscape($reference).'</td><td>'.indyReceiptMoney($row->amount).'</td></tr>';
    }
    return $html.'</tbody></table>';
}

function indyReceiptSummary($start, $end, $settled, $invoices)
{
    global $langs;
    list($from, $until) = indyReceiptBounds($start, $end);
    $period = dol_print_date($from, 'day').' – '.dol_print_date($until - 1, 'day');
    $html = '<p>'.$period.($settled ? ' · '.$langs->trans('ReceiptSettled') : '').'</p>';
    $html .= '<p><strong>'.$langs->trans('ReceiptTotal').' · '.indyReceiptMoney(array_sum(array_column($invoices, 'received'))).'</strong></p>';
    if (!$invoices) $html .= '<p>'.$langs->trans('ReceiptEmpty').'</p>';
    return $html;
}
