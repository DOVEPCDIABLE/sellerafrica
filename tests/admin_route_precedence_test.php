<?php
$source=file_get_contents(__DIR__.'/../.htaccess');
preg_match_all('/^\s*RewriteRule\s+(\^dashboard\S*)\s+(\S+)\s+(\[[^\]]+\])/m',$source,$rules,PREG_SET_ORDER);
foreach(['dashboard/payment-links'=>'public/admin-payment-links.php','dashboard/payment-links/'=>'public/admin-payment-links.php','dashboard/priority-members'=>'public/priority-members.php','dashboard/vendors'=>'public/dashboard.php?view=$1'] as $path=>$expected){
    $matched=null;
    foreach($rules as $rule){if(preg_match('~'.$rule[1].'~',$path)){$matched=$rule;break;}}
    if(!$matched || $matched[2]!==$expected || !str_contains($matched[3],'QSA') || str_contains($matched[3],'R='))throw new RuntimeException('Wrong handler or redirect for '.$path);
    echo "PASS $path routes internally to $expected and preserves query parameters\n";
}
