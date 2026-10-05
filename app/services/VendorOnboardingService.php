<?php
declare(strict_types=1);

namespace App;

final class VendorOnboardingService
{
    public const FULFILLMENT = ['Ship orders yourself', 'Send inventory to SAC U.S. warehouse', 'Join SAC consolidated export shipments'];

    public static function application(int $vendorId): array
    {
        return \db()->fetch('SELECT * FROM vendor_applications WHERE vendor_id = ? ORDER BY id DESC LIMIT 1', [$vendorId]) ?: [];
    }

    public static function data(array $application): array
    {
        $data = json_decode((string)($application['application_data'] ?? '{}'), true);
        $data = is_array($data) ? $data : [];
        if (!isset($data['verification']) && $application !== []) {
            $r = $data['readiness'] ?? [];
            $data['verification'] = ['store_name' => $application['business_name'] ?? '', 'product_description' => $r['product_description'] ?? '', 'product_regular_price' => $r['product_price'] ?? '', 'product_weight' => $r['product_weight'] ?? '', 'product_length' => $r['package_length'] ?? '', 'product_width' => $r['package_width'] ?? '', 'product_height' => $r['package_height'] ?? '', 'fulfillment_method' => $r['how_to_sell_through_sac'] ?? ''];
        }
        if (empty($data['verification']['product_category_id']) && !empty($data['onboarding']['product_id'])) {
            $data['verification']['product_category_id'] = ProductCategoryService::selected((int)$data['onboarding']['product_id']);
        }
        return $data;
    }

    public static function checks(array $data): array
    {
        $v = $data['verification'] ?? [];
        $checks = [];
        $categoryId = $v['product_category_id'] ?? null;
        if (!$categoryId && !empty($data['onboarding']['product_id'])) $categoryId = ProductCategoryService::selected((int)$data['onboarding']['product_id']);
        $checks[] = ['key' => 'product_category_id', 'label' => 'Product category', 'done' => ProductCategoryService::valid($categoryId)];
        foreach (['store_name' => 'Store name', 'product_name' => 'Product name', 'product_description' => 'Product description'] as $key => $label) {
            $checks[] = ['key' => $key, 'label' => $label, 'done' => trim((string)($v[$key] ?? '')) !== ''];
        }
        foreach (['product_regular_price' => 'Product price (USD)', 'product_weight' => 'Product weight (kg)', 'product_length' => 'Package length (cm)', 'product_width' => 'Package width (cm)', 'product_height' => 'Package height (cm)'] as $key => $label) {
            $value = $v[$key] ?? '';
            $checks[] = ['key' => $key, 'label' => $label, 'done' => is_numeric($value) && is_finite((float)$value) && (float)$value > 0];
        }
        foreach (['store_banner' => 'Store banner', 'product_image' => 'Product image'] as $key => $label) {
            $checks[] = ['key' => $key, 'label' => $label, 'done' => (int)($data['file_ids'][$key] ?? 0) > 0];
        }
        $checks[] = ['key' => 'fulfillment_method', 'label' => 'Fulfillment method', 'done' => in_array($v['fulfillment_method'] ?? '', self::FULFILLMENT, true)];
        $checks[] = ['key' => 'monthly_shipment', 'label' => 'Monthly shipment Yes / No', 'done' => in_array($v['monthly_shipment'] ?? '', ['Yes', 'No'], true)];
        $checks[] = ['key' => 'terms_consent', 'label' => 'Terms and conditions', 'done' => ($v['terms_consent'] ?? '') === '1'];
        return $checks;
    }

    public static function paymentReady(int $vendorId, array $data): bool
    {
        if (!VendorRegistrationPaymentService::paid($vendorId, $data)) return false;
        $packageId = (int)($data['onboarding']['package_id'] ?? 0);
        if ($packageId <= 0) return true; // Legacy applications have no selected onboarding plan.
        // Keep this read safe inside the verification transaction: package() runs schema DDL.
        $package = \db()->fetch('SELECT price FROM vendor_packages WHERE id = ? LIMIT 1', [$packageId]);
        if (!$package) return false;
        if ((float)$package['price'] <= 0) return true;
        return (bool)\db()->fetch("SELECT id FROM vendor_subscriptions WHERE vendor_id = ? AND package_id = ? AND status = 'active' AND paid_at IS NOT NULL AND (current_period_end IS NULL OR current_period_end > NOW()) LIMIT 1", [$vendorId, $packageId]);
    }

