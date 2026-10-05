<?php
// One-off production maintenance: preview first, then apply against its exact ID set.
if (PHP_SAPI !== 'cli') { exit(1); }
[$script, $root, $mode, $backup] = array_pad($argv, 4, '');
if (!in_array($mode, ['preview', 'apply'], true) || !$root || !$backup) { exit(1); }
require $root . '/app/core/bootstrap.php';
function hasDescription($value): bool {
    $text = strip_tags(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $text) !== '';
}
$pdo = db()->pdo();
try {
    $pdo->beginTransaction();
    $rows = db()->fetchAll("SELECT * FROM products WHERE status IN ('active','pending') ORDER BY id FOR UPDATE");
    $images = db()->fetchAll("SELECT DISTINCT pm.product_id FROM product_media pm JOIN files f ON f.id=pm.file_id WHERE pm.role IN ('primary','gallery') AND TRIM(f.path) <> ''");
    $imageIds = array_fill_keys(array_column($images, 'product_id'), true);
    $affected = [];
    $counts = ['active'=>0,'pending'=>0,'missing_picture_only'=>0,'missing_description_only'=>0,'missing_both'=>0];
    foreach ($rows as $row) {
        $noImage = !isset($imageIds[$row['id']]);
        $noDescription = !hasDescription($row['description']) && !hasDescription($row['short_description']);
        if (!$noImage && !$noDescription) { continue; }
        $reason = $noImage && $noDescription ? 'missing_both' : ($noImage ? 'missing_picture_only' : 'missing_description_only');
        $counts[$row['status']]++;
        $counts[$reason]++;
        $affected[] = ['reason'=>$reason,'product'=>$row];
    }
    $hash = hash('sha256', json_encode($affected, JSON_THROW_ON_ERROR));
    $output = ['scanned'=>count($rows),'affected'=>count($affected),'counts'=>$counts,'snapshot_hash'=>$hash];
    if ($mode === 'apply') {
        if (!isset($argv[4]) || !hash_equals($argv[4], $hash)) { throw new RuntimeException('Snapshot changed; preview again.'); }
        umask(0077);
        $handle = fopen($backup, 'x');
        if (!$handle) { throw new RuntimeException('Cannot create unique backup.'); }
        $json = json_encode(['created_at'=>date(DATE_ATOM),'criteria'=>'active/pending, no primary/gallery file path OR both descriptions empty','summary'=>$output,'records'=>$affected], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) { throw new RuntimeException('Backup failed.'); }
        fclose($handle);
        $changed = 0;
        foreach ($affected as $entry) {
            $row = $entry['product'];
            $changed += db()->query("UPDATE products SET status='rejected', updated_at=NOW() WHERE id=? AND status=?", [$row['id'],$row['status']])->rowCount();
        }
        if ($changed !== count($affected)) { throw new RuntimeException('Update count mismatch.'); }
        $pdo->commit();
        $output['changed'] = $changed;
        $output['backup'] = $backup;
    } else {
        $pdo->rollBack();
    }
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
