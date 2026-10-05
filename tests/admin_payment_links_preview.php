<?php
if(PHP_SAPI!=='cli')exit(1);
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function app_url($s){return '/'.$s;}function getCsrfToken(){return 'preview';}
$catalog=['manage'=>['label'=>'Manage My Store - $12 for one year','providers'=>['stripe','paystack']],'setup'=>['label'=>'Set Up My Store - $5 one-time','providers'=>['stripe','paystack']],'priority_monthly'=>['label'=>'Priority Membership - $5/month recurring','providers'=>['stripe']]];
$error='';$notice='';$customer=['display_name'=>'Preview Customer','email'=>'preview@example.com'];
$selected=['id'=>1,'label'=>$catalog['manage']['label'],'customer_email'=>'preview@example.com','customer_name'=>'Preview Customer','provider'=>'paystack','created_at'=>'2026-09-30 16:00:00','checkout_url'=>'https://checkout.paystack.com/'.str_repeat('long',25)];$rows=[$selected];
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
require __DIR__.'/../app/views/admin/payment-links.php';echo '</body></html>';
