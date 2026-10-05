<?php
declare(strict_types=1);
namespace App;

final class DistributorService
{
    public const OUTCOMES = ['pending' => 'Awaiting review', 'approved' => 'Distribution Ready', 'support_required' => 'Readiness Support Required', 'not_ready' => 'Not Yet Ready'];

    public static function ensureSchema(): void
    {
        static $ready = false;
        if ($ready) return;
        \db()->query("CREATE TABLE IF NOT EXISTS distributor_applications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            vendor_id BIGINT UNSIGNED NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            application_data LONGTEXT NOT NULL,
            product_limit INT UNSIGNED NULL,
            review_notes TEXT NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            submitted_at DATETIME NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_distributor_status (status, submitted_at),
            INDEX idx_distributor_vendor (vendor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
    }

    public static function forUser(int $userId): ?array
    {
        try { return \db()->fetch('SELECT * FROM distributor_applications WHERE user_id=?', [$userId]) ?: null; }
        catch (\PDOException $e) { if (($e->errorInfo[1] ?? 0) === 1146) return null; throw $e; }
    }

    public static function allowance(int $vendorId): ?array
    {
        try { return \db()->fetch("SELECT product_limit FROM distributor_applications WHERE vendor_id=? AND status='approved' LIMIT 1", [$vendorId]) ?: null; }
        catch (\PDOException $e) { if (($e->errorInfo[1] ?? 0) === 1146) return null; throw $e; }
    }

    public static function fields(): array
    {
        $yes = ['Yes','No'];
        $ready = ['Yes','No','Not available yet','Not applicable'];
        return [
            'Business' => [
                'business_name'=>['Registered business name','text',true], 'registration_number'=>['Registration number','text',true],
                'registration_country'=>['Country of registration','country',true], 'business_type'=>['Business type',['Manufacturer','Brand owner','Wholesaler','Distributor','Cooperative','Other'],true],
                'year_founded'=>['Year founded','year',true], 'website'=>['Website','url',false],
                'contact_name'=>['Full name','text',true], 'job_title'=>['Job title','text',true], 'email'=>['Email address','email',true],
                'phone'=>['Phone number (include country code)','tel',true], 'address'=>['Business address','text',true], 'city'=>['City','text',true], 'country'=>['Country','country',true],
            ],
            'Products' => [
                'category'=>['Primary product category','text',true], 'sku_count'=>['Number of SKUs','number',true], 'brands'=>['Product / brand names','textarea',true],
                'manufacturing_location'=>['Manufacturing location','text',true], 'own_facility'=>['Own manufacturing facility?',$yes,true],
                'monthly_capacity'=>['Average monthly production capacity (include units)','text',true], 'moq'=>['Minimum order quantity (MOQ)','text',false],
                'wholesale'=>['Do you currently sell wholesale?',$yes,true], 'export'=>['Do you currently export?',$yes,true],
                'current_markets'=>['Current markets / countries sold in','text',false], 'wholesale_pricing'=>['Can you provide wholesale pricing?',$yes,true],
                'scalable'=>['Can production scale for large orders?',$yes,true], 'lead_time'=>['Typical production lead time','text',false], 'private_label'=>['Private label capability?',$yes,false],
            ],
            'Compliance' => [
                'barcode'=>['UPC / GTIN barcode?',$ready,true], 'ingredients'=>['Complete ingredient list?',$ready,true], 'allergens'=>['Allergen information on label?',$ready,true],
                'nutrition'=>['Nutrition Facts panel?',$ready,true], 'origin_label'=>['Country of origin on label?',$ready,true], 'shelf_life'=>['Documented shelf-life / expiry data?',$ready,true],
                'fda_compliant'=>['FDA-compliant for U.S. sale, if applicable?',$ready,true], 'fda_registered'=>['Facility FDA registered, if required?',$ready,true],
                'export_documents'=>['Export documentation available?',$ready,true], 'specifications'=>['Product specifications / technical sheets?',$ready,true],
                'certifications'=>['Certifications currently held','textarea',false], 'compliance_support'=>['Would you like support with missing compliance requirements?',$yes,true],
            ],
            'Retail' => [
                'retail'=>['Currently sold in retail stores?',$yes,true], 'distributor'=>['Currently work with a distributor?',$yes,true], 'retailers'=>['Current retailers / distributors','text',false],
                'us_inventory'=>['Hold inventory in the U.S.?',$yes,true], 'case_supply'=>['Can supply products by the case?',$yes,true],
                'channel'=>['Primary channel',['Grocery retail','Specialty retail','Food service','Wholesale','Online','Other'],true],
                'target_market'=>['Primary target market','text',true], 'seeking'=>['What are you seeking from Seller Africa?','textarea',true],
                'samples'=>['Provide product samples for evaluation?',$yes,true], 'margins'=>['Open to distributor pricing / margins?',$yes,true], 'goals'=>['Tell us about your retail goals','textarea',false],
            ],
            'Review' => [
                'catalog_available'=>['Product catalog / sell sheet available?',$ready,false], 'prices_available'=>['Wholesale price list available?',$ready,false], 'photos_available'=>['Product photos available?',$ready,false],
                'accurate'=>['I confirm the information provided is accurate.','checkbox',true],
                'authorize'=>['I authorize Seller Africa to review my brand for distribution.','checkbox',true],
                'no_guarantee'=>['I understand application does not guarantee retail placement.','checkbox',true],
                'applicant_name'=>['Applicant full name','text',true],
            ],
        ];
    }

    public static function validate(array $data): array
    {
        $errors = [];
        foreach (self::fields() as $fields) foreach ($fields as $key => [$label,$type,$required]) {
            $value = $data[$key] ?? '';
            if (!is_string($value)) { $errors[$key] = 'Enter a valid value.'; continue; }
            if ($required && trim($value) === '') { $errors[$key] = $label . ' is required.'; continue; }
            if ($value === '') continue;
            if (strlen($value) > 5000) $errors[$key] = 'Use 5,000 characters or fewer.';
            elseif (is_array($type) && !in_array($value,$type,true)) $errors[$key] = 'Choose an available option.';
            elseif ($type === 'checkbox' && $value !== '1') $errors[$key] = 'Please confirm this declaration.';
            elseif ($type === 'email' && !filter_var($value,FILTER_VALIDATE_EMAIL)) $errors[$key] = 'Enter a valid email address.';
            elseif ($type === 'url' && (!filter_var($value,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true))) $errors[$key] = 'Enter a full https:// website address.';
            elseif ($type === 'tel' && !preg_match('/^\+?[0-9 ()-]{7,25}$/',$value)) $errors[$key] = 'Enter your phone number with country code.';
            elseif ($type === 'year' && (!ctype_digit($value) || (int)$value < 1800 || (int)$value > (int)date('Y'))) $errors[$key] = 'Enter a valid founding year.';
            elseif ($type === 'number' && (!ctype_digit($value) || (int)$value < 1 || (int)$value > 10000000)) $errors[$key] = 'Enter a whole number between 1 and 10,000,000.';
        }
        return $errors;
    }

    public static function page(string $route): void
    {
        if (!in_array($route,['distributor','distributor/register','dashboard/distributors'],true)) { http_response_code(404); return; }
        self::ensureSchema();
        if ($route === 'dashboard/distributors') { self::admin(); return; }
        $user = AuthService::user();
        $application = $user ? self::forUser((int)$user['id']) : null;
        $applicationMeta = json_decode((string)($application['application_data'] ?? '{}'), true) ?: [];
        if ($route === 'distributor' && $user && $application && $application['status'] !== 'draft') {
            $payment = DistributorPaymentService::latest((int)$application['id']);
            if (isset($_GET['payment']) || (!empty($applicationMeta['retail_payment_required']) && empty($payment['paid_at']))) {
                DistributorPaymentService::page($user, $application);
                return;
            }
        }
        if ($route === 'distributor' && $application && $application['status'] === 'approved') {
            $role = \db()->fetch('SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.code="vendor"',[$user['id']]);
            if ($role) $_SESSION['roles'] = array_values(array_unique(array_merge($_SESSION['roles'] ?? [],['vendor'])));
            VendorDashboardService::page('dashboard');
            return;
        }
        $data = json_decode((string)($application['application_data'] ?? '{}'),true) ?: [];
        $errors = []; $saved = false;
        if ($route === 'distributor/register') {
            if (!$user) {
                $_SESSION['intended_url'] = \app_url('distributor/register');
                $_SESSION['distributor_registration_return'] = true;
            } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                \validateCsrf();
                foreach (self::fields() as $fields) foreach ($fields as $key => $field) $data[$key] = is_scalar($_POST[$key] ?? null) ? trim((string)$_POST[$key]) : '';
                $data['email'] = $user['email'];
                $submit = ($_POST['intent'] ?? '') === 'submit';
                $errors = self::validate($data);
                $status = $submit && !$errors ? 'pending' : 'draft';
                $data['application_date'] = date('Y-m-d');
                $data['retail_payment_required'] = true;
                $pdo = \db()->pdo(); $pdo->beginTransaction();
                try {
                    \db()->fetch('SELECT id FROM users WHERE id=? FOR UPDATE',[$user['id']]);
                    $current = self::forUser((int)$user['id']);
                    if ($current && in_array($current['status'],['pending','approved'],true)) throw new \RuntimeException('Your application is already submitted.');
                    \db()->query('INSERT INTO distributor_applications (user_id,status,application_data,submitted_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), application_data=VALUES(application_data), submitted_at=VALUES(submitted_at)',[$user['id'],$status,json_encode($data),$status === 'pending' ? \sql_now() : null]);
                    $pdo->commit(); $saved = true;
                    if ($status === 'pending') {
                        self::notify($user['email'],'Distribution application received','Thank you, ' . $data['contact_name'] . '. Our team will review your product fit, readiness, compliance and supply capacity.');
                        try { NotificationService::notifySuperAdmins('Distribution application submitted','New distribution application','<p>' . \e($data['business_name']) . '</p>', ['button_url'=>\app_url('dashboard/distributors'),'button_text'=>'Review application']); } catch (\Throwable $e) { \error_log($e->getMessage()); }
                        \redirect('distributor?payment=1');
                    }
                    if (!$submit) $errors = [];
                    $application = self::forUser((int)$user['id']);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors['_form'] = 'We could not save this application. Refresh its status and try again.';
                    \error_log('Distributor application: ' . $e->getMessage());
                }
            }
        }
        if ($route === 'distributor' && !$user) { $_SESSION['intended_url']=\app_url('distributor'); \redirect('login'); }
        header('Cache-Control: private, no-store');
        \render_layout('vendor-onboarding','distributor/page.php',compact('route','user','application','data','errors','saved')+['title'=>'Seller Africa Distribution']);
    }

    private static function notify(string $email,string $subject,string $message): void
    {
        try { NotificationService::enqueueEmail($email,$subject,$subject,'<p>' . \e($message) . '</p>',['button_url'=>\app_url('distributor'),'button_text'=>'View distribution application']); }
        catch (\Throwable $e) { \error_log('Distributor email: ' . $e->getMessage()); }
    }

    private static function admin(): void
    {
        RoleDashboardService::requireRole('admin');
        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            \validateCsrf();
            try { self::review($_POST,(int)AuthService::user()['id']); \redirect('dashboard/distributors'); }
            catch (\Throwable $e) { $error = $e instanceof \RuntimeException ? $e->getMessage() : 'Unable to save review.'; \error_log($e->getMessage()); }
        }
        $status = (string)($_GET['status'] ?? 'pending');
        if (!in_array($status,array_keys(self::OUTCOMES),true)) $status='pending';
        $counts=array_fill_keys(array_keys(self::OUTCOMES),0);
        foreach (\db()->fetchAll('SELECT a.status,COUNT(*) AS total FROM distributor_applications a JOIN users u ON u.id=a.user_id GROUP BY a.status') as $count) {
            if (array_key_exists($count['status'],$counts)) $counts[$count['status']]=(int)$count['total'];
        }
        $totalPages=max(1,(int)ceil($counts[$status]/25));
        $page=min($totalPages,max(1,(int)($_GET['page'] ?? 1))); $offset=($page-1)*25;
        $rows=\db()->fetchAll("SELECT a.*,u.email FROM distributor_applications a JOIN users u ON u.id=a.user_id WHERE a.status=? ORDER BY a.submitted_at DESC LIMIT 25 OFFSET $offset",[$status]);
        \render_layout('admin','distributor/admin.php',compact('rows','status','page','error','counts','totalPages')+['title'=>'Distributors','active'=>'distributors','styles'=>[\asset('css/distributor-admin.css?v=1')]]);
    }

