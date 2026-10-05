<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true)) { http_response_code(403); exit; }
require dirname(__DIR__) . '/app/services/DistributorService.php';
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function app_url($p): string { return '/'.$p; }
function asset($p): string { return '/public/assets/'.$p; }
function app_branding(): array { return ['logo'=>'/public/assets/images/iamavendor.jpeg']; }
function getCsrfToken(): string { return 'preview'; }
$route='distributor/register'; $user=['display_name'=>'Preview Applicant','email'=>'preview@example.com','phone'=>''];
$application=null; $data=[]; $errors=[]; $saved=false;
if(isset($_GET['guest']))$user=null;
ob_start(); require dirname(__DIR__).'/app/views/distributor/'.(isset($_GET['landing'])?'landing':'page').'.php'; $content=ob_get_clean();
require dirname(__DIR__).'/app/views/layouts/vendor-onboarding.php';
