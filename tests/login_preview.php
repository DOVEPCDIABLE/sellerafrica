<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function app_url($p): string { return '/' . $p; }
function asset($p): string { return '/public/assets/' . $p; }
function app_branding(): array { return ['name'=>'Seller Africa','logo'=>'/public/assets/images/iamavendor.jpeg']; }
function getCsrfToken(): string { return 'preview-token'; }
function consume_toasts(): array { return isset($_GET['error']) ? [['type'=>'error','message'=>'Please check your email and password.']] : []; }
$errors = isset($_GET['error']) ? ['Please check your email and password.'] : [];
$pendingMfa = isset($_GET['mfa']);
$captchaRequired = isset($_GET['captcha']);
$captchaChallenge = ['question'=>'3 + 4'];
require dirname(__DIR__) . '/app/views/auth/login.php';
