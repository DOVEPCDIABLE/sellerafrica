<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/services/PriorityMembershipService.php';
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function app_url($s){return 'https://sellerafrica.com/'.$s;}
function asset($s){return 'https://sellerafrica.com/assets/'.$s;}
function app_branding(){return ['logo'=>'https://sellerafrica.com/uploads/settings/logo_path-20260712173349-45c92b5e.png'];}
function getCsrfToken(){return 'preview';}
$plan='monthly';$provider='paystack';$naira=['monthly'=>750000,'annual'=>7500000];$member=null;$managed=null;$token='';$error='';$notice='';$user=null;
if(($argv[1]??'')==='error')$error='Enter your name and a valid email address.';
if(($argv[1]??'')==='paid'){$member=['checkout_paid_at'=>'2026-09-30','status'=>'active','current_period_end'=>date('Y-m-d H:i:s',time()+3600)];$notice='Payment confirmed. Welcome to Seller Africa Priority.';}
$title='Seller Africa Priority';$meta_description='Founding membership';
ob_start();require __DIR__.'/../app/views/storefront/pages/priority.php';$content=ob_get_clean();
require __DIR__.'/../app/views/layouts/priority.php';
