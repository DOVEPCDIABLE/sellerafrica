<?php
if (PHP_SAPI!=='cli') exit(1);
require __DIR__.'/../app/services/DistributorService.php';
require __DIR__.'/../app/services/DistributorPaymentService.php';
function table_exists($table){return false;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function app_url($v){return 'https://example.test/'.$v;}
function getCsrfToken(){return 'test-only-token';}
$status='pending';$page=1;$totalPages=2;$counts=['pending'=>26,'approved'=>5,'support_required'=>2,'not_ready'=>1];$error='';
$answers=['business_name'=>'Example African Foods and International Distribution Limited','country'=>'Nigeria','contact_name'=>'Example Applicant','brands'=>str_repeat('Long product and brand information. ',12),'accurate'=>'1'];
$rows=[['id'=>123,'email'=>'long.application.address@example.test','application_data'=>json_encode($answers),'status'=>'pending','submitted_at'=>'2026-09-29 15:00:00','reviewed_at'=>null,'review_notes'=>'','product_limit'=>null]];
if (($argv[1] ?? '')==='error') {$error='Catalog allowance is required for approval.';$_POST=['id'=>123,'status'=>'approved','review_notes'=>'Keep this feedback'];}
if (($argv[1] ?? '')==='empty') {$rows=[];$counts['pending']=0;$totalPages=1;}
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="admin-layout"><main class="admin-main"><div class="content">';
require __DIR__.'/../app/views/distributor/admin.php';
echo '</div></main></body></html>';
