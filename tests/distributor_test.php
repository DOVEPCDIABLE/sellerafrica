<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/services/DistributorService.php';
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
$data=[];
foreach (\App\DistributorService::fields() as $group) foreach ($group as $key=>[$label,$type,$required]) {
    $data[$key]=is_array($type)?$type[0]:match($type) {'checkbox'=>'1','email'=>'owner@example.com','url'=>'https://example.com','tel'=>'+12025550123','year'=>'2020','number'=>'100',default=>'Sample business'};
}
check(\App\DistributorService::validate($data)===[],'Complete PDF application accepted');
foreach (\App\DistributorService::fields() as $group) foreach ($group as $key=>[$label,$type,$required]) {
    $copy=$data; $copy[$key]='';
    check(isset(\App\DistributorService::validate($copy)[$key])===$required,'Required/optional: '.$key);
    if (is_array($type)) { $copy[$key]='forged option'; check(isset(\App\DistributorService::validate($copy)[$key]),'Reject forged option: '.$key); }
}
foreach (['email'=>'invalid','phone'=>'letters','year_founded'=>'9999','sku_count'=>'-2','website'=>'javascript:alert(1)','accurate'=>'0'] as $key=>$bad) {
    $copy=$data; $copy[$key]=$bad; check(isset(\App\DistributorService::validate($copy)[$key]),'Reject invalid '.$key);
}
$copy=$data;
foreach (\App\DistributorService::fields()['Compliance'] as $key=>[$label,$type]) if (is_array($type) && in_array('Not available yet',$type,true)) $copy[$key]='Not available yet';
check(\App\DistributorService::validate($copy)===[],'Missing compliance documents do not disqualify applicant');
