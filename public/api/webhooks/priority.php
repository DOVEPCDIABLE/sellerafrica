<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/app/core/bootstrap.php';
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){http_response_code(405);exit;}
try {
    $secretFile=dirname(APP_ROOT).'/.priority-stripe-webhook-secret';
    $secret=is_readable($secretFile)?trim((string)file_get_contents($secretFile)):'';
    if($secret==='')throw new RuntimeException('Priority webhook is not configured.');
    $payload=(string)file_get_contents('php://input');
    $event=\Stripe\Webhook::constructEvent($payload,(string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''),$secret);
    \App\PriorityMembershipService::webhook($payload,(string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''),$event);
    http_response_code(200);
    echo 'OK';
}catch(Throwable $e){error_log('Priority webhook: '.$e->getMessage());http_response_code(400);echo 'Unable to verify event';}
