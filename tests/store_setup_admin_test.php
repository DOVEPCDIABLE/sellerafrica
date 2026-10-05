<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/services/AuthService.php';
require dirname(__DIR__).'/app/services/AdminAccessService.php';
function e($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function app_url($v): string {return '/'.$v;}
function getCsrfToken(): string {return 'fixture';}
function check(bool $pass,string $message): void {if(!$pass)throw new RuntimeException($message);}
$view='users';
$usersData=['selectedUser'=>['id'=>42,'email'=>'test@example.com','display_name'=>'Test User','first_name'=>'Test','last_name'=>'User','username'=>'test','phone'=>'','status'=>'active','email_verified_at'=>null],'allRoles'=>[]];
foreach(['super_admin','admin'] as $role){
    $_SESSION=['roles'=>[$role]];
    ob_start(); require dirname(__DIR__).'/app/views/admin/users.php'; $html=ob_get_clean();
    check(str_contains($html,'Generate $5 setup payment link')===($role==='super_admin'),'Payment permission boundary');
    if($role==='super_admin'){
        check(str_contains($html,'name="service_plan" value="setup"'),'Setup plan missing');
        check(str_contains($html,'name="user_id" value="42"'),'Account target missing');
    }
}
$_SESSION=['roles'=>['super_admin']];
$usersData['selectedUserSetup']=['status'=>'pending','paid_at'=>null,'raw_response'=>json_encode(['paystack_checkout'=>['authorization_url'=>'https://checkout.paystack.com/setup-fixture']])];
ob_start();require dirname(__DIR__).'/app/views/admin/users.php';$html=ob_get_clean();
check(str_contains($html,'https://checkout.paystack.com/setup-fixture'),'Pending link not visible');
check(!str_contains($html,'Generate $5 setup payment link'),'Pending link should be reused');
$usersData['selectedUserSetup']['paid_at']='2026-09-24';
ob_start();require dirname(__DIR__).'/app/views/admin/users.php';$html=ob_get_clean();
check(str_contains($html,'Payment received - arrange store setup'),'Paid state missing');
check(!str_contains($html,'Generate $5 setup payment link'),'Paid service should not offer another invoice');
echo "PASS admin setup form, account targeting, payment permissions, pending link and paid state\n";
