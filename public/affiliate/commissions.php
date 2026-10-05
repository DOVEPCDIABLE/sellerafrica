<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/core/bootstrap.php';
\App\RoleDashboardService::requireRole('affiliate');
\App\RoleDashboardService::portal('affiliate', 'commissions', 'Commissions', \App\RoleDashboardService::stats('affiliate'), \App\RoleDashboardService::rows('affiliate', 'commissions'));
