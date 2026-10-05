<?php
if (PHP_SAPI!=='cli' || empty($argv[1]) || empty($argv[2])) exit(1);
require $argv[1].'/app/core/bootstrap.php';
require $argv[2];
use App\ProductVariationService as V;
function verify($condition) { if (!$condition) throw new RuntimeException('Variation integration assertion failed'); }
$pdo=db()->pdo();$pdo->beginTransaction();
try {
 $vendor=db()->fetch("SELECT id FROM vendors WHERE status='active' AND kyc_status='approved' LIMIT 1");
 $slug='variation-rollback-test-'.bin2hex(random_bytes(8));
 db()->query("INSERT INTO products (vendor_id,sku,name,slug,type,status,description,regular_price,currency,weight,length,width,height) VALUES (?,?,?,?,'simple','pending','Integration fixture',10,'USD',1,2,3,4)",[$vendor['id'],$slug,'Variation rollback fixture',$slug]);
 $parent=(int)db()->lastInsertId();
 db()->query("UPDATE products SET type='variable' WHERE id=?",[$parent]);
 V::assertPurchasable($parent,1);
 verify(V::picker($parent)==='');
 $file=db()->fetch("SELECT id FROM files WHERE mime_type LIKE 'image/%' LIMIT 1");
 db()->query("INSERT INTO product_media (product_id,file_id,role) VALUES (?,?,'primary')",[$parent,$file['id']]);
 $rows=V::validate([['size'=>'M','colour'=>'Red','price'=>'15','stock'=>'3'],['size'=>'L','colour'=>'Blue','price'=>'20','stock'=>'0','shipping_weight'=>'2']]);
 V::save($parent,(int)$vendor['id'],$rows,fn()=>throw new RuntimeException('Unexpected upload'));
 $saved=V::rows($parent);verify(count($saved)===2);verify((float)$saved[1]['weight']===2.0);verify(!empty($saved[0]['image_path']));
 $blocked=false;try{V::assertPurchasable((int)$saved[0]['id'],1);}catch(DomainException $e){$blocked=true;}verify($blocked);
 db()->query("UPDATE products SET status='active' WHERE id=?",[$parent]);
 V::assertPurchasable((int)$saved[0]['id'],3);
 foreach ([[$saved[0]['id'],4],[$saved[1]['id'],1],[$parent,1]] as [$id,$qty]) {
  $blocked=false;try{V::assertPurchasable((int)$id,$qty);}catch(DomainException $e){$blocked=true;}verify($blocked);
 }
 $bad=$rows;$bad[0]['id']=$parent;
 $blocked=false;try{V::save($parent,(int)$vendor['id'],$bad,fn()=>0);}catch(RuntimeException $e){$blocked=true;}verify($blocked);
 $rows[0]['id']=(int)$saved[0]['id'];$rows[0]['price']=18;
 V::save($parent,(int)$vendor['id'],[$rows[0]],fn()=>0);
 verify(count(V::rows($parent))===1);verify((int)V::rows($parent)[0]['id']===(int)$saved[0]['id']);
 verify(db()->fetch('SELECT status FROM products WHERE id=?',[$saved[1]['id']])['status']==='archived');
 verify(str_contains(V::picker($parent),'Size: M'));
 V::save($parent,(int)$vendor['id'],[],fn()=>0);verify(V::rows($parent)===[]);verify(V::picker($parent)==='');
 echo "Database integration passed; all fixture changes rolled back\n";
} finally {if($pdo->inTransaction())$pdo->rollBack();}
