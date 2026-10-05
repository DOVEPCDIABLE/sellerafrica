<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/core/bootstrap.php';
\App\RoleDashboardService::requireRole('admin');
\App\AdminAccessService::enforce('priority-members');
\App\PriorityMembershipService::ensureSchema();
$search=trim((string)($_GET['q'] ?? ''));
$params=$search===''?[]:['%'.$search.'%','%'.$search.'%'];
$where=$search===''?'':' WHERE name LIKE ? OR email LIKE ?';
$total=(int)(db()->fetch('SELECT COUNT(*) AS total FROM priority_memberships'.$where,$params)['total'] ?? 0);
$pages=max(1,(int)ceil($total/25));$page=min($pages,max(1,(int)($_GET['page'] ?? 1)));
$rows=db()->fetchAll('SELECT * FROM priority_memberships'.$where.' ORDER BY (first_paid_at IS NOT NULL) DESC,id DESC LIMIT 25 OFFSET '.(($page-1)*25),$params);
render_layout('admin','admin/priority-members.php',compact('rows','search','page','pages','total')+['title'=>'Priority Members','active'=>'priority-members']);
