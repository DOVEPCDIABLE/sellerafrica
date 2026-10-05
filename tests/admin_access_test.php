<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/services/AdminAccessService.php';
use App\AdminAccessService as Access;
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$_SESSION['roles'] = ['super_admin'];
$all = require dirname(__DIR__) . '/app/views/partials/admin_navigation.php';
foreach ($all as $section => $items) {
    foreach ($items as $item) {
        $view = $item['key'];
        check(Access::allows(['super_admin'], $view, 'any_action'), 'Superadmin denied ' . $view);
        check(!Access::allows(['customer'], $view), 'Customer allowed ' . $view);
        check(!Access::allows(['vendor'], $view), 'Vendor allowed ' . $view);
        check(!Access::allows([], $view), 'Guest allowed ' . $view);
        $restricted = in_array($section, ['Settings', 'Payments'], true) || in_array($view, ['aramex-api-settings', 'data-migration'], true);
        check(Access::allows(['admin'], $view) === !$restricted, 'Admin view policy ' . $view);
        check(Access::allows(['customer','admin'], $view) === !$restricted, 'Mixed-role policy ' . $view);
    }
}
$_SESSION['roles'] = ['admin'];
$filtered = require dirname(__DIR__) . '/app/views/partials/admin_navigation.php';
check(!isset($filtered['Settings'], $filtered['Payments']), 'Restricted menus leaked');
foreach (['save_payment_gateway','test_mail','disable_maintenance','save_admin_profile','save_subscription_settings','create_manage_store_payment','clear_queue','unknown',''] as $action) {
    check(!Access::allows(['admin'], 'overview', $action), 'Cross-view POST allowed ' . $action);
    check(Access::allows(['super_admin'], 'overview', $action), 'Superadmin action denied');
}
foreach (['save_product','save_user_admin','update_vendor_approval','save_marketing_banner','update_shipment_tracking'] as $action) {
    check(Access::allows(['admin'], 'overview', $action), 'Normal action denied');
    check(!Access::allows(['admin'], 'payment-gateways', $action), 'Restricted view POST allowed');
}
check(!Access::canEditUser(['admin'], ['customer','super_admin']), 'Superadmin account takeover');
check(Access::canEditUser(['admin'], ['customer']), 'Customer editing denied');
check(Access::canEditUser(['super_admin'], ['super_admin']), 'Superadmin editing denied');
$source = file_get_contents(dirname(__DIR__) . '/public/dashboard.php');
check(strpos($source, 'AdminAccessService::enforce($view)') < strpos($source, "=== 'disable_maintenance'"), 'Guard must precede POST dispatch');
check(strpos($source, 'Only a super administrator can change account roles.') < strpos($source, "'UPDATE users SET email"), 'Role validation must precede writes');
require dirname(__DIR__) . '/app/services/AuthService.php';
function validateCsrf(): void {}
final class UserGuardDatabase {
    public array $roles = [];
    public function fetchAll(string $sql, array $params): array { return $this->roles; }
    public function fetch(string $sql, array $params): array { throw new RuntimeException('Unexpected user read after authorization'); }
    public function query(string $sql, array $params): void { throw new RuntimeException('Unexpected database write'); }
}
$database = new UserGuardDatabase();
function db(): UserGuardDatabase { global $database; return $database; }
$start = strpos($source, 'function dashboard_save_user_admin(): void');
$end = strpos($source, 'function dashboard_save_admin_profile(): void', $start);
eval(substr($source, $start, $end - $start));
foreach ([
    [[['id'=>1,'code'=>'super_admin']], [1], 'Only a super administrator can edit'],
    [[['id'=>2,'code'=>'admin']], [1,2], 'Only a super administrator can change'],
    [[['id'=>3,'code'=>'customer']], [1], 'Only a super administrator can change'],
] as [$roles, $requested, $expected]) {
    $database->roles = $roles;
    $_POST = ['user_id'=>42,'roles'=>$requested];
    try { dashboard_save_user_admin(); throw new RuntimeException('Guard did not reject request'); }
    catch (RuntimeException $e) { check(str_starts_with($e->getMessage(), $expected), $e->getMessage()); }
}
echo "PASS admin/superadmin/guest/mixed-role views, menus, cross-view POST, user protection and guard order\n";
