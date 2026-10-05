<?php
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
$root = $argv[1];
require $root . '/app/core/bootstrap.php';
// Exercise the dashboard's actual static query without executing its request handler.
$source = file_get_contents($root . '/public/dashboard.php');
if (!preg_match('/\$vendorBaseSql = "(.*?)";/s', $source, $match)) { throw new RuntimeException('Dashboard query not found'); }
$sql = stripcslashes($match[1]);
$start = microtime(true);
$rows = db()->fetchAll($sql . " WHERE (v.status='pending' OR v.kyc_status='pending') AND v.status<>'rejected' AND v.kyc_status<>'rejected'");
$result = App\VendorCompletionService::listing($rows, '');
if (array_sum($result['summary']) !== count($rows)) { throw new RuntimeException('Summary mismatch'); }
$previous = 101;
foreach ($result['rows'] as $row) {
    if ($row['completion_percent'] > $previous) { throw new RuntimeException('Sort failed'); }
    $previous = $row['completion_percent'];
}
echo json_encode(['pending_vendors'=>count($rows),'completion_counts'=>$result['summary'],'seconds'=>round(microtime(true)-$start, 3)], JSON_PRETTY_PRINT), PHP_EOL;
