<?php
// CLI-only, one-off monthly report. Preview freezes the data; send uses that snapshot.
if (PHP_SAPI !== 'cli' || count($argv) < 4) { exit(1); }
[$script, $root, $mode, $path] = $argv;
$daily = ($argv[4] ?? '') === 'daily';
require $root . '/app/core/bootstrap.php';
umask(0077);
function recipients(): array {
    $rows = db()->fetchAll("SELECT DISTINCT u.email, u.display_name AS name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code IN ('admin','super_admin') AND u.status='active'");
    $result = [];
    foreach ($rows as $row) {
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) { $result[strtolower($row['email'])] = $row; }
    }
    ksort($result);
    return array_values($result);
}
function metric(string $sql, array $params = []): int { return (int)db()->fetch($sql, $params)['n']; }
function grouped(string $table, string $column, string $where = '1=1', array $params = []): array {
    return db()->fetchAll("SELECT `$column` AS label, COUNT(*) AS n FROM `$table` WHERE $where GROUP BY `$column` ORDER BY `$column`", $params);
}
function section(string $title, array $metrics): string {
    $html = '<h2 style="font-size:20px;color:#177d51;margin:28px 0 8px">'.e($title).'</h2><table style="width:100%;border-collapse:collapse">';
    foreach ($metrics as $label=>$value) {
        $html .= '<tr><td style="padding:9px 4px;border-bottom:1px solid #dceae2">'.e($label).'</td><td style="padding:9px 4px;border-bottom:1px solid #dceae2;text-align:right;font-weight:bold">'.e(is_int($value) ? number_format($value) : $value).'</td></tr>';
    }
    return $html.'</table>';
}
function labels(array $rows): array {
    $result=[];
    foreach ($rows as $row) { $result[ucwords(str_replace('_',' ',(string)($row['label'] ?? 'Not set')))] = (int)$row['n']; }
    return $result;
}
if ($mode === 'preview') {
    if (file_exists($path)) { throw new RuntimeException('Snapshot already exists'); }
    $end = app_now();
    $start = $daily ? $end->setTime(0,0) : $end->modify('first day of this month')->setTime(0,0);
    $params = [$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')];
    db()->pdo()->beginTransaction();
    $r = ['period'=>$params,'timezone'=>app_timezone(),'recipients'=>recipients()];
    $r['users'] = ['Total user accounts'=>metric('SELECT COUNT(*) n FROM users'),'New user accounts this month'=>metric('SELECT COUNT(*) n FROM users WHERE created_at BETWEEN ? AND ?', $params)];
    $r['user_status'] = labels(grouped('users','status'));
    $r['roles'] = db()->fetchAll("SELECT r.name AS label, COUNT(DISTINCT u.id) n, COUNT(DISTINCT CASE WHEN u.created_at BETWEEN ? AND ? THEN u.id END) AS added FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id GROUP BY r.id,r.name ORDER BY r.name",$params);
    $bucket = "CASE WHEN status='rejected' OR kyc_status='rejected' THEN 'Rejected' WHEN status='active' AND kyc_status='approved' THEN 'Approved / verified' WHEN status='pending' OR kyc_status='pending' THEN 'Pending review' ELSE 'Other / incomplete status' END";
    $r['vendors'] = ['Total vendor stores'=>metric('SELECT COUNT(*) n FROM vendors'),'Vendor stores added this month'=>metric('SELECT COUNT(*) n FROM vendors WHERE created_at BETWEEN ? AND ?', $params)];
    $r['vendor_review'] = labels(db()->fetchAll("SELECT $bucket label,COUNT(*) n FROM vendors GROUP BY label"));
    $r['vendor_new_status'] = labels(db()->fetchAll("SELECT $bucket label,COUNT(*) n FROM vendors WHERE created_at BETWEEN ? AND ? GROUP BY label",$params));
    $r['vendor_status'] = labels(grouped('vendors','status'));
    $r['kyc_status'] = labels(grouped('vendors','kyc_status'));
    $r['review_events'] = ['Vendors with recorded approval this month'=>metric("SELECT COUNT(DISTINCT entity_id) n FROM audit_logs WHERE entity_type='vendors' AND action='vendor.approved' AND created_at BETWEEN ? AND ?",$params),'Vendors with recorded rejection this month'=>metric("SELECT COUNT(DISTINCT entity_id) n FROM audit_logs WHERE entity_type='vendors' AND action='vendor.rejected' AND created_at BETWEEN ? AND ?",$params)];
    $r['products'] = ['Total product listings (excluding variations)'=>metric("SELECT COUNT(*) n FROM products WHERE type<>'variation'"),'New product listings this month'=>metric("SELECT COUNT(*) n FROM products WHERE type<>'variation' AND created_at BETWEEN ? AND ?",$params)];
    $r['product_status'] = labels(grouped('products','status',"type<>'variation'"));
    $r['orders'] = labels(grouped('orders','status','placed_at BETWEEN ? AND ?', $params));
    $r['paid_sales'] = db()->fetchAll("SELECT currency, COUNT(*) n, SUM(grand_total) amount FROM orders WHERE paid_at BETWEEN ? AND ? AND payment_status='paid' AND status NOT IN ('cancelled','failed','refunded') GROUP BY currency",$params);
    $r['services'] = db()->fetchAll("SELECT plan,currency,COUNT(*) n,SUM(amount) amount FROM vendor_store_management_services WHERE paid_at BETWEEN ? AND ? AND status='active' GROUP BY plan,currency",$params);
    $r['plans'] = labels(grouped('vendor_subscriptions','status'));
    $r['affiliates'] = ['Total affiliate records'=>metric('SELECT COUNT(*) n FROM affiliates'),'New affiliate records this month'=>metric('SELECT COUNT(*) n FROM affiliates WHERE COALESCE(registered_at,created_at) BETWEEN ? AND ?', $params),'Affiliate visits this month'=>metric('SELECT COUNT(*) n FROM affiliate_visits WHERE created_at BETWEEN ? AND ?', $params),'Affiliate referrals this month'=>metric('SELECT COUNT(*) n FROM affiliate_referrals WHERE created_at BETWEEN ? AND ?', $params)];
    db()->pdo()->commit();
    $body = '<p>Month-to-date report: <strong>'.e($start->format('j F Y').' to '.$end->format('j F Y, g:i A')).'</strong> ('.e(app_timezone()).').</p><p>Current totals are a snapshot at report generation; monthly additions use registration dates. This is not a closed-month report.</p>';
    $body .= section('Users', $r['users']).section('User account status', $r['user_status']);
    $roleMetrics=[];
    foreach ($r['roles'] as $row) { $roleMetrics[$row['label']] = number_format((int)$row['n']).' total / '.number_format((int)$row['added']).' new'; }
    $body .= section('Account roles: total / new this month', $roleMetrics).'<p style="font-size:12px;color:#61766c">Users can have multiple roles, so role totals overlap. Vendor stores and vendor-role user accounts are different counts.</p>';
    $body .= section('Vendors',$r['vendors']).section('Current review groups',$r['vendor_review']).section('This month\'s new vendors: current review status',$r['vendor_new_status']).section('Store status',$r['vendor_status']).section('KYC status',$r['kyc_status']);
    $body .= '<p style="font-size:12px;color:#61766c">Review groups are mutually exclusive. Rejected stores are excluded from pending review. KYC totals are separate and must not be added to store totals.</p>';
    $body .= section('Recorded review activity this month',$r['review_events']).'<p style="font-size:12px;color:#61766c">Review activity counts distinct vendors in the audit log, not current status. A vendor may have both events; historical changes without audit records are not counted.</p>';
    $body .= section('Products',$r['products']).section('Current product status',$r['product_status']);
    $body .= section('Orders placed this month', ['Total placed orders'=>array_sum($r['orders'])]+$r['orders']).'<p style="font-size:12px;color:#61766c">Uses placed_at; unplaced carts are excluded. Statuses are current.</p>';
    $money=[];
    foreach ($r['paid_sales'] as $row) { $money[$row['currency'].' paid orders ('.$row['n'].')'] = $row['currency'].' '.number_format((float)$row['amount'],2); }
    $body .= section('Orders paid this month',$money ?: ['Paid orders'=>0]).'<p style="font-size:12px;color:#61766c">Order totals include shipping and tax; these are not platform profit. Only currently paid, non-cancelled/non-failed/non-refunded orders with paid_at in the period are included. Currencies are not combined.</p>';
    $services=[];
    foreach ($r['services'] as $row) { $label = $row['plan']==='setup' ? 'Set Up My Store' : 'Manage My Store ('.$row['plan'].')'; $services[$label.' / '.$row['currency'].' ('.$row['n'].')'] = $row['currency'].' '.number_format((float)$row['amount'],2); }
    $body .= section('Store services paid this month',$services ?: ['Paid service records'=>0]).'<p style="font-size:12px;color:#61766c">Active paid service records at their recorded plan currency; not a recurring-payment ledger.</p>';
    $body .= section('Current vendor plan subscriptions',$r['plans']).section('Affiliate activity',$r['affiliates']);
    $brand = app_branding();
    $r['subject']='Seller Africa | '.$start->format('F Y').' month-to-date general report';
    $r['html']='<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f2faf5;font-family:Arial,sans-serif;color:#16372a"><div style="max-width:680px;margin:20px auto;background:white"><header style="background:#0b5d36;padding:24px;color:white"><img alt="Seller Africa" src="'.e($brand['logo']).'" style="max-width:180px;max-height:60px"><h1 style="font-size:26px;color:white">Monthly Marketplace Report</h1></header><main style="padding:20px">'.$body.'<p><a href="'.e(app_url('dashboard')).'" style="display:inline-block;background:#177d51;color:white;padding:12px 20px;text-decoration:none">Open Admin Dashboard</a></p><p style="font-size:12px">Confidential administrative report. Seller Africa.</p></main></div></body></html>';
    $r['period_type'] = $daily ? 'daily' : 'monthly';
    if ($daily) {
        $r['subject'] = 'Seller Africa | ' . $end->format('j F Y') . ' daily general report';
        $r['html'] = str_replace(
            ['Monthly Marketplace Report', 'Month-to-date report:', 'This month&#039;s', "This month's", 'this month', 'monthly additions', 'closed-month report'],
            ['Daily Marketplace Report', 'Today so far:', 'Today&#039;s', "Today's", 'today', 'daily additions', 'closed-day report'],
            $r['html']
        );
        foreach (['users','vendors','review_events','products','affiliates'] as $group) {
            $renamed = [];
            foreach ($r[$group] as $key => $value) { $renamed[str_replace('this month','today',$key)] = $value; }
            $r[$group] = $renamed;
        }
    }
    file_put_contents($path,json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
    file_put_contents($path.'.html',$r['html'],LOCK_EX);
    unset($r['html']);
    echo json_encode($r,JSON_PRETTY_PRINT),PHP_EOL;
} elseif ($mode === 'send') {
    $r=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if ($r['recipients'] !== recipients()) { throw new RuntimeException('Admin recipients changed; regenerate report'); }
    if (App\EmailService::config()['mailer']==='log') { throw new RuntimeException('Email transport is log-only'); }
    $lock=fopen($path.'.sending','x');
    if (!$lock) { throw new RuntimeException('Send already attempted; inspect receipts before retrying'); }
    $results=[];
    foreach ($r['recipients'] as $recipient) {
        try {
            $response=App\EmailService::send([$recipient],$r['subject'],$r['html']);
            $results[]=['email'=>$recipient['email'],'result'=>$response];
        } catch (Throwable $e) { $results[]=['email'=>$recipient['email'],'error'=>$e->getMessage()]; }
        file_put_contents($path.'.receipts.json',json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
    }
    audit(($r['period_type'] ?? 'monthly').'_admin_report_sent','system',null,[],['period'=>$r['period'],'snapshot'=>$path,'results'=>$results]);
    echo json_encode($results,JSON_PRETTY_PRINT),PHP_EOL;
} else { exit(1); }
