<?php
// Disposable Compose database ONLY; copied into the CI container, never shipped.
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

function checkComputedDate($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$user->fetch(0, 'admin');
$user->loadRights('', 1);
$manualCode = 'date_derniere_commande_manuelle';
$computedCode = 'indy_test_last_order';
$formula = 'indyLastOrderDate($objectoffield->id)';
$fields = new ExtraFields($db);
foreach (array($manualCode => '', $computedCode => $formula) as $code => $calculation) {
    checkComputedDate($fields->addExtraField($code, $code, 'date', 999, '', 'societe', 0, 0, '', '', 0, '', '1', '', $calculation, (string) $conf->entity) > 0, 'Cannot create date extrafield: '.$fields->error);
}
// Reload cached metadata so Societe::fetch evaluates the actual persisted formula.
unset($extrafields);
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label('societe');
$manualDate = dol_mktime(0, 0, 0, 2, 15, 2020);
$customer = new Societe($db);
$customer->name = 'CI date commande — Émilie';
$customer->client = 1;
$customer->code_client = 'auto';
$customer->array_options['options_'.$manualCode] = $manualDate;
checkComputedDate($customer->create($user) > 0, 'Cannot create third party: '.$customer->error);
$objectoffield = $customer;
checkComputedDate(dol_eval($formula, 1, 0, '2') === $manualDate, 'The real evaluator must accept the helper and return the historical date');
$loaded = new Societe($db);
checkComputedDate($loaded->fetch($customer->id) > 0, 'Cannot fetch calculated third party');
checkComputedDate((int) $loaded->array_options['options_'.$computedCode] === $manualDate, 'Native persisted calculated extrafield did not use the historical date');
checkComputedDate(indyLastOrderDate(0) === null && indyLastOrderDate(2147483647) === null, 'Unknown third party must be empty');
checkComputedDate(indyLastOrderDate($customer->id, 'invalid-code') === null, 'Reject invalid manual field code');
checkComputedDate(indyLastOrderDate($customer->id, 'not_configured') === null, 'Absent historical date must be empty');

$order = new Commande($db);
$order->socid = $customer->id;
$order->date = dol_mktime(0, 0, 0, 5, 10, 2025);
checkComputedDate($order->create($user) > 0, 'Cannot create order: '.$order->error);
checkComputedDate(dol_eval($formula, 1, 0, '2') === $manualDate, 'Draft orders must not replace the historical date');
function setDateFixture($orderId, $status, $date, $entity) {
    global $db;
    checkComputedDate((bool) $db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut = '.((int) $status).', date_commande = '."'".$db->idate($date)."'".', entity = '.((int) $entity).' WHERE rowid = '.((int) $orderId)), 'Cannot update disposable order fixture');
}
$validated = $order->date;
foreach (array(1, 2, 3) as $status) {
    setDateFixture($order->id, $status, $validated, $conf->entity);
    checkComputedDate(dol_eval($formula, 1, 0, '2') === $validated, 'Validated/in-progress/closed order date not returned');
}
setDateFixture($order->id, -1, $validated, $conf->entity);
checkComputedDate(dol_eval($formula, 1, 0, '2') === $manualDate, 'Cancelled order must not replace the historical date');
setDateFixture($order->id, 1, $validated, $conf->entity + 9999);
checkComputedDate(dol_eval($formula, 1, 0, '2') === $manualDate, 'Order from another entity leaked');
setDateFixture($order->id, 1, $validated, $conf->entity);
$older = new Commande($db);
$older->socid = $customer->id;
$older->date = dol_mktime(0, 0, 0, 1, 10, 2024);
checkComputedDate($older->create($user) > 0, 'Cannot create older order');
setDateFixture($older->id, 1, $older->date, $conf->entity);
checkComputedDate(dol_eval($formula, 1, 0, '2') === $validated, 'Latest date must win over highest order ID');
$customer2 = new Societe($db);
$customer2->name = 'CI autre tiers sans date';
$customer2->client = 1;
$customer2->code_client = 'auto';
checkComputedDate($customer2->create($user) > 0, 'Cannot create second third party');
checkComputedDate(indyLastOrderDate($customer2->id) === null, 'Another customer or empty history leaked into this result');
$externalSocid = $user->socid;
$user->socid = $customer2->id;
checkComputedDate(indyLastOrderDate($customer->id) === null, 'External user must not read another third party');
$user->socid = $externalSocid;
// Function remains dynamic after an order date changes; no stale request cache.
$updated = dol_mktime(0, 0, 0, 9, 18, 2026);
setDateFixture($order->id, 1, $updated, $conf->entity);
$loaded = new Societe($db);
checkComputedDate($loaded->fetch($customer->id) > 0, 'Cannot refetch updated date');
checkComputedDate((int) $loaded->array_options['options_'.$computedCode] === $updated, 'Native calculated date was not refreshed');
checkComputedDate((int) $loaded->array_options['options_'.$manualCode] === $manualDate, 'Historical date was overwritten');
global $dolibarr_main_restrict_eval_methods;
checkComputedDate(!in_array('query', array_map('trim', explode(',', $dolibarr_main_restrict_eval_methods))), 'Generic SQL must remain disallowed');
checkComputedDate(strpos((string) dol_eval('1 ?? 2', 1, 0, '2'), 'Bad string syntax') === 0, 'The evaluator protections must remain enabled');
checkComputedDate(strpos((string) dol_eval('file_get_contents("/etc/passwd")', 1, 0, '2'), 'Bad string syntax') === 0, 'File-reading methods must remain disallowed');
echo "PASS: real dol_eval and native third-party date extrafield, historical/empty fallback, order statuses, date sorting, entity/external isolation, dynamic refresh and unchanged evaluator protections.\n";
