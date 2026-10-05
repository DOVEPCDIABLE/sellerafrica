<?php
declare(strict_types=1);
require __DIR__ . '/../app/services/ProductCategoryService.php';
require __DIR__ . '/../app/services/VendorOnboardingService.php';
require __DIR__ . '/../app/services/VendorDashboardService.php';
function db() {
    return new class {
        function fetch($sql, $params=[]) {
            if (str_contains($sql, 'SELECT * FROM products')) return ['id'=>1,'vendor_id'=>1];
            return match ((int)($params[0] ?? 0)) {
                4 => ['id'=>4,'slug'=>'food-groceries','is_active'=>1],
                11 => ['id'=>11,'slug'=>'uncategorized','is_active'=>1],
                51 => ['id'=>51,'slug'=>'sac-marketplace-subscription-plans','is_active'=>1],
                52 => ['id'=>52,'slug'=>'inactive-category','is_active'=>0],
                default => false,
            };
        }
    };
}
foreach ([null,'',0,-1,'4x','1 OR 1=1',[],11,51,52,9999] as $value) {
    try {
        \App\ProductCategoryService::requireCategory($value);
        throw new LogicException('Invalid category accepted.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Please select a product category from the list.') throw $e;
    }
}
if (\App\ProductCategoryService::requireCategory('4') !== 4) throw new LogicException('Valid category refused.');
foreach ([[],['product_category_id'=>'9999'],['product_category_id'=>'4']] as $verification) {
    $check = \App\VendorOnboardingService::checks(['verification'=>$verification])[0];
    if ($check['done'] !== (($verification['product_category_id'] ?? '') === '4')) throw new LogicException('Verification category requirement failed.');
}
$_POST=['product_id'=>1];
$method=new ReflectionMethod(\App\VendorDashboardService::class,'updateProduct');
// A vendor cannot omit the category even when calling the backend directly.
try {
    // The first query supplies an owned product; category lookup then refuses the missing value.
    $method->invoke(null,['id'=>1],['id'=>1]);
    throw new LogicException('Missing category accepted by edit endpoint.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Please select a product category from the list.') throw $e;
}
echo "Category allowlist and verification requirements passed.\n";
