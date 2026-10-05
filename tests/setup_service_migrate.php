<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || empty($argv[1]) || empty($argv[2])) exit(1);
require rtrim($argv[1], '/') . '/app/core/bootstrap.php';
$table = db()->fetch('SHOW CREATE TABLE vendor_store_management_services');
if (!$table) throw new RuntimeException('Store services table was not found.');
if (is_file($argv[2])) throw new RuntimeException('Schema backup already exists.');
if (file_put_contents($argv[2], $table['Create Table'] . ";\n", LOCK_EX) === false) throw new RuntimeException('Could not save schema backup.');
$before = (int)db()->fetch('SELECT COUNT(*) AS total FROM vendor_store_management_services')['total'];
$column = db()->fetch("SHOW COLUMNS FROM vendor_store_management_services LIKE 'plan'");
if (!str_contains((string)$column['Type'], "'setup'")) {
    db()->pdo()->exec("ALTER TABLE vendor_store_management_services MODIFY plan ENUM('monthly', 'annual', 'setup') NOT NULL DEFAULT 'monthly'");
}
$after = (int)db()->fetch('SELECT COUNT(*) AS total FROM vendor_store_management_services')['total'];
echo json_encode(['before'=>$before,'after'=>$after,'plan'=>db()->fetch("SHOW COLUMNS FROM vendor_store_management_services LIKE 'plan'")['Type']]) . "\n";
