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
$menuCheck = $db->query("SELECT fk_menu,fk_mainmenu,fk_leftmenu,mainmenu,leftmenu,url,enabled,perms FROM ".MAIN_DB_PREFIX."menu WHERE module='indylabels'");
$menuRow = $menuCheck ? $db->fetch_object($menuCheck) : null;
echo 'MENU CHECK '.json_encode(array('enabled'=>isModEnabled('indylabels'),'read'=>$user->hasRight('indylabels','read'),
    'societe_read'=>$user->hasRight('societe','lire'),'row'=>$menuRow,
    'evaluated'=>$menuRow ? dol_eval($menuRow->perms,1,0,'1') : null),JSON_UNESCAPED_UNICODE)."\n";
if (!$menuRow || !verifCond($menuRow->perms) || !verifCond($menuRow->enabled)) throw new RuntimeException('Native secure menu evaluation failed');
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
$incompleteId=$party->id;
// 800 historical customers selected in one action, with actual orders taking priority.
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
$fields = new ExtraFields($db);
if ($fields->addExtraField('date_derniere_commande_manuelle','Année historique','int',100,'4','societe',0,0,'','',0,'','1') <= 0) throw new RuntimeException($fields->error);
// Native metadata is cached per request; refresh after adding the fixture-only year field.
$extrafields = new ExtraFields($db); $extrafields->fetch_name_optionals_label('societe', true);
$bulk = array();
for ($i=1; $i<=801; $i++) {
    $party = new Societe($db); $party->name = sprintf('CI groupe %04d',$i); $party->client=1; $party->code_client='auto';
    $party->address='10 rue des Étiquettes'; $party->zip='01000'; $party->town='Bourg-en-Bresse'; $party->country_id=1;
    if ($party->create($user) <= 0) throw new RuntimeException($party->error);
    $party->array_options['options_destinataire_facturation']='Mme Élodie GROUPE'.sprintf('%04d',$i);
    $party->array_options['options_date_derniere_commande_manuelle']=$i===801 ? 2024 : 2025;
    if ($party->insertExtraFields() < 0) throw new RuntimeException($party->error);
    $bulk[]=$party->id;
}
foreach (array(array(0,2025,1),array(0,2026,1),array(1,2025,1),array(2,2026,0),array(2,2026,-1),array(800,2025,1)) as $fixture) {
    $order=new Commande($db); $order->socid=$bulk[$fixture[0]]; $order->date=dol_mktime(12,0,0,6,15,$fixture[1]);
    if ($order->create($user)<=0) throw new RuntimeException($order->error);
    if (!$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut='.((int)$fixture[2]).' WHERE rowid='.((int)$order->id))) throw new RuntimeException($db->lasterror());
}
$group=indyLabelsGroup($db,$user,'last_order_year',2025);
if (count($group)!==800 || in_array($bulk[0],$group) || !in_array($bulk[800],$group) || !in_array($bulk[2],$group)) throw new RuntimeException('Historical/real priority or draft/cancel filtering failed: '.json_encode(array('count'=>count($group),'newer'=>in_array($bulk[0],$group),'boost'=>in_array($bulk[800],$group),'draft'=>in_array($bulk[2],$group))));
$actual=indyLabelsGroup($db,$user,'orders_in_year',2025);
if (count($actual)!==3 || !in_array($bulk[0],$actual)) throw new RuntimeException('Any order in year must include later customers');
$compact=implode(',',$group);
if (indyLabelsParseSelection($compact)!==$group || count(indyLabelsParseSelection('',array_merge($group,$group)))!==800) throw new RuntimeException('Large selection or union deduplication failed');
$recipients=indyLabelsRecipients($db,$user,$group);
if (count($recipients)!==800) throw new RuntimeException('Bulk address lookup truncated');
$pdf=indyLabelsPdf($recipients); $pdf->Output($out.'/eight-hundred.pdf','F');
$manifest['eight-hundred']=array('start'=>1,'padding'=>10,'recipients'=>$recipients);
$pdf=indyLabelsPdf(array_slice($recipients,0,1),1,14); $pdf->Output($out.'/padding-14.pdf','F');
$manifest['padding-14']=array('start'=>1,'padding'=>14,'recipients'=>array_slice($recipients,0,1));
if (indyLabelsManualYear('2025-07-10','date')!==2025 || indyLabelsManualYear('', 'int')!==null) throw new RuntimeException('Manual date/year conversion failed');
$restricted->socid=0;
if (indyLabelsGroup($db,$restricted,'last_order_year',2025)!==array()) throw new RuntimeException('Group leaked unassigned third parties');
$res=activateModule('modCategorie'); if (!empty($res['errors'])) throw new RuntimeException(json_encode($res['errors']));
$conf->setValues($db); $user->loadRights('',1);
if (!$db->query("INSERT INTO ".MAIN_DB_PREFIX."categorie (label,type,entity) VALUES ('CI groupe courrier',2,".((int)$conf->entity).")")) throw new RuntimeException($db->lasterror());
$categoryId=$db->last_insert_id(MAIN_DB_PREFIX.'categorie');
foreach (array($bulk[1],$bulk[2]) as $id) if (!$db->query('INSERT INTO '.MAIN_DB_PREFIX.'categorie_societe (fk_categorie,fk_soc) VALUES ('.((int)$categoryId).','.$id.')')) throw new RuntimeException($db->lasterror());
$categoryGroup=indyLabelsGroup($db,$user,'last_order_year',2025,$categoryId);
if (count($categoryGroup)!==2) throw new RuntimeException('Category/year intersection failed');
$oldEntity=$conf->entity; $conf->entity=9876;
if (indyLabelsGroup($db,$user,'orders_in_year',2025)!==array()) throw new RuntimeException('Group entity boundary failed');
$conf->entity=$oldEntity;
echo "PASS: 800 imported historical customers, 50 PDF pages, real-order priority, any-order year, category intersection, batch lookup, compact selection and configurable left padding.\n";
file_put_contents($out.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents($out.'/ui.json', json_encode(array('ids'=>$ids,'incomplete'=>$incompleteId,'bulk'=>$group,'category'=>$categoryId)));
echo "PASS: native module, 1/16/17/33 labels, used-sheet offset, fallback, accents, duplicate IDs, entity/salesperson/external restrictions and invalid input rejection.\n";
