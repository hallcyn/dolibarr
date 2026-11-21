<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require dirname(__DIR__).'/main.inc.php';
require_once __DIR__.'/lib/report.lib.php';
require_once __DIR__.'/lib/view.lib.php';

if (!isModEnabled('indyreceipts') || !isModEnabled('invoice') || $user->isExternalUser() || !$user->hasRight('facture', 'lire')) {
    accessforbidden();
}
restrictedArea($user, 'facture');
$langs->loadLangs(array('bills', 'banks', 'dict', 'indyreceipts@indyreceipts'));
$title = $langs->trans('IndyReceiptsTitle');
$today = dol_print_date(dol_now(), '%Y-%m-%d');
$start = GETPOSTISSET('date_from') ? GETPOST('date_from', 'alphanohtml') : substr($today, 0, 8).'01';
$end = GETPOSTISSET('date_to') ? GETPOST('date_to', 'alphanohtml') : $today;
$settled = GETPOSTINT('settled') === 1;
$error = '';
try {
    $invoices = indyReceiptReport($db, $user, $start, $end, $settled);
    $summary = indyReceiptSummary($start, $end, $settled, $invoices);
} catch (RuntimeException | InvalidArgumentException $exception) {
    $error = $exception->getMessage();
}

if ($error === '' && GETPOST('format', 'aZ09') === 'pdf') {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
    $pdf = pdf_getInstance(array(210, 297));
    $pdf->SetCreator('Dolibarr');
    $pdf->SetTitle($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(14, 14, 14);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->AddPage();
    $pdf->writeHTML('<h1>'.$title.'</h1>'.$summary);
    foreach ($invoices as $invoice) {
        if ($pdf->GetY() > 235) $pdf->AddPage();
        $pdf->writeHTML(indyReceiptBlock($invoice));
    }
    $pdf->Output('encaissements-'.$start.'_'.$end.'.pdf', 'D');
    exit;
}

llxHeader('', $title);
print load_fiche_titre($title, '', 'payment');
print '<form method="get" action="'.DOL_URL_ROOT.'/indyreceipts/index.php">';
print '<input type="hidden" name="mainmenu" value="billing"><input type="hidden" name="leftmenu" value="customers_bills_payment_indyreceipts">';
print '<p><label>'.$langs->trans('ReceiptStart').' <input type="date" name="date_from" required value="'.indyReceiptEscape($start).'"></label> ';
print '<label>'.$langs->trans('ReceiptEnd').' <input type="date" name="date_to" required value="'.indyReceiptEscape($end).'"></label></p>';
print '<p><label><input type="checkbox" name="settled" value="1"'.($settled ? ' checked' : '').'> '.$langs->trans('ReceiptSettled').'</label></p>';
print '<button class="button" type="submit">'.$langs->trans('ReceiptView').'</button> ';
print '<button class="button" type="submit" name="format" value="pdf">'.$langs->trans('ReceiptPdf').'</button></form>';
if ($error !== '') {
    print '<p class="error" role="alert">'.indyReceiptEscape($error).'</p>';
} else {
    print $summary;
    foreach ($invoices as $invoice) {
        print '<section class="fichecenter">'.indyReceiptBlock($invoice).'</section>';
    }
}
llxFooter();
$db->close();
