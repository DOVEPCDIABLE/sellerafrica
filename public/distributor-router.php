<?php
declare(strict_types=1);
if (($_GET['route'] ?? '') === 'distributors') {
    require __DIR__ . '/storefront/distributors.php';
} else {
    require __DIR__ . '/distributor.php';
}
