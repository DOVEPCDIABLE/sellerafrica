<?php
require __DIR__ . '/../app/core/bootstrap.php';
$email = 'testnewuser123@example.com';
$row = db()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
echo "Result for $email:\n";
var_dump($row);
echo "\nTotal users:\n";
var_dump(db()->fetch('SELECT COUNT(*) as c FROM users'));
echo "\nRandom user:\n";
var_dump(db()->fetch('SELECT email, phone FROM users ORDER BY id DESC LIMIT 1'));