    public static function review(array $input,int $adminId): void
    {
        $status=(string)($input['status'] ?? '');
        if (!in_array($status,['approved','support_required','not_ready'],true)) throw new \RuntimeException('Choose a review outcome.');
        $limit=trim((string)($input['product_limit'] ?? ''));
        if ($status==='approved' && (!ctype_digit($limit) || (int)$limit < 1 || (int)$limit > 1000000)) throw new \RuntimeException('Set a catalog allowance between 1 and 1,000,000 products.');
        $notes=trim((string)($input['review_notes'] ?? ''));
        if ($status!=='approved' && $notes==='') throw new \RuntimeException('Add feedback to help the applicant improve readiness.');
        $pdo=\db()->pdo(); $pdo->beginTransaction();
        try {
            $row=\db()->fetch('SELECT * FROM distributor_applications WHERE id=? FOR UPDATE',[(int)($input['id'] ?? 0)]);
            if (!$row || $row['status']==='draft') throw new \RuntimeException('A submitted application is required.');
            $data=json_decode($row['application_data'],true) ?: [];
            if ($row['status']==='approved' && $status!=='approved') throw new \RuntimeException('This distributor is already approved. Suspend selling access from the vendor administration page before changing onboarding status.');
            if ($status==='approved' && self::validate($data)) throw new \RuntimeException('Required application details are missing.');
            if ($status==='approved' && !empty($data['retail_payment_required']) && empty(DistributorPaymentService::latest((int)$row['id'])['paid_at'])) throw new \RuntimeException('The retail placement package payment must be completed before approval.');
            $user=\db()->fetch('SELECT * FROM users WHERE id=? FOR UPDATE',[$row['user_id']]);
            $vendorId=$row['vendor_id'];
            if ($status==='approved') {
                $vendor=\db()->fetch('SELECT id FROM vendors WHERE user_id=? LIMIT 1',[$row['user_id']]);
                if (!$vendor) {
                    \db()->query('INSERT INTO vendors (user_id,store_name,store_slug,store_email,store_phone,status,kyc_status,created_at,updated_at) VALUES (?,?,?,?,?,"active","approved",NOW(),NOW())',[$row['user_id'],$data['business_name'],'distribution-' . $row['id'] . '-' . bin2hex(random_bytes(4)),$user['email'],$data['phone']]);
                    $vendorId=(int)\db()->lastInsertId();
                } else {
                    $vendorId=(int)$vendor['id'];
                    \db()->query('UPDATE vendors SET status="active",kyc_status="approved",updated_at=NOW() WHERE id=?',[$vendorId]);
                }
                $role=\db()->fetch('SELECT id FROM roles WHERE code="vendor" LIMIT 1');
                if (!$role) throw new \RuntimeException('Vendor role is not configured.');
                if (!\db()->fetch('SELECT 1 FROM user_roles WHERE user_id=? AND role_id=?',[$row['user_id'],$role['id']])) \db()->query('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)',[$row['user_id'],$role['id']]);
            }
            \db()->query('UPDATE distributor_applications SET status=?,vendor_id=?,product_limit=?,review_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$status,$vendorId,$status==='approved'?(int)$limit:null,$notes,$adminId,$row['id']]);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        \audit('distributor.reviewed','distributor_applications',(string)$row['id'],['status'=>$row['status']],['status'=>$status,'product_limit'=>$limit,'review_notes'=>$notes],$adminId);
        self::notify($user['email'],'Distribution application: ' . self::OUTCOMES[$status],$notes ?: 'Your distribution application is approved. Your distributor workspace is ready.');
    }
}
