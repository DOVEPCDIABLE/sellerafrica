<?php
require __DIR__.'/../app/services/PriorityMembershipService.php';
use App\PriorityMembershipService as P;
function check($value,$label){if(!$value)throw new RuntimeException($label);echo "PASS $label\n";}
check(P::plans()['monthly']['amount']===500,'Monthly price');
check(P::plans()['annual']['amount']===5000,'Annual price');
foreach(P::plans() as $code=>$plan){
    $row=['plan'=>$code,'reference'=>'test-reference'];
    $sub=json_decode(json_encode(['metadata'=>['context'=>P::CONTEXT,'reference'=>'test-reference'],'items'=>['data'=>[['quantity'=>1,'price'=>['unit_amount'=>$plan['amount'],'currency'=>'usd','recurring'=>['interval'=>$plan['interval'],'interval_count'=>1]]]]]]));
    check(P::subscriptionMatches($row,$sub),"$code valid subscription");
    $sub->items->data[0]->price->unit_amount=1;
    check(!P::subscriptionMatches($row,$sub),"$code wrong amount rejected");
    $sub->items->data[0]->price->unit_amount=$plan['amount'];
    $sub->metadata->reference='another-account';
    check(!P::subscriptionMatches($row,$sub),"$code wrong reference rejected");
}
$member=['checkout_paid_at'=>date('Y-m-d H:i:s'),'status'=>'active','current_period_end'=>date('Y-m-d H:i:s',time()+3600)];
check(P::active($member),'Paid membership active');
$member['checkout_paid_at']=null;
check(!P::active($member),'Unpaid membership denied');
$member['checkout_paid_at']='2026-01-01';$member['current_period_end']='2020-01-01';
check(!P::active($member),'Expired membership denied');
check(P::managed('invalid')===null,'Invalid management token denied');
