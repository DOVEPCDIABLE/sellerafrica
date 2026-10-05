<?php
declare(strict_types=1);
namespace App {
    final class NotificationService {
        public static array $emails = [];
        public static function notifySuperAdmins(...$args): void { self::$emails[] = $args; }
        public static function enqueueEmail(...$args): void { self::$emails[] = $args; }
    }
    final class VendorSubscriptionService {
        public static float $price = 0;
        public static function package(int $id): array { return ['price' => self::$price]; }
    }
    final class VendorRegistrationPaymentService {
        public static function paid(int $id, array $data): bool { return true; }
    }
}
namespace {
    require dirname(__DIR__) . '/app/services/VendorOnboardingService.php';
    require dirname(__DIR__) . '/app/services/ProductCategoryService.php';
    final class OnboardingTestDb {
        public array $data = [], $queries = [];
        public string $status = 'not_started';
        public bool $transaction = false, $paid = false;
        public function pdo(): self { return $this; }
        public function beginTransaction(): void { $this->transaction = true; }
        public function commit(): void { $this->transaction = false; }
        public function rollBack(): void { $this->transaction = false; }
        public function inTransaction(): bool { return $this->transaction; }
        public function lastInsertId(): int { return 123; }
        public function fetchAll(string $sql, array $params=[]): array { return []; }
        public function fetch(string $sql, array $params = []): ?array {
            if (str_contains($sql, 'FROM categories')) return ($params[0] ?? 0) === 4 ? ['id'=>4,'slug'=>'food-groceries','is_active'=>1] : null;
            if (str_contains($sql, 'vendor_packages')) return ['price' => \App\VendorSubscriptionService::$price];
            if (str_contains($sql, 'vendor_subscriptions')) return $this->paid ? ['id' => 1] : null;
            if (str_contains($sql, 'vendor_applications')) return ['id' => 10, 'application_data' => json_encode($this->data)];
            if (str_contains($sql, 'FOR UPDATE')) return ['status' => 'pending', 'kyc_status' => $this->status];
            return null;
        }
        public function query(string $sql, array $params = []): void {
            $this->queries[] = [$sql, $params];
            if (str_contains($sql, 'UPDATE vendor_applications')) $this->data = json_decode($params[1], true);
            if (str_contains($sql, 'UPDATE vendors SET store_name')) $this->status = 'pending';
        }
    }
    $db = new OnboardingTestDb();
    function db(): OnboardingTestDb { return $GLOBALS['db']; }
    function sql_now(): string { return '2026-09-20 12:00:00'; }
    function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
    function app_url(string $path): string { return 'https://sellerafrica.com/' . $path; }
    function check(bool $pass, string $label): void { if (!$pass) throw new \RuntimeException($label); echo "PASS: $label\n"; }
    $v = ['store_name'=>'Review fixture', 'product_name'=>'Produce', 'product_category_id'=>'4', 'product_description'=>'Fresh produce', 'product_regular_price'=>'10', 'product_weight'=>'1', 'product_length'=>'10', 'product_width'=>'5', 'product_height'=>'5', 'fulfillment_method'=>'Ship orders yourself', 'monthly_shipment'=>'No', 'terms_consent'=>'1'];
    $data = ['onboarding'=>['version'=>2,'package_id'=>1], 'verification'=>$v, 'file_ids'=>['store_banner'=>1,'product_image'=>2]];
    check(count(array_filter(\App\VendorOnboardingService::checks($data), fn($c)=>!$c['done'])) === 0, 'Required fields reach 100% without optional fields');
    foreach (array_keys($v) as $key) {
        $bad = $data; $bad['verification'][$key] = '';
        check(count(array_filter(\App\VendorOnboardingService::checks($bad), fn($c)=>!$c['done'])) > 0, 'Required field: ' . $key);
    }
    check(\App\VendorOnboardingService::paymentReady(1,$data), 'Free plan skips payment');
    \App\VendorSubscriptionService::$price = 10;
    check(!\App\VendorOnboardingService::paymentReady(1,$data), 'Unpaid plan blocked');
    $db->paid = true;
    check(\App\VendorOnboardingService::paymentReady(1,$data), 'Confirmed paid plan allowed');
    $save = new \ReflectionMethod(\App\VendorOnboardingService::class, 'save');
    $vendor = ['id'=>1,'store_name'=>'Draft']; $user=['id'=>2,'email'=>'fixture@example.invalid'];
    $db->data = ['onboarding'=>['version'=>2,'package_id'=>1]]; $_POST = ['store_name'=>'Draft']; $_FILES=[];
    try { $save->invoke(null,$vendor,$user,[],[],true); throw new \LogicException('Missing details accepted'); }
    catch (\RuntimeException $e) { check(str_contains($e->getMessage(),'Please complete:'), 'Incomplete submission returns missing fields'); }
    check($db->data['verification']['store_name']==='Draft' && !$db->transaction, 'Incomplete submission saves draft');
    $db->data = $data; $_POST = ['terms_consent'=>'1'];
    $save->invoke(null,$vendor,$user,[],[],true);
    check($db->status==='pending' && $db->data['onboarding']['submitted'], 'Complete submission enters pending review');
    $productInserts = array_filter($db->queries, fn($q)=>str_contains($q[0],'INSERT INTO products'));
    check(count($productInserts)===1, 'Product created once');
    check(count(array_filter($db->queries, fn($q)=>str_contains($q[0],'INSERT IGNORE INTO product_categories') && $q[1] === [123,4]))===1, 'Registration product category persisted');
    check(count(\App\NotificationService::$emails)===2, 'Submission notifications queued');
    try { $save->invoke(null,$vendor,$user,[],[],true); throw new \LogicException('Duplicate accepted'); }
    catch (\RuntimeException $e) { check(str_contains($e->getMessage(),'already been submitted'), 'Duplicate submission blocked'); }
    check(count(array_filter($db->queries, fn($q)=>str_contains($q[0],'INSERT INTO products')))===1, 'No duplicate product');
}