    public static function assertApprovable(int $vendorId): void
    {
        $data = self::data(self::application($vendorId));
        if (($data['onboarding']['version'] ?? 0) !== 2) return;
        if (empty($data['onboarding']['submitted']) || array_filter(self::checks($data), static fn ($check) => !$check['done']) || !self::paymentReady($vendorId, $data)) {
            throw new \RuntimeException('This vendor must complete payment (if applicable) and submit all required verification details before approval.');
        }
    }

    public static function page(): void
    {
        RoleDashboardService::requireRole('vendor');
        $user = AuthService::user();
        $vendor = \db()->fetch('SELECT * FROM vendors WHERE user_id = ? ORDER BY id LIMIT 1', [(int)$user['id']]);
        if (!$vendor) { \redirect('vendor/register'); return; }
        if ($vendor['status'] === 'active' && in_array($vendor['kyc_status'], ['approved', 'verified'], true)) { \redirect('vendor'); return; }
        $application = self::application((int)$vendor['id']);
        $data = self::data($application);
        $errors = [];
        $step = max(0, min(2, (int)($_GET['step'] ?? $_POST['step'] ?? 0)));
        $pending = $vendor['status'] !== 'rejected' && $vendor['kyc_status'] === 'pending';
        $paymentReady = self::paymentReady((int)$vendor['id'], $data);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            \validateCsrf();
            try {
                if (($_POST['intent'] ?? '') === 'pay') {
                    if ($paymentReady) \redirect('vendor/verification');
                    if (!VendorRegistrationPaymentService::paid((int)$vendor['id'], $data)) {
                        \redirect(VendorRegistrationPaymentService::checkout((int)$vendor['id']));
                    }
                    $provider = in_array($_POST['provider'] ?? '', ['stripe', 'paystack'], true) ? $_POST['provider'] : 'stripe';
                    $url = VendorSubscriptionService::createCheckoutSession((int)$vendor['id'], (int)($data['onboarding']['package_id'] ?? 0), \app_url('vendor/verification?payment_return=1'), \app_url('vendor/verification?cancelled=1'), $provider);
                    \redirect($url);
                }
                if (!VendorRegistrationPaymentService::paid((int)$vendor['id'], $data)) throw new \RuntimeException('Pay the compulsory NGN 1,500 registration fee before continuing verification. This fee applies to every plan.');
                if (!$paymentReady) throw new \RuntimeException('Complete payment for your selected plan before submitting verification.');
                if ($pending) throw new \RuntimeException('Your application is already awaiting review.');
                self::save($vendor, $user, $application, $data, ($_POST['intent'] ?? '') === 'submit');
                \redirect('vendor/verification?step=' . min(2, $step + 1));
            } catch (\Throwable $e) {
                $errors[] = $e instanceof \RuntimeException ? $e->getMessage() : 'We could not save your application. Please try again.';
                \error_log('Vendor verification: ' . $e->getMessage());
                $application = self::application((int)$vendor['id']);
                $data = self::data($application);
                $data['verification'] = array_merge($data['verification'] ?? [], self::posted());
                $missing = array_values(array_filter(self::checks($data), static fn ($check) => !$check['done']));
                if (($_POST['intent'] ?? '') === 'submit' && $missing !== []) {
                    $key = $missing[0]['key'];
                    $step = in_array($key, ['store_name', 'store_banner'], true) ? 0 : (in_array($key, ['fulfillment_method', 'monthly_shipment', 'terms_consent'], true) ? 2 : 1);
                }
            }
        }
        header('Cache-Control: private, no-store');
        \render_layout('vendor-onboarding', 'auth/vendor-verification.php', compact('vendor', 'user', 'application', 'data', 'errors', 'step', 'pending', 'paymentReady') + ['title' => 'Complete vendor verification']);
    }

    private static function posted(): array
    {
        $values = [];
        foreach (['store_name', 'product_name', 'product_category_id', 'product_description', 'product_regular_price', 'product_sale_price', 'product_weight', 'product_length', 'product_width', 'product_height', 'fulfillment_method', 'monthly_shipment', 'social_page', 'terms_consent'] as $key) {
            if (isset($_POST[$key]) && is_scalar($_POST[$key])) $values[$key] = trim((string)$_POST[$key]);
        }
        return $values;
    }

    private static function upload(string $key, int $userId, int $vendorId, array &$paths): ?int
    {
        $file = $_FILES[$key] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Choose a valid file of 5 MB or smaller for ' . str_replace('_', ' ', $key) . '.');
        $mime = mime_content_type($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (in_array($key, ['business_registration', 'identity'], true)) $extensions['application/pdf'] = 'pdf';
        if (!isset($extensions[$mime])) throw new \RuntimeException('Unsupported file type for ' . str_replace('_', ' ', $key) . '.');
        $relative = 'uploads/vendor/' . $vendorId . '/verification/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $path = APP_ROOT . '/public/' . $relative;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) throw new \RuntimeException('Unable to prepare upload folder.');
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new \RuntimeException('Unable to save your upload.');
        $paths[] = $path;
        $size = str_starts_with($mime, 'image/') ? getimagesize($path) : false;
        \db()->query('INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, created_at) VALUES (?, "public", ?, ?, ?, ?, ?, ?, ?)', [$userId, $relative, basename($file['name']), $mime, filesize($path), $size[0] ?? null, $size[1] ?? null, \sql_now()]);
        return (int)\db()->lastInsertId();
    }

    private static function save(array $vendor, array $user, array $application, array $data, bool $submit): void
    {
        $pdo = \db()->pdo();
        $paths = [];
        $pdo->beginTransaction();
        try {
            $locked = \db()->fetch('SELECT status, kyc_status FROM vendors WHERE id = ? FOR UPDATE', [$vendor['id']]);
            if (($locked['status'] !== 'rejected' && $locked['kyc_status'] === 'pending') || ($locked['status'] === 'active' && in_array($locked['kyc_status'], ['approved', 'verified'], true))) throw new \RuntimeException('This application has already been submitted. Refresh the page.');
            // Read the latest saved draft while holding the vendor lock.
            $application = self::application((int)$vendor['id']);
            $data = self::data($application);
            if (!self::paymentReady((int)$vendor['id'], $data)) throw new \RuntimeException('Complete payment for your selected plan before submitting verification.');
            $data['onboarding']['version'] = 2;
            $data['verification'] = array_merge($data['verification'] ?? [], self::posted());
            if ($submit) $data['verification']['terms_consent'] = ($_POST['terms_consent'] ?? '') === '1' ? '1' : '';
            foreach (['store_banner', 'product_image', 'store_profile_image', 'business_registration', 'identity'] as $field) {
                $id = self::upload($field, (int)$user['id'], (int)$vendor['id'], $paths);
                if ($id) $data['file_ids'][$field] = $id;
            }
            $v = $data['verification'];
            if (!empty($v['product_category_id'])) ProductCategoryService::requireCategory($v['product_category_id']);
            if (!empty($v['social_page']) && (!filter_var($v['social_page'], FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($v['social_page'], PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw new \RuntimeException('Enter a valid http or https link for your social media page, or leave it blank.');
            }
            $sale = $v['product_sale_price'] ?? '';
            if ($sale !== '' && (!is_numeric($sale) || (float)$sale <= 0 || (float)$sale >= (float)($v['product_regular_price'] ?? 0))) throw new \RuntimeException('Discount price must be greater than zero and less than the product price.');
            $missing = array_values(array_filter(self::checks($data), static fn ($c) => !$c['done']));
            // Save incomplete drafts, including uploads, before reporting missing submission fields.
            $complete = $submit && $missing === [];
            $data['onboarding']['submitted'] = $complete;
            $data['business']['business_name'] = $v['store_name'] ?? $vendor['store_name'];
            $data['readiness'] = array_merge($data['readiness'] ?? [], ['product_description' => $v['product_description'] ?? '', 'product_price' => $v['product_regular_price'] ?? '', 'product_weight' => $v['product_weight'] ?? '', 'package_length' => $v['product_length'] ?? '', 'package_width' => $v['product_width'] ?? '', 'package_height' => $v['product_height'] ?? '', 'how_to_sell_through_sac' => $v['fulfillment_method'] ?? '']);
            if ($complete) {
                $productId = (int)($data['onboarding']['product_id'] ?? 0);
                $params = [$v['product_name'], $v['product_description'], $v['product_regular_price'], $sale !== '' ? $sale : null, $v['product_weight'], $v['product_length'], $v['product_width'], $v['product_height']];
                if ($productId > 0) {
                    \db()->query('UPDATE products SET name=?, description=?, regular_price=?, sale_price=?, weight=?, length=?, width=?, height=?, status="pending", updated_at=NOW() WHERE id=? AND vendor_id=?', [...$params, $productId, $vendor['id']]);
                } else {
                    $unique = 'vendor-' . $vendor['id'] . '-' . bin2hex(random_bytes(6));
                    \db()->query('INSERT INTO products (name,description,regular_price,sale_price,weight,length,width,height,vendor_id,slug,sku,type,status,visibility,currency,stock_status,manage_stock,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,"simple","pending","visible","USD","in_stock",0,NOW(),NOW())', [...$params, $vendor['id'], $unique, $unique]);
                    $productId = (int)\db()->lastInsertId();
                }
                $data['onboarding']['product_id'] = $productId;
                ProductCategoryService::assign($productId, ProductCategoryService::requireCategory($v['product_category_id']), ProductCategoryService::selected($productId));
                ProductCategoryService::inherit($productId);
                \db()->query('UPDATE product_media SET role="gallery" WHERE product_id=? AND role="primary"', [$productId]);
                \db()->query('INSERT INTO product_media (product_id,file_id,role,sort_order,created_at) VALUES (?, ?, "primary", 0, NOW())', [$productId, $data['file_ids']['product_image']]);
                foreach (['business_registration', 'identity'] as $type) {
                    if (!empty($data['file_ids'][$type]) && !\db()->fetch('SELECT id FROM vendor_kyc_documents WHERE vendor_id=? AND file_id=?', [$vendor['id'], $data['file_ids'][$type]])) {
                        \db()->query('INSERT INTO vendor_kyc_documents (vendor_id,document_type,file_id,status,submitted_at) VALUES (?,?,?,"pending",NOW())', [$vendor['id'], $type, $data['file_ids'][$type]]);
                    }
                }
                \db()->query('UPDATE vendors SET store_name=?, banner_file_id=?, logo_file_id=COALESCE(?,logo_file_id), status="pending",kyc_status="pending",updated_at=NOW() WHERE id=?', [$v['store_name'], $data['file_ids']['store_banner'], $data['file_ids']['store_profile_image'] ?? null, $vendor['id']]);
            }
            if ($application) {
                \db()->query('UPDATE vendor_applications SET business_name=?, application_data=?, submitted_at=? WHERE id=?', [$data['business']['business_name'], json_encode($data), \sql_now(), $application['id']]);
            } else {
                \db()->query('INSERT INTO vendor_applications (vendor_id,user_id,application_type,business_name,owner_name,product_category,application_data,submitted_at) VALUES (?, ?, "vendor", ?, ?, "Other", ?, NOW())', [$vendor['id'], $user['id'], $data['business']['business_name'], $user['display_name'] ?? '', json_encode($data)]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($paths as $path) @unlink($path);
            throw $e;
        }
        if ($submit && $missing !== []) throw new \RuntimeException('Please complete: ' . implode(', ', array_column($missing, 'label')) . '. Your draft and uploads have been saved.');
        if ($complete) {
            try {
                NotificationService::notifySuperAdmins('Vendor verification submitted', 'Vendor application ready for review', '<p>' . \e($v['store_name']) . ' completed verification.</p>', ['button_url' => \app_url('dashboard/pending-vendors'), 'button_text' => 'Review vendor']);
                NotificationService::enqueueEmail($user['email'], 'Vendor application received', 'Your application is under review', '<p>Thank you for completing your application. We will email you when a decision is made.</p>', ['button_url' => \app_url('vendor/verification'), 'button_text' => 'View status']);
            } catch (\Throwable $e) {
                \error_log('Vendor verification saved; notification enqueue failed: ' . $e->getMessage());
            }
        }
    }
}
