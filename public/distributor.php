<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/core/bootstrap.php';
if (trim((string)($_GET['route'] ?? ''), '/') === 'distributor/register' && !\App\AuthService::check()) {
    define('DISTRIBUTOR_SIGNUP', true);
    $_SESSION['intended_url'] = app_url('distributor/register');
    $_SESSION['distributor_registration_return'] = true;
    $_GET['type'] = 'customer';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') $_POST['account_type'] = 'customer';
    require __DIR__ . '/register.php';
    exit;
}
\App\DistributorService::page(trim((string)($_GET['route'] ?? 'distributor'), '/'));
