<?php
/* Enable the installed theme's native progressive enhancement and discover new
 * document models. Do not change any invoice/order default or business record. */
if (PHP_SAPI !== 'cli') exit(1);
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
// Native web redirects do not apply to this CLI-only startup hook.
define('NOHTTPSREDIRECT', 1);
define('NOREQUIREMENU', 1);
require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (!getDolGlobalInt('ALLOW_THEME_JS')) {
    if (dolibarr_set_const($db, 'ALLOW_THEME_JS', '1', 'chaine', 0, '', $conf->entity) <= 0) exit(1);
}
$version = json_decode(file_get_contents(DOL_DOCUMENT_ROOT.'/theme/indy/version.json'), true)['version'];
if (getDolGlobalString('INDY_UI_ASSET_VERSION') !== $version) {
    $db->begin();
    $ok = dolibarr_set_const($db, 'MAIN_IHM_PARAMS_REV', (string)(getDolGlobalInt('MAIN_IHM_PARAMS_REV') + 1), 'chaine', 0, '', $conf->entity) > 0;
    $ok = dolibarr_set_const($db, 'INDY_UI_ASSET_VERSION', $version, 'chaine', 0, '', $conf->entity) > 0 && $ok;
    if (!$ok) { $db->rollback(); exit(1); }
    $db->commit();
}
if (isModEnabled('indyinvoice')) {
    $entity = (int)$conf->entity;
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom,type,entity) SELECT 'indyorder','order',".$entity.
        " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom='indyorder' AND type='order' AND entity=".$entity.")";
    if (!$db->query($sql)) exit(1);
}
echo '[railway] Presentation assets and document models ready.'.PHP_EOL;
