<?php
// Only run in the disposable Compose integration database.
if (PHP_SAPI !== 'cli') exit(1);
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/indyreceipts/lib/report.lib.php';
$user->fetch(0, 'admin');
$user->loadRights('', 1);
$party = new Societe($db);
$party->name = 'Maison des encaissements';
$party->client = 1;
$party->code_client = 'auto';
if ($party->create($user) <= 0) throw new RuntimeException($party->error);
$invoices = array();
foreach (array('Chardonnay' => 6, 'Pinot noir' => 3) as $product => $quantity) {
    $invoice = new Facture($db);
    $invoice->socid = $party->id;
    $invoice->date = dol_mktime(12, 0, 0, 10, 1, 2026);
    if ($invoice->create($user) <= 0 || $invoice->addline($product, 20, $quantity, 0) <= 0) throw new RuntimeException($invoice->error);
    if ($quantity === 6 && $invoice->addline('Livraison', 40, 1, 0) <= 0) throw new RuntimeException($invoice->error);
    if ($invoice->validate($user) <= 0) throw new RuntimeException($invoice->error);
    $invoices[] = (int) $invoice->id;
}
list($first, $second) = $invoices;
$prefix = MAIN_DB_PREFIX;
$insert = function ($sql) use ($db) {
    if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
};
foreach (array(
    array('2026-10-31 16:00:00', 'CHQ', '0045800', array($first => 20)),
    array('2026-11-01 00:00:00', 'CHQ', '0045821', array($first => 100, $second => 60)),
    array('2026-11-12 23:59:59', 'VIR', 'VIR-381', array($first => 40)),
) as list($day, $mode, $number, $allocation)) {
    $res = $db->query("SELECT id FROM {$prefix}c_paiement WHERE code='$mode' ORDER BY id");
    $type = (int) $db->fetch_object($res)->id;
    $total = array_sum($allocation);
    $insert("INSERT INTO {$prefix}paiement (ref, datep, amount, fk_paiement, num_paiement)
             VALUES ('CI-$number', '$day', $total, $type, '$number')");
    $payment = (int) $db->last_insert_id($prefix.'paiement');
    foreach ($allocation as $invoiceId => $amount) {
        $insert("INSERT INTO {$prefix}paiement_facture (fk_paiement, fk_facture, amount)
                 VALUES ($payment, $invoiceId, $amount)");
    }
}
$report = indyReceiptReport($db, $user, '2026-11-01', '2026-11-30', false);
if (count($report) !== 2 || $report[$first]['received'] !== 140.0 || count($report[$first]['lines']) !== 2
    || $report[$second]['received'] !== 60.0) throw new RuntimeException('Incorrect allocations or repeated items');
$restricted = new class {
    public $id = 2147483000;
    public function hasRight(...$right) { return false; }
};
if (indyReceiptReport($db, $restricted, '2026-11-01', '2026-11-30', false)) throw new RuntimeException('Salesperson scope');
