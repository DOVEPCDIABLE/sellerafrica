<?php
require __DIR__.'/../app/services/PriorityPaystackService.php';
use App\PriorityPaystackService as P;
$row=['reference'=>'SA-PRIORITY-NGN-test','email'=>'buyer@example.com','amount_minor'=>750000];
$valid=['status'=>'success','reference'=>$row['reference'],'currency'=>'NGN','amount'=>750000,'customer'=>['email'=>'buyer@example.com']];
function check($value,$label){if(!$value)throw new RuntimeException($label);echo "PASS $label\n";}
check(P::matches($row,$valid),'Verified naira payment accepted');
foreach(['status'=>'pending','reference'=>'wrong','currency'=>'USD','amount'=>500,'customer'=>['email'=>'other@example.com']] as $key=>$value){$bad=$valid;$bad[$key]=$value;check(!P::matches($row,$bad),'Reject mismatched '.$key);}
