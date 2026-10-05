<?php
require __DIR__ . '/../app/services/VendorOnboardingService.php';
require __DIR__ . '/../app/services/ProductCategoryService.php';
require __DIR__ . '/../app/services/VendorCompletionService.php';
use App\VendorCompletionService as C;
function db() {
    return new class {
        function fetch($sql, $params=[]) { return ['id'=>4,'slug'=>'food-groceries','is_active'=>1]; }
    };
}
function expect($actual, $expected): void {
    if ($actual !== $expected) { throw new RuntimeException(var_export([$actual, $expected], true)); }
}
$rows = [
    ['id'=>1],
    ['id'=>2,'logo_file_id'=>1],
    ['id'=>3,'logo_file_id'=>1,'banner_file_id'=>2],
    ['id'=>4,'logo_file_id'=>1,'banner_file_id'=>2,'imaged_product_count'=>1],
    ['id'=>5,'logo_file_id'=>1,'banner_file_id'=>2,'imaged_product_count'=>1,'ready_product_count'=>1],
];
$result = C::listing($rows, '');
expect(array_column($result['rows'], 'id'), [5,4,3,2,1]);
expect(array_values($result['summary']), [1,1,1,1,1]);
expect(array_column(C::listing($rows,'100')['rows'],'id'), [5]);
expect(C::listing($rows,'invalid')['filter'], '');
expect(C::listing([], '')['rows'], []);
foreach ([0=>'0-24',24=>'0-24',25=>'25-49',49=>'25-49',50=>'50-74',74=>'50-74',75=>'75-99',99=>'75-99',100=>'100'] as $percent=>$range) {
    expect(C::range($percent), $range);
}
$v = ['store_name'=>'Store','product_name'=>'Item','product_category_id'=>4,'product_description'=>'Description','product_regular_price'=>10,'product_weight'=>1,'product_length'=>1,'product_width'=>1,'product_height'=>1,'fulfillment_method'=>App\VendorOnboardingService::FULFILLMENT[0],'monthly_shipment'=>'No','terms_consent'=>'1'];
$data = ['onboarding'=>['version'=>2],'verification'=>$v,'file_ids'=>['store_banner'=>1,'product_image'=>2]];
expect(C::percent(['latest_application_data'=>json_encode($data)]), 100);
unset($data['verification']['terms_consent']);
expect(C::percent(['latest_application_data'=>json_encode($data)]), 93);
expect(C::percent(['latest_application_data'=>'bad json']), 0);
expect(array_column(C::listing([['id'=>1],['id'=>2]], '')['rows'],'id'), [2,1]);
expect(count(array_slice($result['rows'], 2, 2)), 2);
echo "Vendor completion tests passed\n";
