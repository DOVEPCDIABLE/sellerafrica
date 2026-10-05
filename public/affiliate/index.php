<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/core/bootstrap.php';
\App\RoleDashboardService::requireRole('affiliate');
\App\RoleDashboardService::portal('affiliate', 'dashboard', 'Affiliate Dashboard', \App\RoleDashboardService::stats('affiliate'), \App\RoleDashboardService::rows('affiliate', 'dashboard'));
