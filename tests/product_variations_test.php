<?php
require __DIR__.'/../app/services/ProductVariationService.php';
use App\ProductVariationService as V;
function check($condition) { if (!$condition) throw new RuntimeException('Assertion failed'); }
function fails(callable $fn) { try { $fn(); } catch (RuntimeException $e) { return; } throw new RuntimeException('Expected validation failure'); }
$row=['size'=>'M','colour'=>'Red','price'=>'15.50','stock'=>'3'];
check(V::validate([])===[]);
$valid=V::validate([$row]);
check($valid[0]['price']===15.5 && $valid[0]['stock']===3 && $valid[0]['dimensions']['weight']===null);
fails(fn()=>V::validate([$row,$row]));
fails(fn()=>V::validate([array_merge($row,['size'=>'','colour'=>''])]));
foreach (['0','-1','NaN','Infinity',''] as $price) fails(fn()=>V::validate([array_merge($row,['price'=>$price])]));
foreach (['-1','1.5','abc',''] as $stock) fails(fn()=>V::validate([array_merge($row,['stock'=>$stock])]));
fails(fn()=>V::validate([array_merge($row,['shipping_weight'=>'0'])]));
check(V::validate([array_merge($row,['stock'=>'0','shipping_weight'=>'2'])])[0]['dimensions']['weight']===2.0);
fails(fn()=>V::validate(array_fill(0,51,$row)));
$_POST=[];check(V::posted()===null);
$_POST=['variation_editor'=>'1'];check(V::posted()===[]);
echo "Variation validation tests passed\n";
