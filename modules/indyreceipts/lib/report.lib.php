<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */

/** Convert two inclusive calendar dates using the same timezone as native payment lists. */
function indyReceiptBounds($start, $end)
{
    global $langs;
    $bounds = array();
    foreach (array($start, $end) as $index => $date) {
        $parts = array();
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || $start > $end) {
            throw new InvalidArgumentException($langs->trans('ReceiptRangeError'));
        }
        $bounds[] = $index === 0
            ? dol_mktime(0, 0, 0, (int) $parts[2], (int) $parts[3], (int) $parts[1])
            : dol_mktime(23, 59, 59, (int) $parts[2], (int) $parts[3], (int) $parts[1]) + 1;
    }
    return $bounds;
}

function indyReceiptQuery($db, $sql)
{
    global $langs;
    $result = $db->query($sql);
    if (!$result) {
        dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
        throw new RuntimeException($langs->trans('ReceiptReadError'));
    }
    return $result;
}

function indyReceiptReport($db, $user, $start, $end, $settledOnly)
{
    list($from, $until) = indyReceiptBounds($start, $end);
    $from = $db->idate($from);
    $until = $db->idate($until);
    $prefix = MAIN_DB_PREFIX;
    $entities = getEntity('invoice');
    $sql = "SELECT f.rowid AS invoice_id, f.ref, f.total_ttc, s.nom, p.datep, p.ref AS payment_ref,
                   p.num_paiement, c.code, c.libelle, pf.amount
            FROM {$prefix}paiement p
            JOIN {$prefix}paiement_facture pf ON pf.fk_paiement = p.rowid
            JOIN {$prefix}facture f ON f.rowid = pf.fk_facture
            JOIN {$prefix}societe s ON s.rowid = f.fk_soc
            LEFT JOIN {$prefix}c_paiement c ON c.id = p.fk_paiement
            WHERE p.entity IN ($entities) AND f.entity IN ($entities)
              AND s.entity IN (".getEntity('societe').")
              AND p.datep >= '$from' AND p.datep < '$until'";
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $sql .= " AND EXISTS (SELECT 1 FROM {$prefix}societe_commerciaux sc
                    WHERE sc.fk_soc = s.rowid AND sc.fk_user = ".((int) $user->id).")";
    }
    if ($settledOnly) {
        // A fully paid native closure also covers settlements involving applied credit notes.
        // Otherwise use every signed allocation through the cutoff, including earlier periods.
        $decimals = getDolGlobalInt('MAIN_MAX_DECIMALS_TOT', 2);
        $sql .= " AND ((f.fk_statut = 2 AND COALESCE(f.close_code, '') = '' AND f.date_closing < '$until')
                  OR ROUND((f.total_ttc - (SELECT SUM(a.amount) FROM {$prefix}paiement_facture a
                     JOIN {$prefix}paiement prior ON prior.rowid = a.fk_paiement
                     WHERE a.fk_facture = f.rowid AND prior.entity IN ($entities) AND prior.datep < '$until'))
                     * CASE WHEN f.total_ttc < 0 THEN -1 ELSE 1 END, $decimals) <= 0)";
    }
    $result = indyReceiptQuery($db, $sql.' ORDER BY f.ref, f.rowid, p.datep, p.rowid, pf.rowid');
    $invoices = array();
    while ($row = $db->fetch_object($result)) {
        $id = (int) $row->invoice_id;
        if (!isset($invoices[$id])) {
            $invoices[$id] = array('ref' => $row->ref, 'customer' => $row->nom,
                'total' => $row->total_ttc, 'received' => 0, 'payments' => array(), 'lines' => array());
        }
        $invoices[$id]['received'] += (float) $row->amount;
        $invoices[$id]['payments'][] = $row;
    }
    $db->free($result);

    // Fetch lines separately so neither quantities nor payment amounts are multiplied.
    foreach (array_chunk(array_keys($invoices), 500) as $ids) {
        $sql = "SELECT d.fk_facture, d.qty, d.label, d.description, pr.ref, pr.label AS product_label
                FROM {$prefix}facturedet d LEFT JOIN {$prefix}product pr ON pr.rowid = d.fk_product
                WHERE d.fk_facture IN (".implode(',', $ids).") AND COALESCE(d.product_type, 0) <> 9
                ORDER BY d.fk_facture, d.rang, d.rowid";
        $result = indyReceiptQuery($db, $sql);
        while ($line = $db->fetch_object($result)) {
            $invoices[$line->fk_facture]['lines'][] = $line;
        }
        $db->free($result);
    }
    return $invoices;
}
