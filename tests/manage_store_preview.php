<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function app_url($p): string { return '/' . $p; }
function asset($p): string { return '/public/assets/' . $p; }
function app_branding(): array { return ['name'=>'Seller Africa','logo'=>'/public/assets/images/iamavendor.jpeg']; }
function getCsrfToken(): string { return 'preview-token'; }
function consume_toasts(): array { return []; }
ob_start();
require dirname(__DIR__).'/app/views/storefront/pages/manage-store.php';
$content = ob_get_clean();
require dirname(__DIR__).'/app/views/layouts/manage-store.php';
