<?php
/** CLI-only import. Input is private JSON, never customer data in the repository. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$options = getopt('', array('input:', 'entity:', 'apply', 'backup-dir:'));
$connection = null;
try {
    $inputPath = $options['input'] ?? '';
    if (!$inputPath || !is_file($inputPath)) throw new RuntimeException('Usage: php import-billing-recipients.php --input=file.json --entity=1 [--apply]');
    $input = json_decode(file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input) || !array_is_list($input) || !$input || count($input) > 5000) throw new RuntimeException('Expected 1 to 5000 input rows.');
    $entity = filter_var($options['entity'] ?? null, FILTER_VALIDATE_INT, array('options'=>array('min_range'=>1)));
    if (!$entity) throw new RuntimeException('An explicit positive --entity is required.');
    $apply = isset($options['apply']);
    $trim = static fn($s) => preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $s);
    $normalize = static function ($s) use ($trim) {
        if (class_exists('Normalizer')) $s = Normalizer::normalize($s, Normalizer::FORM_C);
        return mb_strtolower(preg_replace('/[\s\p{Z}]+/u', ' ', $trim($s)), 'UTF-8');
    };
    $source = array(); $sourceCounts = array();
    foreach ($input as $i=>$row) {
        if (!is_array($row) || !is_string($row['name'] ?? null) || !is_string($row['recipient'] ?? null)) throw new RuntimeException('Invalid row '.($i+1));
        $name = $trim($row['name']); $recipient = $trim(str_replace(array("\r\n", "\r"), "\n", $row['recipient']));
        if ($name === '' || $recipient === '' || str_contains($name, "\n") || str_contains($name.$recipient, "\0")) throw new RuntimeException('Empty/invalid row '.($i+1));
        $key = $normalize($name);
        $source[] = array('row'=>$row['row'] ?? $i+1, 'name'=>$name, 'value'=>$name."\n".$recipient, 'key'=>$key);
        $sourceCounts[$key] = ($sourceCounts[$key] ?? 0)+1;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(getenv('DOLI_DB_HOST'), getenv('DOLI_DB_USER'), getenv('DOLI_DB_PASSWORD'), getenv('DOLI_DB_NAME'), (int)(getenv('DOLI_DB_HOST_PORT') ?: 3306));
    $connection->set_charset('utf8mb4');
    $meta = $connection->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='llx_societe_extrafields' AND COLUMN_NAME='destinataire_facturation'")->fetch_assoc();
    if (!$meta) throw new RuntimeException('Missing destinataire_facturation extrafield. No schema change performed.');
    $limit = (int)$meta['CHARACTER_MAXIMUM_LENGTH'];
    $engines = $connection->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('llx_societe','llx_societe_extrafields')");
    $engineCount = 0;
    while ($table = $engines->fetch_assoc()) {
        if (strtolower($table['ENGINE']) !== 'innodb') throw new RuntimeException('Transactional InnoDB tables required; no changes applied.');
        $engineCount++;
    }
    if ($engineCount !== 2) throw new RuntimeException('Required application tables missing.');
    $connection->begin_transaction();
    $stmt = $connection->prepare('SELECT rowid,nom FROM llx_societe WHERE entity=? ORDER BY rowid'.($apply ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $entity); $stmt->execute(); $rows = $stmt->get_result();
    $parties = array(); while ($p = $rows->fetch_assoc()) $parties[$normalize($p['nom'])][] = $p;
    $report = array('mode'=>$apply ? 'apply' : 'preview', 'entity'=>$entity, 'input_rows'=>count($source), 'updated'=>0, 'unchanged'=>0, 'skipped'=>array(), 'matches'=>array());
    $before = array(); $changes = array();
    foreach ($source as $s) {
        $reason = $sourceCounts[$s['key']] !== 1 ? 'duplicate_in_input' : (count($parties[$s['key']] ?? array()) !== 1 ? (empty($parties[$s['key']]) ? 'not_found' : 'ambiguous_in_dolibarr') : '');
        if ($reason) { $report['skipped'][] = array('row'=>$s['row'],'name'=>$s['name'],'reason'=>$reason); continue; }
        if ($limit && mb_strlen($s['value'], 'UTF-8') > $limit) throw new RuntimeException('Recipient exceeds column capacity at input row '.$s['row']);
        $p = $parties[$s['key']][0]; $id = (int)$p['rowid'];
        $stmt = $connection->prepare('SELECT destinataire_facturation FROM llx_societe_extrafields WHERE fk_object=?'.($apply ? ' FOR UPDATE' : ''));
        $stmt->bind_param('i',$id); $stmt->execute(); $old = $stmt->get_result()->fetch_assoc();
        $report['matches'][] = array('row'=>$s['row'],'id'=>$id,'name'=>$p['nom'],'new_value'=>$s['value']);
        if ($old && $old['destinataire_facturation'] === $s['value']) { $report['unchanged']++; continue; }
        $before[] = array('id'=>$id,'name'=>$p['nom'],'row_existed'=>$old !== null,'old_value'=>$old['destinataire_facturation'] ?? null,'new_value'=>$s['value']);
        $changes[] = array('id'=>$id,'exists'=>$old !== null,'value'=>$s['value']);
    }
    $report['changes'] = count($changes);
    if ($apply && $changes) {
        $directory = $options['backup-dir'] ?? '/data/backups';
        if (!is_dir($directory) || !is_writable($directory)) throw new RuntimeException('Backup directory missing/not writable; no changes applied.');
        umask(0077);
        $backup = rtrim($directory,'/').'/billing-recipients-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)).'.json';
        $f = fopen($backup,'x');
        if (!$f) throw new RuntimeException('Cannot create backup; no changes applied.');
        try {
            $bytes = json_encode(array('entity'=>$entity,'input_sha256'=>hash_file('sha256',$inputPath),'before'=>$before), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if (fwrite($f,$bytes)!==strlen($bytes) || !fflush($f)) throw new RuntimeException('Incomplete backup; no changes applied.');
        } finally { fclose($f); }
        $report['backup'] = $backup;
        foreach ($changes as $change) {
            $sql = $change['exists'] ? 'UPDATE llx_societe_extrafields SET destinataire_facturation=? WHERE fk_object=?' : 'INSERT INTO llx_societe_extrafields (destinataire_facturation,fk_object) VALUES (?,?)';
            $stmt = $connection->prepare($sql); $stmt->bind_param('si',$change['value'],$change['id']); $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Unexpected write count; entire batch rolled back.');
            $stmt = $connection->prepare('SELECT destinataire_facturation FROM llx_societe_extrafields WHERE fk_object=?');
            $stmt->bind_param('i',$change['id']); $stmt->execute(); $verified = $stmt->get_result()->fetch_assoc();
            if ($verified['destinataire_facturation'] !== $change['value']) throw new RuntimeException('Round-trip verification failed; entire batch rolled back.');
            $report['updated']++;
        }
        $connection->commit();
    } else $connection->rollback();
    echo json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    if ($connection) { try { $connection->rollback(); } catch (Throwable $ignored) {} }
    fwrite(STDERR, 'Import aborted: '.$e->getMessage().PHP_EOL); exit(1);
}
