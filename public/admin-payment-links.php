<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/core/bootstrap.php';
\App\RoleDashboardService::requireRole('admin');
if(!\App\AuthService::hasRole('super_admin')){http_response_code(403);exit('Payment links are restricted to super administrators.');}
use App\AdminPaymentLinkService as Links;
Links::ensureSchema();
$error='';$notice='';$selected=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    validateCsrf();
    try {
        if(($_POST['action'] ?? '')==='email'){
            Links::email((int)($_POST['id'] ?? 0));$notice='Payment email queued for delivery.';
        } else {
            $id=Links::generate((string)($_POST['email'] ?? ''),(string)($_POST['name'] ?? ''),(string)($_POST['service'] ?? ''),(string)($_POST['provider'] ?? ''),(int)$_SESSION['user_id']);
            redirect('dashboard/payment-links?link='.$id);
        }
    }catch(Throwable $e){error_log('Admin payment link: '.$e->getMessage());$error=$e->getMessage();}
}
$customer=!empty($_GET['user'])?db()->fetch('SELECT display_name,email FROM users WHERE id=? AND status<>"deleted"',[(int)$_GET['user']]):null;
$selected=!empty($_GET['link'])?db()->fetch('SELECT * FROM admin_payment_links WHERE id=?',[(int)$_GET['link']]):null;
$catalog=Links::catalog();
$rows=db()->fetchAll('SELECT * FROM admin_payment_links ORDER BY id DESC LIMIT 50');
render_layout('admin','admin/payment-links.php',compact('catalog','rows','selected','customer','error','notice')+['title'=>'Payment Links','active'=>'payment-links']);
