<?php
/* Copyright (C) 2026 Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */

/**
 * Latest dated customer order (validated/in progress/closed), otherwise the
 * third party's historical date extrafield. Read only; never edits either date.
 * Loaded by conf.php so it is available before any extrafield is evaluated.
 *
 * @param int|string $thirdpartyId Third-party ID
 * @param string $manualField Date extrafield code, without the options_ prefix
 * @return int|null Unix timestamp, or null for an empty/invalid/inaccessible value
 */
function indyLastOrderDate($thirdpartyId, $manualField = 'date_derniere_commande_manuelle')
{
    global $db, $user, $extrafields;

    $id = (int) $thirdpartyId;
    if ($id <= 0 || !is_string($manualField) || !preg_match('/^[a-z][a-z0-9_]*$/', $manualField)) {
        return null;
    }
    if (!empty($user->socid) && (int) $user->socid !== $id) {
        return null;
    }
    // The third party and its orders may have different sharing rules under
    // MultiCompany. Apply each native entity scope to its own table.
    $sql = 'SELECT MAX(c.date_commande) AS last_order_date';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'societe AS s';
    $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'commande AS c ON c.fk_soc = s.rowid';
    $sql .= ' AND c.entity IN ('.getEntity('commande').') AND c.fk_statut > 0';
    $sql .= ' WHERE s.rowid = '.$id.' AND s.entity IN ('.getEntity('societe').')';
    $sql .= ' GROUP BY s.rowid';
    $result = $db->query($sql);
    if (!$result) {
        dol_syslog('indyLastOrderDate: order date lookup failed', LOG_ERR);
        return null;
    }
    $row = $db->fetch_object($result);
    $db->free($result);
    if (!$row) {
        return null;
    }
    if (!empty($row->last_order_date)) {
        $date = $db->jdate($row->last_order_date);
        return $date === false || $date === '' || $date === null ? null : (int) $date;
    }

    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $thirdparty = new Societe($db);
    // Disable all computed fields on this nested fetch to prevent recursion.
    if ($thirdparty->fetchNoCompute($id) <= 0) {
        return null;
    }
    $type = $extrafields->attributes['societe']['type'][$manualField] ?? null;
    if (!in_array($type, array('date', 'datetime'), true)
        || !empty($extrafields->attributes['societe']['computed'][$manualField])) {
        return null;
    }
    $manual = $thirdparty->array_options['options_'.$manualField] ?? null;
    return is_numeric($manual) && (int) $manual !== 0 ? (int) $manual : null;
}
