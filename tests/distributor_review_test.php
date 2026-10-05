<?php
declare(strict_types=1);
namespace App {
    final class NotificationService { public static array $sent=[]; public static function enqueueEmail(...$args): void { self::$sent[]=$args; } }
}
namespace {
require dirname(__DIR__).'/app/services/DistributorService.php';
final class ReviewDb {
    public array $queries=[],$application=[]; public bool $transaction=false; public int $vendorId=0;
    public function pdo(): self{return $this;}
    public function beginTransaction(): void{$this->transaction=true;}
    public function commit(): void{$this->transaction=false;}
    public function rollBack(): void{$this->transaction=false;}
    public function inTransaction(): bool{return $this->transaction;}
    public function lastInsertId(): int{return 50;}
    public function fetch(string $sql,array $params=[]): ?array {
        if(str_contains($sql,'FROM distributor_applications'))return $this->application;
        if(str_contains($sql,'FROM users'))return ['id'=>2,'email'=>'fixture@example.invalid'];
        if(str_contains($sql,'FROM vendors'))return $this->vendorId?['id'=>$this->vendorId]:null;
        if(str_contains($sql,'FROM roles'))return ['id'=>3];
        return null;
    }
    public function query(string $sql,array $params=[]): void {
        $this->queries[]=[$sql,$params];
        if(str_contains($sql,'INSERT INTO vendors'))$this->vendorId=50;
        if(str_contains($sql,'UPDATE distributor_applications')){$this->application['status']=$params[0];$this->application['vendor_id']=$params[1];}
    }
}
$db=new ReviewDb();function db(): ReviewDb{return $GLOBALS['db'];}function e($s): string{return htmlspecialchars((string)$s);}function app_url($s): string{return '/'.$s;}function audit(...$args): void{}
function expect(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$data=[];foreach(\App\DistributorService::fields() as $group)foreach($group as $key=>[$label,$type])$data[$key]=is_array($type)?$type[0]:match($type){'checkbox'=>'1','email'=>'fixture@example.invalid','tel'=>'+12025550123','year'=>'2020','number'=>'100','url'=>'https://example.com',default=>'Business'};
$db->application=['id'=>1,'user_id'=>2,'vendor_id'=>null,'status'=>'pending','application_data'=>json_encode($data)];
try{\App\DistributorService::review(['id'=>1,'status'=>'approved','product_limit'=>''],9);throw new LogicException('Missing allowance accepted');}catch(RuntimeException $e){expect(str_contains($e->getMessage(),'allowance'),'Approval requires catalog allowance');}
\App\DistributorService::review(['id'=>1,'status'=>'support_required','review_notes'=>'Please confirm labeling.'],9);
expect($db->vendorId===0,'Support outcome does not grant vendor access');
\App\DistributorService::review(['id'=>1,'status'=>'approved','product_limit'=>'500'],9);
expect($db->application['status']==='approved' && $db->vendorId===50,'Approval provisions vendor workspace');
expect(count(\App\NotificationService::$sent)===2,'Review outcomes send applicant emails');
\App\DistributorService::review(['id'=>1,'status'=>'approved','product_limit'=>'1000'],9);
expect(count(array_filter($db->queries,fn($q)=>str_contains($q[0],'INSERT INTO vendors')))===1,'Repeated approval does not create duplicate vendor');
expect(!$db->transaction,'Review transaction committed');
}
