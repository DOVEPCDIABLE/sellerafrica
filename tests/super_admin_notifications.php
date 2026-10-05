<?php
declare(strict_types=1);
require __DIR__.'/../app/services/NotificationService.php';
function db() { static $db; return $db ??= new class {
    public string $sql = '';
    public function fetchAll(string $sql): array { $this->sql=$sql; return []; }
}; }
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function app_url(string $path): string { return 'https://example.test/'.$path; }
function check(bool $ok): void { if (!$ok) throw new RuntimeException('Notification recipient assertion failed'); }
foreach (['buyer','vendor','distributor'] as $type) {
    App\NotificationService::registrationCreated(['email'=>'fixture@example.test'], $type);
    check(str_contains(db()->sql, "r.code = 'super_admin'"));
    check(str_contains(db()->sql, "u.status = 'active'"));
}
App\NotificationService::notifySuperAdmins('Test','Test','Test');
check(str_contains(db()->sql, "r.code = 'super_admin'"));
App\NotificationService::notifyAdmins('Test','Test','Test');
check(str_contains(db()->sql, "r.code IN ('super_admin', 'admin')"));
foreach (['VendorDashboardService','VendorOnboardingService','DistributorService'] as $service) {
    $source=file_get_contents(__DIR__.'/../app/services/'.$service.'.php');
    check(str_contains($source,'NotificationService::notifySuperAdmins('));
    check(!str_contains($source,'NotificationService::notifyAdmins('));
}
echo "Super-admin-only registrations and submissions passed; general admin notifications preserved. No emails sent.\n";
