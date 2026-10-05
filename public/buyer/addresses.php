<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/core/bootstrap.php';
\App\RoleDashboardService::requireRole('customer');
\App\RoleDashboardService::portal('buyer', 'addresses', 'Addresses', \App\RoleDashboardService::stats('buyer'), \App\RoleDashboardService::rows('buyer', 'addresses'));
