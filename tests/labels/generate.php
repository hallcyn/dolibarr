<?php
// Disposable CI database only, never shipped in the web image.
if (PHP_SAPI !== 'cli') exit(1);
define('NOLOGIN', 1); define('NOCSRFCHECK', 1); define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/indylabels/lib/labels.lib.php';
$user->fetch(0, 'admin');
foreach (array('modIndyInvoice','modIndyLabels') as $module) {
    $res = activateModule($module); if (!empty($res['errors'])) throw new RuntimeException(json_encode($res['errors']));
}
$conf->setValues($db); $user->loadRights('', 1);
$langs->setDefaultLang('fr_FR'); $langs->loadLangs(array('main', 'companies', 'indylabels@indylabels'));
$out = '/tmp/indy-label-fixtures'; dol_mkdir($out); $ids = array();
for ($i = 1; $i <= 33; $i++) {
    $party = new Societe($db); $party->name = sprintf('CI étiquette %02d', $i);
    $party->client = 1; $party->code_client = 'auto'; $party->address = $i.' rue de l’Été';
    $party->zip = '69003'; $party->town = 'Lyon'; $party->country_id = 1;
    if ($party->create($user) <= 0) throw new RuntimeException($party->error);
    $party->array_options['options_destinataire_facturation'] = $i % 2 ? 'Mme Zoé TEST'.sprintf('%02d', $i).' et M. Frédéric TEST'.sprintf('%02d', $i) : '  ';
    if ($party->insertExtraFields() < 0) throw new RuntimeException($party->error);
    $ids[] = $party->id;
}
$manifest = array();
foreach (array('one'=>array(1,1), 'full'=>array(16,1), 'seventeen'=>array(17,1), 'thirty-three'=>array(33,1), 'offset'=>array(2,16)) as $case => $config) {
    $recipients = indyLabelsRecipients($db, $user, array_slice($ids, 0, $config[0]));
    $pdf = indyLabelsPdf($recipients, $config[1]); $pdf->Output($out.'/'.$case.'.pdf', 'F');
    $manifest[$case] = array('start'=>$config[1], 'recipients'=>$recipients);
}
$recipients = indyLabelsRecipients($db, $user, array($ids[0],$ids[0]));
if (count($recipients) !== 1) throw new RuntimeException('Duplicate ID produced duplicate labels');
foreach (array(array(), array(0), array(2147483647), array($ids[0],2147483647)) as $invalid) {
    try { indyLabelsRecipients($db, $user, $invalid); throw new LogicException('Unauthorized/invalid selection accepted'); }
    catch (InvalidArgumentException|RuntimeException $expected) {}
}
$restricted = new class {
    public $socid = 0;
    public $id = 2147483647;
    public function hasRight($module, $one, $two = '') { return $one === 'lire'; }
};
try { indyLabelsRecipients($db, $restricted, array($ids[0])); throw new LogicException('Unassigned third party leaked'); } catch (RuntimeException $expected) {}
$restricted->socid = $ids[0];
try { indyLabelsRecipients($db, $restricted, array($ids[0])); throw new LogicException('External user accepted'); } catch (RuntimeException $expected) {}
$oldEntity = $conf->entity; $conf->entity = 9876;
try { indyLabelsRecipients($db, $user, array($ids[0])); throw new LogicException('Other entity leaked'); } catch (RuntimeException $expected) {}
$conf->entity = $oldEntity;
foreach (array(0,17) as $invalidStart) {
    try { indyLabelsPdf($recipients, $invalidStart); throw new LogicException('Invalid offset accepted'); } catch (InvalidArgumentException $expected) {}
}
$incomplete = $recipients; $incomplete[0]['complete'] = false;
try { indyLabelsPdf($incomplete); throw new LogicException('Incomplete address accepted'); } catch (RuntimeException $expected) {}
$tooLong = $recipients; $tooLong[0]['lines'][0] = str_repeat('W', 300);
try { indyLabelsPdf($tooLong); throw new LogicException('Overflowing name accepted'); } catch (RuntimeException $expected) {}
// Give the UI a populated form and also an incomplete-address fixture to test rejection.
$party = new Societe($db); $party->name = 'CI adresse incomplète'; $party->client=1; $party->code_client='auto';
if ($party->create($user) <= 0) throw new RuntimeException($party->error);
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents($out.'/ui.json', json_encode(array('ids'=>$ids,'incomplete'=>$party->id)));
echo "PASS: native module, 1/16/17/33 labels, used-sheet offset, fallback, accents, duplicate IDs, entity/salesperson/external restrictions and invalid input rejection.\n";
