<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/services/VendorCompletionService.php';
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_url($path) { return '/' . $path; }
$view = 'pending-vendors';
$_GET = ['completion'=>'100','q'=>'Example'];
$vendorsData = ['completion'=>['filter'=>'100','summary'=>['100'=>35,'75-99'=>10,'50-74'=>22,'25-49'=>9,'0-24'=>100]], 'pagination'=>['page'=>1,'totalPages'=>2,'total'=>35,'search'=>'Example']];
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
require __DIR__ . '/../app/views/admin/vendors.php';
echo '</body></html>';
