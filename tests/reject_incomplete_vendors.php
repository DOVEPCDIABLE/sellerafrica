<?php
if (PHP_SAPI !== 'cli' || count($argv) < 4) { exit(1); }
[$script, $root, $mode, $backup] = $argv;
if (!in_array($mode, ['preview', 'apply'], true)) { exit(1); }
require $root . '/app/core/bootstrap.php';
$source = file_get_contents($root . '/public/dashboard.php');
if (!preg_match('/\$vendorBaseSql = "(.*?)";/s', $source, $match)) { throw new RuntimeException('Dashboard query not found'); }
$where = "(v.status='pending' OR v.kyc_status='pending') AND v.status<>'rejected' AND v.kyc_status<>'rejected'";
$pdo = db()->pdo();
try {
    $pdo->beginTransaction();
    $originals = db()->fetchAll("SELECT v.* FROM vendors v WHERE $where ORDER BY v.id FOR UPDATE");
    $rows = db()->fetchAll(stripcslashes($match[1]) . " WHERE $where");
    $result = App\VendorCompletionService::listing($rows, '');
    $selected = [];
    foreach ($result['rows'] as $row) {
        if ($row['completion_percent'] < 100) { $selected[(int)$row['id']] = $row['completion_percent']; }
    }
    $records = array_values(array_filter($originals, static fn ($row) => isset($selected[(int)$row['id']])));
    if (count($records) !== count($selected)) { throw new RuntimeException('Selected records mismatch'); }
    $hash = hash('sha256', json_encode([$records, $selected], JSON_THROW_ON_ERROR));
    $summary = ['pending_before'=>count($rows),'reject_count'=>count($selected),'completion_counts'=>$result['summary'],'hash'=>$hash];
    if ($mode === 'preview') {
        $pdo->rollBack();
        echo json_encode($summary, JSON_PRETTY_PRINT), PHP_EOL;
        exit;
    }
    if (!isset($argv[4]) || !hash_equals($hash, $argv[4])) { throw new RuntimeException('Data changed; preview again'); }
    umask(0077);
    $handle = fopen($backup, 'x');
    if (!$handle) { throw new RuntimeException('Backup cannot be created'); }
    $json = json_encode(['created_at'=>date(DATE_ATOM),'summary'=>$summary,'completion'=>$selected,'vendors'=>$records], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) { throw new RuntimeException('Backup write failed'); }
    fclose($handle);
    $reason = 'Your application is incomplete. Please complete the required store and product information shown on your verification page before requesting another review.';
    foreach ($records as $row) {
        $changed = db()->query("UPDATE vendors SET status='rejected', kyc_status='rejected', updated_at=NOW() WHERE id=? AND status=? AND kyc_status=?", [$row['id'],$row['status'],$row['kyc_status']])->rowCount();
        if ($changed !== 1) { throw new RuntimeException('Vendor update mismatch'); }
        db()->query("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, old_values, new_values, user_agent) VALUES (NULL, 'vendor.rejected', 'vendors', ?, ?, ?, 'Authorized CLI bulk review')", [$row['id'],json_encode(['status'=>$row['status'],'kyc_status'=>$row['kyc_status']]),json_encode(['status'=>'rejected','kyc_status'=>'rejected','reason'=>$reason,'completion_percent'=>$selected[(int)$row['id']],'backup'=>$backup])]);
    }
    $pdo->commit();
    $summary['changed'] = count($records);
    $summary['notification_errors'] = [];
    foreach ($records as $row) {
        try { App\NotificationService::vendorRejected((int)$row['id'], $reason); }
        catch (Throwable $e) { $summary['notification_errors'][] = (int)$row['id']; error_log($e->getMessage()); }
    }
    $summary['backup'] = $backup;
    echo json_encode($summary, JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
