<?php

declare(strict_types=1);

namespace App;

final class VendorDashboardService
{
    private static bool $distributionWorkspace = false;
    private const UPLOAD_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const VENDOR_COMMUNITY_URL = 'https://chat.whatsapp.com/D9ujOCsXzjOFWigipx2BZR?s=sh&p=a&ilr=4&amv=2';

    public static function page(string $active): void
    {
        RoleDashboardService::requireRole('vendor');
        self::ensureVendorSchema();

        $user = AuthService::user() ?? [];
        $vendor = self::vendor($user);
        self::$distributionWorkspace = DistributorService::allowance((int)$vendor['id']) !== null;
        self::redirectUnverifiedVendor($active, $vendor);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::handlePost($active, $vendor, $user);
            header('Location: ' . self::pageUrl($active));
            exit;
        }

        self::render($active, $vendor, $user);
    }

    private static function handlePost(string $active, array $vendor, array $user): void
    {
        validateCsrf();
        $intent = (string)($_POST['intent'] ?? '');

        try {
            match ($intent) {
                'save_product' => self::saveProduct($vendor, $user),
                'update_product' => self::updateProduct($vendor, $user),
                'archive_product' => self::archiveProduct($vendor),
                'delete_product' => self::deleteProduct($vendor),
                'update_order' => self::updateOrder($vendor),
                'send_chat_response' => self::sendChatResponse($vendor),
                'request_payout' => self::requestPayout($vendor),
                'subscribe_plan' => self::subscribePlan($vendor),
                'save_kyc_details' => self::saveKycDetails($vendor),
                'save_store' => self::saveStore($vendor, $user),
                'upload_kyc' => self::uploadKyc($vendor, $user),
                'save_profile' => self::saveProfile($user),
                'change_password' => self::changePassword($user),
                default => flash('error', 'The requested vendor action was not recognized.'),
            };
        } catch (\Throwable $e) {
            if (db()->pdo()->inTransaction()) db()->pdo()->rollBack();
            flash('error', $e->getMessage());
        }
    }

    private static function redirectUnverifiedVendor(string $active, array $vendor): void
    {
        $roles = $_SESSION['roles'] ?? [];
        if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) {
            return;
        }

        $kycStatus = strtolower((string)($vendor['kyc_status'] ?? 'not_started'));
        if ($vendor['status'] === 'active' && in_array($kycStatus, ['approved', 'verified'], true)) {
            return;
        }


        $message = $kycStatus === 'pending'
            ? 'Your vendor verification is under review. Please check the verification page for any remaining items.'
            : 'Please complete vendor verification before using the rest of your dashboard.';
        flash('warning', $message);
        redirect('vendor/verification');
    }

    private static function render(string $active, array $vendor, array $user): void
    {
        $brand = app_branding();
        $verification = self::verification($vendor);
        $title = self::title($active);
        $toasts = consume_toasts();

        echo '<!doctype html><html lang="en"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . e($title . ' | Vendor Workspace | ' . $brand['name']) . '</title>';
        echo '<link rel="stylesheet" href="' . e(asset('css/role-dashboard.css?v=' . self::assetVersion('css'))) . '">';
        echo '</head><body style="--portal-accent:#16a34a">';
        echo '<div class="portal-shell vendor-shell">';
        self::sidebar($active, $brand, $verification);
        echo '<main class="portal-main">';
        self::topbar($title, $user);
        self::toastStack($toasts);
        self::outstandingPaymentPrompt($vendor);
        if ($active === 'dashboard') {
            self::hero($vendor, $verification);
            self::cards(self::metrics((int)$vendor['id']));
        } else {
            self::pageIntro($active, $vendor, $verification);
        }
        self::content($active, $vendor, $verification);
        echo '</main></div>';
        echo '<script src="' . e(asset('js/role-dashboard.js?v=' . self::assetVersion('js'))) . '"></script>';
        echo app_chat_widget_embed();
        echo '</body></html>';
    }

    private static function sidebar(string $active, array $brand, array $verification): void
    {
        echo '<aside class="portal-sidebar">';
        echo '<a class="portal-brand" href="' . e(app_url('vendor')) . '">';
        echo '<img src="' . e((string)$brand['logo']) . '" alt="' . e((string)$brand['name']) . '"><span>' . e((string)$brand['name']) . '</span></a>';
        echo '<div class="portal-chip">' . (self::$distributionWorkspace ? 'Distributor Workspace' : 'Vendor Workspace') . '</div>';
        echo '<nav>';
        foreach (self::nav() as $item) {
            $isActive = $item['key'] === $active;
            echo '<a class="portal-nav-link' . ($isActive ? ' is-active' : '') . '" href="' . e($item['url']) . '">';
            echo '<span class="portal-dot"></span><span>' . e($item['label']) . '</span></a>';
        }
        echo '</nav>';
        echo '<div class="vendor-verify-mini"><strong>' . e((string)$verification['percent']) . '%</strong><span>Verification readiness</span><div><i style="width:' . e((string)$verification['percent']) . '%"></i></div></div>';
        echo '<div class="portal-sidebar-footer">';
        echo '<a class="portal-store-link portal-store-link-secondary" href="' . e(app_url('store')) . '">Back to Store</a>';
        echo '<a class="portal-store-link" href="' . e(app_url('logout')) . '">Sign Out</a>';
        echo '</div></aside>';
    }

    private static function topbar(string $title, array $user): void
    {
        $initial = strtoupper(substr((string)($user['display_name'] ?? 'V'), 0, 1));
        echo '<header class="portal-topbar"><button class="portal-menu" type="button" aria-label="Toggle menu" data-portal-menu><i></i><i></i><i></i></button>';
        echo '<div><p>' . (self::$distributionWorkspace ? 'Distributor Workspace' : 'Vendor Workspace') . '</p><h1>' . e($title) . '</h1></div>';
        echo '<div class="portal-user"><span>' . e((string)($user['display_name'] ?? 'Vendor')) . '</span><strong>' . e($initial) . '</strong></div></header>';
    }

    private static function outstandingPaymentPrompt(array $vendor): void
    {
        $outstanding = VendorSubscriptionService::outstandingPayment((int)($vendor['id'] ?? 0));
        if (!$outstanding) {
            return;
        }

        $provider = in_array((string)($outstanding['provider'] ?? 'stripe'), ['stripe', 'paystack'], true)
            ? (string)$outstanding['provider']
            : 'stripe';
        $amount = strtoupper((string)($outstanding['currency'] ?? 'USD')) . ' ' . number_format((float)($outstanding['price'] ?? 0), 2);
        $interval = (string)($outstanding['billing_interval'] ?? 'month');

        echo '<section class="vendor-alert" style="border-color:#f59e0b;background:#fff7ed">';
        echo '<div><strong>Vendor plan payment pending</strong><p>Your application is saved as pending, but your selected plan <strong>' . e((string)($outstanding['package_name'] ?? 'Vendor plan')) . '</strong> still needs payment of ' . e($amount . ' / ' . $interval) . '.</p></div>';
        echo '<form method="post" style="margin:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
        echo self::csrf() . '<input type="hidden" name="intent" value="subscribe_plan"><input type="hidden" name="package_id" value="' . e((string)($outstanding['package_id'] ?? 0)) . '"><input type="hidden" name="payment_provider" value="' . e($provider) . '">';
        echo '<button class="portal-action" type="submit">Pay now</button><a class="vendor-link-btn" href="' . e(app_url('vendor/plans')) . '">View plan</a></form>';
        echo '</section>';

        echo '<div class="vendor-payment-pop" role="dialog" aria-live="polite" aria-label="Vendor plan payment reminder">';
        echo '<div><strong>Payment needed</strong><span>' . e((string)($outstanding['package_name'] ?? 'Vendor plan')) . ' is awaiting payment.</span></div>';
        echo '<form method="post">';
        echo self::csrf() . '<input type="hidden" name="intent" value="subscribe_plan"><input type="hidden" name="package_id" value="' . e((string)($outstanding['package_id'] ?? 0)) . '"><input type="hidden" name="payment_provider" value="' . e($provider) . '">';
        echo '<button type="submit">Pay ' . e($amount) . '</button></form>';
        echo '<a href="' . e(app_url('vendor/plans')) . '">Details</a></div>';
        echo '<style>.vendor-payment-pop{position:fixed;right:22px;bottom:22px;z-index:80;max-width:360px;display:grid;gap:12px;padding:18px;border:1px solid #fed7aa;border-radius:18px;background:#fff7ed;box-shadow:0 24px 70px rgba(15,23,42,.18);color:#1f2937}.vendor-payment-pop strong{display:block;color:#9a3412;font-size:16px}.vendor-payment-pop span{display:block;margin-top:4px;color:#7c2d12;font-weight:700}.vendor-payment-pop form{margin:0}.vendor-payment-pop button,.vendor-payment-pop a{min-height:42px;border-radius:999px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;font-weight:900;text-decoration:none}.vendor-payment-pop button{border:0;background:#177d51;color:#fff}.vendor-payment-pop a{color:#177d51;border:1px solid #bbf7d0;background:#fff}@media(max-width:720px){.vendor-payment-pop{left:14px;right:14px;bottom:14px;max-width:none}}</style>';
    }

    private static function hero(array $vendor, array $verification): void
    {
        $banner = self::fileUrl((string)($vendor['banner_path'] ?? ''));
        $publicUrl = app_url('vendors/' . rawurlencode((string)$vendor['store_slug']));
        $heroStyle = $banner !== ''
            ? 'background-image:linear-gradient(90deg, rgba(255,255,255,.96), rgba(255,255,255,.84)), url(' . e($banner) . ')'
            : 'background-image:linear-gradient(135deg, rgba(34,197,94,.10), rgba(255,255,255,.94) 46%, rgba(249,115,22,.08))';
        echo '<section class="portal-hero vendor-hero" style="' . $heroStyle . '">';
        echo '<div><span>' . e(self::statusLabel((string)$vendor['status']) . ' store') . '</span><h2>' . e((string)$vendor['store_name']) . '</h2>';
        echo '<p>' . e((string)($vendor['description'] ?: 'Complete your store setup, add products, manage orders, and request payouts from this workspace.')) . '</p></div>';
        echo '<a class="portal-action" href="' . e(app_url('vendor/product-upload')) . '">Add Product</a></section>';

        echo '<section class="vendor-public-link" aria-labelledby="vendor-public-link-title">';
        echo '<div><p id="vendor-public-link-title">Public vendor page</p><strong>Share your shop link</strong><span>This page shows your store details and public products.</span></div>';
        echo '<label><span>Vendor shop URL</span><input type="url" readonly value="' . e($publicUrl) . '" data-copy-source="vendor-public-link"></label>';
        echo '<div class="vendor-public-link__actions"><button type="button" data-copy-target="vendor-public-link">Copy link</button><a href="' . e($publicUrl) . '" target="_blank" rel="noopener">Open page</a></div>';
        echo '</section>';

        $quota = VendorSubscriptionService::quota((int)$vendor['id']);
        $subscription = $quota['subscription'] ?? [];
        $limitLabel = $quota['limit'] === null ? 'Unlimited' : number_format((int)$quota['limit']);
        echo '<section class="vendor-alert"><div><strong>Package: ' . e((string)($subscription['package_name'] ?? 'Free')) . '</strong><p>';
        echo e(number_format((int)$quota['used']) . ' of ' . $limitLabel . ' published product slots used. ' . (self::$distributionWorkspace ? 'Contact our team to increase your distributor allowance.' : 'Upgrade to a paid subscription to publish up to 10 or more products.'));
        echo '</p></div><a href="' . e(app_url(self::$distributionWorkspace ? 'contact' : 'vendor/plans')) . '">' . (self::$distributionWorkspace ? 'Contact team' : 'View / upgrade plan') . '</a></section>';

        echo '<section class="vendor-alert"><div><strong>Manage My Store for $1</strong><p>Let the Seller Africa team help upload products, improve descriptions, organize listings, and keep your store ready for buyers.</p></div>';
        echo '<a href="' . e(app_url('manage-store')) . '">Manage my store</a></section>';

        if (!$verification['complete']) {
            echo '<section class="vendor-alert"><div><strong>Verification needed</strong><p>Upload your store banner, profile photo, and at least one product with a clear image, full description, weight, dimensions, and price.</p></div>';
            echo '<a href="' . e(app_url('vendor/kyc')) . '">Complete setup</a></section>';
        }
        if (empty($vendor['logo_file_id']) || empty($vendor['banner_file_id'])) {
            echo '<section class="vendor-alert"><div><strong>Logo and banner needed</strong><p>Add your store logo/profile photo and banner so shoppers and the review team can identify your brand clearly.</p></div>';
            echo '<a href="' . e(app_url('vendor/settings')) . '">Upload logo/banner</a></section>';
        }
    }

    private static function cards(array $cards): void
    {
        echo '<section class="portal-cards">';
        foreach ($cards as $card) {
            echo '<article class="portal-card tone-' . e((string)$card['tone']) . '">';
            echo '<p>' . e((string)$card['label']) . '</p><strong>' . e((string)$card['value']) . '</strong><span>' . e((string)$card['hint']) . '</span></article>';
        }
        echo '</section>';
    }

    private static function pageIntro(string $active, array $vendor, array $verification): void
    {
        $meta = self::pageMeta($active);
        $publicUrl = app_url('vendors/' . rawurlencode((string)$vendor['store_slug']));

        echo '<section class="vendor-page-intro tone-' . e((string)$meta['tone']) . '">';
        echo '<div><span>' . e((string)$meta['eyebrow']) . '</span><h2>' . e((string)$meta['title']) . '</h2><p>' . e((string)$meta['description']) . '</p></div>';
        echo '<div class="vendor-page-intro__side">';
        echo '<strong>' . e((string)$verification['percent']) . '%</strong><span>Verification readiness</span>';
        echo '<div><i style="width:' . e((string)$verification['percent']) . '%"></i></div>';
        echo '</div>';
        echo '</section>';

        echo '<section class="vendor-page-tabs" aria-label="Vendor page shortcuts">';
        foreach (self::nav() as $item) {
            $isActive = $item['key'] === $active;
            echo '<a class="' . ($isActive ? 'is-active' : '') . '" href="' . e((string)$item['url']) . '">' . e((string)$item['label']) . '</a>';
        }
        echo '</section>';

        if ($active !== 'settings') {
            echo '<section class="vendor-public-link vendor-page-link-strip">';
            echo '<div><p>Public store</p><strong>' . e((string)$vendor['store_name']) . '</strong><span>Preview your shopper-facing page while you work.</span></div>';
            echo '<label><span>Store URL</span><input type="url" readonly value="' . e($publicUrl) . '" data-copy-source="vendor-page-public-link"></label>';
            echo '<div class="vendor-public-link__actions"><button type="button" data-copy-target="vendor-page-public-link">Copy link</button><a href="' . e($publicUrl) . '" target="_blank" rel="noopener">Open page</a></div>';
            echo '</section>';
        }
    }

    private static function content(string $active, array $vendor, array $verification): void
    {
        match ($active) {
            'products' => self::productsPage($vendor),
            'inventory' => self::inventoryPage($vendor),
            'orders' => self::ordersPage($vendor),
            'chats' => self::chatsPage($vendor),
            'tracking' => self::trackingPage($vendor),
            'customers' => self::customersPage($vendor),
            'reviews' => self::reviewsPage($vendor),
            'payouts' => self::payoutsPage($vendor),
            'plans' => self::plansPage($vendor),
            'kyc' => self::kycPage($vendor, $verification),
            'settings' => self::settingsPage($vendor),
            default => self::dashboardPage($vendor, $verification),
        };
    }

    private static function dashboardPage(array $vendor, array $verification): void
    {
        $vendorId = (int)$vendor['id'];
        $analytics = self::dashboardAnalytics($vendorId);
        $health = self::storeHealth($vendor, $verification, $analytics);

        self::quickActions();

        echo '<section class="vendor-alert"><div><strong>Join the vendor community</strong><p>Connect with other Seller Africa vendors on WhatsApp for updates, support, and marketplace opportunities.</p></div>';
        echo '<a href="' . e(self::VENDOR_COMMUNITY_URL) . '" target="_blank" rel="noopener noreferrer">Join community</a></section>';

        echo '<section class="vendor-grid vendor-grid-dashboard">';
        echo '<article class="portal-panel vendor-health-panel"><div class="portal-panel-head"><div><p>Store Health</p><h3>What needs attention</h3></div><strong class="vendor-score">' . e((string)$health['score']) . '%</strong></div>';
        self::checklist($health['checks']);
        echo '</article>';

        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Priority Queue</p><h3>Next actions</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/orders')) . '">Open orders</a></div>';
        self::attentionQueue($analytics);
        echo '</article>';
        echo '</section>';

        echo '<section class="vendor-grid vendor-grid-dashboard vendor-mt">';
        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Sales Analytics</p><h3>Revenue trend</h3></div><span class="vendor-panel-stat">' . e(self::money((float)$analytics['last_30_sales'])) . ' / 30 days</span></div>';
        self::barChart((array)$analytics['revenue_series'], 'money');
        echo '</article>';

        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Order Mix</p><h3>Fulfillment status</h3></div><span class="vendor-panel-stat">' . e(number_format((int)$analytics['orders_total'])) . ' orders</span></div>';
        self::statusBars((array)$analytics['order_statuses']);
        echo '</article>';
        echo '</section>';

        echo '<section class="vendor-grid vendor-grid-dashboard vendor-mt">';
        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Catalog</p><h3>Best selling products</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/product-upload')) . '">Manage</a></div>';
        self::topProducts($vendorId);
        echo '</article>';

        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Market Signals</p><h3>Categories and customers</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/customers')) . '">Customers</a></div>';
        self::marketSignals($vendorId, $analytics);
        echo '</article>';
        echo '</section>';

        echo '<section class="vendor-grid vendor-grid-dashboard vendor-mt">';
        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Recent Activity</p><h3>Latest orders</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/orders')) . '">All orders</a></div>';
        self::compactOrders($vendorId);
        echo '</article>';

        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Finance</p><h3>Wallet and withdrawals</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/payouts')) . '">Payouts</a></div>';
        self::financeSnapshot($analytics);
        echo '</article>';
        echo '</section>';
    }

    private static function productsPage(array $vendor): void
    {
        $catalogPage = max(1, min(100000, (int)($_GET['catalog_page'] ?? 1)));
        $catalogSearch = is_scalar($_GET['catalog_search'] ?? '') ? substr(trim((string)($_GET['catalog_search'] ?? '')),0,120) : '';
        $products = self::products((int)$vendor['id'], 30, ($catalogPage-1)*30, $catalogSearch);
        echo '<section class="portal-panel"><form class="vendor-inline-form" method="get"><label>Search products or SKU <input name="catalog_search" value="' . e($catalogSearch) . '"></label><button>Search</button></form><nav aria-label="Catalog pages">';
        if ($catalogPage>1) echo '<a href="' . e(app_url('vendor/product-upload?'.http_build_query(['catalog_page'=>$catalogPage-1,'catalog_search'=>$catalogSearch]))) . '">Previous</a> ';
        echo 'Page ' . $catalogPage;
        if (count($products)===30) echo ' <a href="' . e(app_url('vendor/product-upload?'.http_build_query(['catalog_page'=>$catalogPage+1,'catalog_search'=>$catalogSearch]))) . '">Next</a>';
        echo '</nav></section>';
        $editProduct = self::editableProduct((int)$vendor['id'], max(0, (int)($_GET['edit_product'] ?? 0)));
        $quota = VendorSubscriptionService::quota((int)$vendor['id']);
        $limitLabel = $quota['limit'] === null ? 'Unlimited' : number_format((int)$quota['limit']);
        echo '<section class="vendor-grid vendor-grid-wide">';
        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>' . ($editProduct ? 'Edit Product' : 'Add Product') . '</p><h3>Product details</h3></div>';
        if ($editProduct) {
            echo '<a class="vendor-link-btn" href="' . e(app_url('vendor/product-upload')) . '">New product</a>';
        }
        echo '</div>';
        echo '<div class="vendor-alert"><div><strong>' . e((string)($quota['subscription']['package_name'] ?? 'Free')) . ' package</strong><p>' . e(number_format((int)$quota['used']) . ' of ' . $limitLabel . ' published product slots used.') . '</p></div><a href="' . e(app_url('vendor/plans')) . '">Upgrade</a></div>';
        if (!$quota['can_upload'] && !$editProduct) {
            echo '<div class="vendor-alert"><div><strong>Product limit reached</strong><p>Subscribe to a higher package before adding another product.</p></div><a href="' . e(app_url('vendor/plans')) . '">View packages</a></div>';
            echo '</article>';
            echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Catalog</p><h3>Your products</h3></div></div>';
            self::productTable($products, true);
            echo '</article></section>';
            return;
        }
        echo '<form class="vendor-form" method="post" enctype="multipart/form-data">';
        echo self::csrf() . '<input type="hidden" name="intent" value="' . ($editProduct ? 'update_product' : 'save_product') . '">';
        if ($editProduct) {
            echo '<input type="hidden" name="product_id" value="' . e((string)$editProduct['id']) . '">';
        }
        self::input('Product name', 'name', (string)($editProduct['name'] ?? ''), true);
        ProductCategoryService::field('category_id', $editProduct ? ProductCategoryService::selected((int)$editProduct['id']) : null, 'vendor-field');
        self::input('SKU', 'sku', (string)($editProduct['sku'] ?? ''), false);
        echo '<div class="vendor-form-row">';
        self::input('Price', 'regular_price', (string)($editProduct['regular_price'] ?? ''), true, 'number', '0.01');
        self::input('Sale price', 'sale_price', (string)($editProduct['sale_price'] ?? ''), false, 'number', '0.01');
        self::input('Stock quantity', 'stock_quantity', (string)($editProduct['stock_quantity'] ?? '0'), false, 'number', '1');
        echo '</div>';
        self::textarea('Short description', 'short_description', (string)($editProduct['short_description'] ?? ''));
        self::textarea('Detailed description', 'description', (string)($editProduct['description'] ?? ''), true);
        echo '<div class="vendor-form-row">';
        self::input('Weight', 'weight', (string)($editProduct['weight'] ?? ''), true, 'number', '0.01');
        self::input('Length', 'length', (string)($editProduct['length'] ?? ''), true, 'number', '0.01');
        self::input('Width', 'width', (string)($editProduct['width'] ?? ''), true, 'number', '0.01');
        self::input('Height', 'height', (string)($editProduct['height'] ?? ''), true, 'number', '0.01');
        echo '</div>';
        echo '<p>Enter packaged weight in <strong>kg</strong> and package dimensions in <strong>cm</strong>. <strong>500 g = 0.5 kg</strong>; <strong>1,000 g = 1 kg</strong>.</p>';
        echo '<label class="vendor-field"><span>Clear background product image</span><input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"' . ($editProduct ? '' : ' required') . '>' . self::imageUploadGuidance('Main Product Image requirements') . '</label>';
        $variationRows = $editProduct ? ProductVariationService::rows((int)$editProduct['id']) : [];
        require APP_ROOT . '/app/views/vendor/product-variations.php';
        echo '<button class="portal-action" type="submit">' . ($editProduct ? 'Update product' : 'Submit product for review') . '</button></form></article>';

        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Catalog</p><h3>Your products</h3></div></div>';
        self::productTable($products, true);
        echo '</article></section>';
    }

    private static function ordersPage(array $vendor): void
    {
        $orders = self::orders((int)$vendor['id'], 50);
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Orders</p><h3>Customer orders and tracking</h3></div></div>';
        echo '<div class="vendor-table-wrap"><table class="vendor-table vendor-orders-table"><thead><tr><th>Order</th><th>Customer</th><th>Products</th><th>Total</th><th>Payment</th><th>Fulfillment</th><th>Tracking</th><th>Action</th></tr></thead><tbody>';
        foreach ($orders as $order) {
            $trackingUrl = self::trackingUrl($order);
            echo '<tr><td data-label="Order"><strong>' . e((string)$order['order_number']) . '</strong><span>' . e(self::date((string)$order['created_at'])) . '</span></td>';
            echo '<td data-label="Customer">' . e((string)($order['customer_name'] ?: 'Guest')) . '<span>' . e((string)$order['customer_email']) . '</span></td>';
            echo '<td data-label="Products">' . self::productLinks((string)($order['product_links'] ?? ''), (int)$order['item_count']) . '</td>';
            echo '<td data-label="Total">' . e(self::money((float)$order['gross_total'], (string)$order['currency'])) . '</td>';
            echo '<td data-label="Payment"><em class="vendor-badge vendor-badge-payment">' . e((string)$order['payment_status']) . '</em></td>';
            echo '<td data-label="Fulfillment"><em class="vendor-badge">' . e((string)$order['status']) . '</em></td>';
            echo '<td data-label="Tracking">' . e((string)($order['tracking_number'] ?: 'Not added'));
            echo '<span>' . e((string)($order['carrier_name'] ?: $order['shipment_status'] ?: '')) . '</span>';
            echo $trackingUrl !== '' ? '<a class="vendor-text-link" href="' . e($trackingUrl) . '" target="_blank" rel="noopener">Open tracking</a>' : '';
            echo '</td>';
            echo '<td data-label="Action"><form class="vendor-inline-form" method="post">' . self::csrf() . '<input type="hidden" name="intent" value="update_order"><input type="hidden" name="split_id" value="' . e((string)$order['id']) . '">';
            echo self::orderStatusSelect((string)$order['status']);
            echo '<input name="tracking_number" placeholder="Tracking no." value="' . e((string)($order['tracking_number'] ?? '')) . '"><input name="tracking_url" placeholder="Tracking URL" value="' . e((string)($order['tracking_url'] ?? '')) . '"><button type="submit">Update</button></form></td></tr>';
        }
        if ($orders === []) {
            echo '<tr><td colspan="8">No vendor orders yet.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function chatsPage(array $vendor): void
    {
        $conversations = ChatService::conversationsForVendor((int)$vendor['id']);
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Inbox</p><h3>Buyer chats</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendors/' . rawurlencode((string)$vendor['store_slug']))) . '" target="_blank" rel="noopener">Open vendor page</a></div>';
        echo '<p class="vendor-section-note">Use quick approved responses to keep marketplace chat secure. Open the full chat to see the complete thread.</p>';
        echo '<div class="vendor-table-wrap"><table class="vendor-table"><thead><tr><th>Buyer</th><th>Product</th><th>Latest message</th><th>Updated</th><th>Respond</th></tr></thead><tbody>';
        foreach ($conversations as $conversation) {
            $buyerName = trim((string)($conversation['display_name'] ?? ''));
            if ($buyerName === '') {
                $buyerName = trim((string)($conversation['first_name'] ?? '') . ' ' . (string)($conversation['last_name'] ?? ''));
            }
            $buyerName = $buyerName !== '' ? $buyerName : 'Buyer';
            $latest = ChatService::messageText($conversation['last_sender'] ?? null, $conversation['last_message_key'] ?? null);
            echo '<tr><td data-label="Buyer"><strong>' . e($buyerName) . '</strong><span>' . e((string)($conversation['email'] ?? '')) . '</span></td>';
            echo '<td data-label="Product">' . e((string)($conversation['product_name'] ?: 'General store chat')) . '</td>';
            echo '<td data-label="Latest"><strong>' . e($latest !== '' ? mb_strimwidth($latest, 0, 92, '...') : 'No messages yet') . '</strong><span>' . e(ucfirst((string)($conversation['last_sender'] ?: 'open'))) . '</span></td>';
            echo '<td data-label="Updated">' . e(self::date((string)$conversation['updated_at'])) . '</td>';
            echo '<td data-label="Respond"><form class="vendor-inline-form" method="post">' . self::csrf() . '<input type="hidden" name="intent" value="send_chat_response"><input type="hidden" name="conversation_id" value="' . e((string)$conversation['id']) . '">';
            echo '<select name="message_key">';
            foreach (ChatService::SELLER_MESSAGES as $key => $label) {
                echo '<option value="' . e($key) . '">' . e($label) . '</option>';
            }
            echo '</select><button type="submit">Send</button><a class="vendor-text-link" href="' . e(app_url('chat?id=' . (int)$conversation['id'])) . '">Full chat</a></form></td></tr>';
        }
        if ($conversations === []) {
            echo '<tr><td colspan="5">No buyer chats yet. Shoppers can start a chat from product and vendor pages.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function inventoryPage(array $vendor): void
    {
        $rows = self::inventoryRows((int)$vendor['id']);
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Inventory</p><h3>Stock levels and movement</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/product-upload')) . '">Edit products</a></div>';
        echo '<div class="vendor-table-wrap"><table class="vendor-table"><thead><tr><th>Product</th><th>SKU</th><th>Available</th><th>Status</th><th>Low stock</th><th>Sold today</th><th>Last movement</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $threshold = (int)($row['low_stock_threshold'] ?? 5);
            echo '<tr><td data-label="Product"><strong>' . e((string)$row['name']) . '</strong><span>' . e((string)$row['updated_at']) . '</span></td>';
            echo '<td data-label="SKU">' . e((string)($row['sku'] ?: 'Not set')) . '</td>';
            echo '<td data-label="Available">' . e(number_format((int)($row['stock_quantity'] ?? 0))) . '</td>';
            echo '<td data-label="Status"><em class="vendor-badge">' . e(str_replace('_', ' ', (string)$row['stock_status'])) . '</em></td>';
            echo '<td data-label="Low stock">' . e(number_format($threshold)) . '</td>';
            echo '<td data-label="Sold today">' . e(number_format((int)$row['sold_today'])) . '</td>';
            echo '<td data-label="Last movement">' . e((string)($row['last_movement_at'] ? self::date((string)$row['last_movement_at']) : 'No movement')) . '</td>';
            echo '<td data-label="Action"><a class="vendor-link-btn" href="' . e(app_url('vendor/product-upload?edit_product=' . (int)$row['id'])) . '">Update stock</a></td></tr>';
        }
        if ($rows === []) {
            echo '<tr><td colspan="8">No inventory records yet. Add products to begin tracking stock.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function trackingPage(array $vendor): void
    {
        $orders = self::orders((int)$vendor['id'], 80);
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Tracking</p><h3>Shipment tracking</h3></div><a class="vendor-link-btn" href="' . e(app_url('vendor/orders')) . '">Update orders</a></div>';
        echo '<div class="vendor-table-wrap"><table class="vendor-table vendor-tracking-table"><thead><tr><th>Order</th><th>Customer</th><th>Fulfillment</th><th>Tracking number</th><th>Tracking URL</th><th>Updated</th></tr></thead><tbody>';
        foreach ($orders as $order) {
            $trackingUrl = self::trackingUrl($order);
            echo '<tr><td data-label="Order"><strong>' . e((string)$order['order_number']) . '</strong><span>' . e(self::money((float)$order['gross_total'], (string)$order['currency'])) . '</span></td>';
            echo '<td data-label="Customer">' . e((string)($order['customer_name'] ?: 'Guest')) . '<span>' . e((string)$order['customer_email']) . '</span></td>';
            echo '<td data-label="Fulfillment"><em class="vendor-badge">' . e((string)($order['shipment_status'] ?: $order['status'])) . '</em></td>';
            echo '<td data-label="Tracking number">' . e((string)($order['tracking_number'] ?: 'Not added')) . '</td>';
            echo '<td data-label="Tracking URL">' . ($trackingUrl !== '' ? '<a class="vendor-text-link" href="' . e($trackingUrl) . '" target="_blank" rel="noopener">Open tracking</a>' : 'Not added') . '</td>';
            echo '<td data-label="Updated">' . e(self::date((string)($order['shipment_updated_at'] ?: $order['created_at']))) . '</td></tr>';
        }
        if ($orders === []) {
            echo '<tr><td colspan="6">No shipment tracking records yet.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function customersPage(array $vendor): void
    {
        $customers = self::customers((int)$vendor['id']);
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Customers</p><h3>Vendor customer list</h3></div></div>';
        echo '<div class="vendor-table-wrap"><table class="vendor-table"><thead><tr><th>Customer</th><th>Email</th><th>Orders</th><th>Total spent</th><th>Last order</th></tr></thead><tbody>';
        foreach ($customers as $customer) {
            echo '<tr><td><strong>' . e((string)($customer['customer_name'] ?: 'Guest customer')) . '</strong></td>';
            echo '<td>' . e((string)$customer['customer_email']) . '</td>';
            echo '<td>' . e((string)$customer['orders_count']) . '</td>';
            echo '<td>' . e(self::money((float)$customer['spent_total'], (string)$customer['currency'])) . '</td>';
            echo '<td>' . e(self::date((string)$customer['last_order_at'])) . '</td></tr>';
        }
        if ($customers === []) {
            echo '<tr><td colspan="5">No vendor customers yet.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }

    private static function reviewsPage(array $vendor): void
    {
        $reviews = self::reviews((int)$vendor['id']);
        $reviewUrl = app_url('vendors/' . rawurlencode((string)$vendor['store_slug']) . '/review');
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Reviews</p><h3>Customer feedback</h3></div><div class="vendor-panel-actions"><a class="vendor-link-btn" href="' . e($reviewUrl) . '" target="_blank" rel="noopener">Open review link</a><a class="vendor-link-btn" href="' . e(app_url('vendor/product-upload')) . '">Products</a></div></div>';
        echo '<div class="vendor-public-link"><div><strong>Shareable review link</strong><span>Send this link to customers so their ratings save under your store products.</span></div><label><span>Review URL</span><input type="url" readonly value="' . e($reviewUrl) . '" data-copy-source="vendor-review-link"></label><div class="vendor-public-link__actions"><button type="button" data-copy-target="vendor-review-link">Copy link</button><a href="' . e($reviewUrl) . '" target="_blank" rel="noopener">Open page</a></div></div>';
        echo '<div class="vendor-table-wrap"><table class="vendor-table"><thead><tr><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Status</th><th>Date</th></tr></thead><tbody>';
        foreach ($reviews as $review) {
            $body = trim((string)($review['body'] ?? ''));
            echo '<tr><td data-label="Product"><strong>' . e((string)$review['product_name']) . '</strong><span>' . e((string)($review['sku'] ?: 'No SKU')) . '</span></td>';
            echo '<td data-label="Customer">' . e((string)($review['customer_name'] ?: 'Customer')) . '</td>';
            echo '<td data-label="Rating">' . e(number_format((float)$review['rating'], 1)) . '/5</td>';
            echo '<td data-label="Review"><strong>' . e((string)($review['title'] ?: 'Untitled review')) . '</strong><span>' . e($body !== '' ? mb_strimwidth($body, 0, 120, '...') : 'No written comment') . '</span></td>';
            echo '<td data-label="Status"><em class="vendor-badge">' . e((string)$review['status']) . '</em></td>';
            echo '<td data-label="Date">' . e(self::date((string)$review['created_at'])) . '</td></tr>';
        }
        if ($reviews === []) {
            echo '<tr><td colspan="6">No product reviews yet.</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }


    private static function payoutsPage(array $vendor): void
    {
        $available = self::availableBalance((int)$vendor['id']);
        $withdrawals = self::withdrawals((int)$vendor['id']);
        echo '<section class="vendor-grid vendor-grid-wide"><article class="portal-panel"><div class="portal-panel-head"><div><p>Payouts</p><h3>Request withdrawal</h3></div></div>';
        echo '<div class="vendor-balance"><span>Available balance</span><strong>' . e(self::money($available)) . '</strong></div>';
        echo '<form class="vendor-form" method="post">' . self::csrf() . '<input type="hidden" name="intent" value="request_payout">';
        self::input('Amount', 'amount', '', true, 'number', '0.01');
        echo '<label class="vendor-field"><span>Method</span><select name="method"><option value="bank_transfer">Bank transfer</option><option value="paypal">PayPal</option><option value="manual">Manual review</option></select></label>';
        self::textarea('Payout note', 'note');
        echo '<button class="portal-action" type="submit">Request payout</button></form></article>';

        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>History</p><h3>Withdrawals</h3></div></div>';
        echo '<div class="portal-table">';
        foreach ($withdrawals as $row) {
            echo '<div class="portal-row"><div><strong>' . e(self::money((float)$row['amount'], (string)$row['currency'])) . '</strong><span>' . e((string)$row['method'] . ' · ' . self::date((string)$row['requested_at'])) . '</span></div><em>' . e((string)$row['status']) . '</em></div>';
        }
        if ($withdrawals === []) {
            echo '<div class="portal-row"><div><strong>No payout requests yet</strong><span>Completed vendor earnings will appear here.</span></div><em>Ready</em></div>';
        }
        echo '</div></article></section>';
    }

    private static function kycPage(array $vendor, array $verification): void
    {
        $documents = self::kycDocumentSteps((int)$vendor['id']);
        $requiredDocuments = array_values(array_filter($documents, static fn (array $doc): bool => (bool)($doc['required'] ?? false)));
        $requiredTotal = max(1, count($requiredDocuments));
        $completedRequired = count(array_filter($requiredDocuments, static fn (array $doc): bool => in_array((string)($doc['status'] ?? ''), ['pending', 'approved'], true)));
        $completedUploads = count(array_filter($documents, static fn (array $doc): bool => in_array((string)($doc['status'] ?? ''), ['pending', 'approved'], true)));
        $documentPercent = (int)round(($completedRequired / $requiredTotal) * 100);
        $isApproved = (string)($vendor['kyc_status'] ?? '') === 'approved';
        $brandingDone = !empty($vendor['logo_file_id']) && !empty($vendor['banner_file_id']);
        $productDone = (bool)($verification['checks'][2]['done'] ?? false) && (bool)($verification['checks'][3]['done'] ?? false);
        $readinessDone = (int)($verification['percent'] ?? 0) >= 100;
        $nextAction = $verification['complete']
            ? ($completedRequired >= count($requiredDocuments) ? 'We will review your submitted documents and store details. Optional supporting files can still be added if requested.' : 'Upload the required documents below.')
            : 'Finish the store basics first, then upload your KYC documents.';
        $statusLabel = $isApproved ? 'Approved' : ($completedRequired > 0 ? 'Under review' : 'Not submitted');
        $flowSteps = [
            ['number' => '1', 'title' => 'Store basics', 'text' => 'Complete your public profile, logo, banner, address, and first product.', 'done' => $readinessDone],
            ['number' => '2', 'title' => 'Prepare documents', 'text' => 'Have your business registration and tax record ready. ID and address proof are optional unless requested.', 'done' => $completedRequired > 0],
            ['number' => '3', 'title' => 'Upload files', 'text' => 'Upload clear PDF, JPG, PNG, or WEBP files. Optional files can support your review.', 'done' => $completedRequired >= count($requiredDocuments)],
            ['number' => '4', 'title' => 'Team review', 'text' => 'We verify the details and may request corrections before approval.', 'done' => $isApproved],
        ];
        $setupCards = [
            [
                'label' => 'Branding',
                'title' => 'Add profile photo and banner',
                'status' => $brandingDone ? 'Done' : 'Needed',
                'done' => $brandingDone,
                'url' => app_url('vendor/settings'),
                'action' => 'Open settings',
            ],
            [
                'label' => 'Catalog',
                'title' => 'Publish one complete product',
                'status' => $productDone ? 'Done' : 'Needed',
                'done' => $productDone,
                'url' => app_url('vendor/product-upload'),
                'action' => 'Add product',
            ],
            [
                'label' => 'Documents',
                'title' => 'Submit required business files',
                'status' => $completedRequired . '/' . count($requiredDocuments),
                'done' => $completedRequired >= count($requiredDocuments),
                'url' => '#kyc-documents',
                'action' => 'Upload files',
            ],
        ];

        echo '<section class="kyc-setup-hero kyc-setup-hero--guided"><div><p>Vendor verification</p><h3>Complete KYC in guided steps</h3><span>' . e($nextAction) . '</span>';
        echo '<div class="kyc-hero-actions"><a href="#kyc-documents">Upload documents</a><a href="' . e(app_url('vendor/settings')) . '">Store settings</a></div></div>';
        echo '<strong><small>Status</small>' . e($statusLabel) . '</strong></section>';

        echo '<section class="kyc-progress-grid">';
        echo '<article><span>Store readiness</span><strong>' . e((string)$verification['percent']) . '%</strong><div><i style="width:' . e((string)$verification['percent']) . '%"></i></div></article>';
        echo '<article><span>Required documents</span><strong>' . e((string)$completedRequired) . '/' . e((string)count($requiredDocuments)) . '</strong><div><i style="width:' . e((string)$documentPercent) . '%"></i></div></article>';
        echo '</section>';

        echo '<section class="kyc-flow-grid" aria-label="Verification steps">';
        foreach ($flowSteps as $step) {
            echo '<article class="' . ((bool)$step['done'] ? 'is-done' : '') . '"><span>' . e((string)$step['number']) . '</span><div><h4>' . e((string)$step['title']) . '</h4><p>' . e((string)$step['text']) . '</p></div></article>';
        }
        echo '</section>';

        echo '<section class="kyc-action-grid">';
        foreach ($setupCards as $card) {
            echo '<a class="kyc-action-card ' . ((bool)$card['done'] ? 'is-done' : '') . '" href="' . e((string)$card['url']) . '">';
            echo '<div><span>' . e((string)$card['label']) . '</span><h4>' . e((string)$card['title']) . '</h4></div>';
            echo '<em>' . e((string)$card['status']) . '</em><strong>' . e((string)$card['action']) . '</strong></a>';
        }
        echo '</section>';

        echo '<section class="vendor-grid vendor-grid-wide vendor-mt"><article class="portal-panel kyc-guidance-panel"><div class="portal-panel-head"><div><p>Readiness checklist</p><h3>Finish these basics</h3></div><strong class="vendor-score">' . e((string)$verification['percent']) . '%</strong></div>';
        self::checklist($verification['checks']);
        echo '<div class="kyc-help-box"><strong>Before you upload</strong><ul><li>Business registration and tax documents are required for standard review.</li><li>Government ID and proof of address are optional unless our team requests them.</li><li>Accepted files: PDF, JPG, PNG, or WEBP.</li></ul></div>';
        echo '</article></section>';

        self::kycDetailsForm($vendor);
        self::vendorPaidServiceOffers();

        echo '<section class="vendor-grid vendor-grid-wide vendor-mt"><article class="portal-panel kyc-documents-panel" id="kyc-documents"><div class="portal-panel-head"><div><p>Documents</p><h3>Upload documents</h3></div><strong class="vendor-score">' . e((string)$completedRequired) . '/' . e((string)count($requiredDocuments)) . ' required</strong></div>';
        echo '<p class="kyc-section-note">Complete the required document steps below. Optional files can be added later if our verification team asks for them.</p>';
        echo '<div class="kyc-step-list">';
        foreach ($documents as $index => $document) {
            $step = $index + 1;
            $status = (string)($document['status'] ?? 'not_uploaded');
            $hasUpload = $status !== 'not_uploaded';
            $required = (bool)($document['required'] ?? false);
            $badge = $hasUpload ? ucwords(str_replace('_', ' ', $status)) : ($required ? 'Required' : 'Optional');
            echo '<article class="kyc-step-card ' . ($hasUpload ? 'is-uploaded' : '') . (!$required ? ' is-optional' : '') . '">';
            echo '<div class="kyc-step-head"><span>' . e((string)$step) . '</span><div><h4>' . e((string)$document['label']) . (!$required ? ' <small>Optional</small>' : '') . '</h4><p>' . e((string)$document['help']) . '</p></div><em>' . e($badge) . '</em></div>';
            if ($hasUpload) {
                echo '<div class="kyc-step-current"><strong>Latest upload</strong><span>' . e((string)($document['number'] ?: 'No document number')) . ' &middot; ' . e(self::date((string)$document['submitted_at'])) . '</span></div>';
            }
            echo '<form class="vendor-form kyc-step-form" method="post" enctype="multipart/form-data">' . self::csrf() . '<input type="hidden" name="intent" value="upload_kyc"><input type="hidden" name="document_type" value="' . e((string)$document['key']) . '">';
            self::input('Document / reference number', 'document_number', (string)($document['number'] ?? ''), $required);
            echo '<label class="vendor-field"><span>Upload file</span><input type="file" name="document_file" accept="image/jpeg,image/png,image/webp,application/pdf" required><small class="vendor-upload-guidance">PDF, JPG, PNG or WEBP. Use a bright, readable file.</small></label>';
            echo '<button class="portal-action" type="submit">' . ($hasUpload ? 'Replace document' : 'Upload document') . '</button></form></article>';
        }
        echo '</div></article></section>';

        echo '<section class="portal-panel vendor-mt kyc-history-panel"><div class="portal-panel-head"><div><p>Submitted</p><h3>KYC review history</h3></div></div>';
        self::kycDocuments((int)$vendor['id']);
        echo '</section>';
    }

    private static function kycDetailsForm(array $vendor): void
    {
        $setup = self::kycSetupDetails((int)$vendor['id']);
        $value = static fn (string $key, string $fallback = ''): string => (string)($setup[$key] ?? $fallback);

        echo '<section class="portal-panel vendor-mt" id="kyc-details">';
        echo '<div class="portal-panel-head"><div><p>Approved vendor setup</p><h3>KYC and store setup details</h3></div><strong class="vendor-score">Required</strong></div>';
        echo '<p class="kyc-section-note">Complete these details after approval so the team can verify your business, storefront, products, compliance, fulfillment, and payout readiness.</p>';
        echo '<form class="vendor-form" method="post">' . self::csrf() . '<input type="hidden" name="intent" value="save_kyc_details">';

        self::formSection('Step 1 - Business verification');
        self::input('Legal Business Name', 'legal_business_name', $value('legal_business_name'), true);
        self::input('Brand / Trading Name', 'brand_trading_name', $value('brand_trading_name', (string)($vendor['store_name'] ?? '')), true);
        self::input('Country of Registration', 'country_registration', $value('country_registration', (string)($vendor['country_of_origin'] ?? '')), true);
        self::input('Business Registration Number', 'business_registration_number', $value('business_registration_number'), true);
        self::input('Year Established', 'year_established', $value('year_established'), false, 'number');
        self::textarea('Registered Business Address', 'registered_business_address', $value('registered_business_address'), true);
        self::input('Business Website', 'business_website', $value('business_website'), false, 'url');
        self::input('Instagram / Social Media', 'social_media', $value('social_media'), false);

        self::formSection('Step 2 - Owner / KYC verification');
        self::input('Owner / Authorized Representative Full Name', 'owner_full_name', $value('owner_full_name'), true);
        self::input('Date of Birth', 'owner_date_of_birth', $value('owner_date_of_birth'), true, 'date');
        self::textarea('Residential Address', 'owner_residential_address', $value('owner_residential_address'), true);
        self::input('Phone / WhatsApp', 'owner_phone_whatsapp', $value('owner_phone_whatsapp', (string)($vendor['store_phone'] ?? '')), true);
        self::select('Government-Issued ID Type', 'government_id_type', ['Passport', 'National ID', 'Driver\'s License'], $value('government_id_type'), false);
        echo '<div class="kyc-help-box"><strong>Document note</strong><ul><li>Government ID and proof of address are optional unless the verification team requests them.</li><li>This private information does not appear publicly on your store.</li></ul></div>';

        self::formSection('Step 3 - Build your store');
        self::input('Store Name', 'store_name_setup', $value('store_name_setup', (string)($vendor['store_name'] ?? '')), true);
        self::textarea('Store Description', 'store_description_setup', $value('store_description_setup', (string)($vendor['description'] ?? '')), true);
        self::input('Business Category', 'business_category_setup', $value('business_category_setup'), true);
        self::input('Country Products Are Made In', 'country_products_made_in', $value('country_products_made_in', (string)($vendor['country_of_origin'] ?? '')), true);
        self::input('Customer Support Email', 'customer_support_email', $value('customer_support_email', (string)($vendor['store_email'] ?? '')), true, 'email');
        self::input('Customer Support WhatsApp', 'customer_support_whatsapp', $value('customer_support_whatsapp', (string)($vendor['store_phone'] ?? '')), false);

        self::formSection('Step 4 - First product setup');
        self::input('Product Name', 'setup_product_name', $value('setup_product_name'), true);
        self::input('Product Category', 'setup_product_category', $value('setup_product_category'), true);
        self::textarea('Product Description', 'setup_product_description', $value('setup_product_description'), true);
        self::input('Selling Price (USD)', 'setup_selling_price_usd', $value('setup_selling_price_usd'), true, 'number', '0.01');
        self::input('Compare-at / Original Price', 'setup_original_price', $value('setup_original_price'), false, 'number', '0.01');
        self::input('SKU', 'setup_sku', $value('setup_sku'), false);
        self::input('Quantity Available', 'setup_quantity_available', $value('setup_quantity_available'), true, 'number');
        self::input('Net Weight', 'setup_net_weight', $value('setup_net_weight'), true);
        self::input('Package Weight', 'setup_package_weight', $value('setup_package_weight'), true);
        self::input('Package Dimensions', 'setup_package_dimensions', $value('setup_package_dimensions'), true);

        self::formSection('Step 5 - Product-specific information');
        self::textarea('Food & Grocery details', 'food_grocery_details', $value('food_grocery_details'), false);
        self::textarea('Fashion details', 'fashion_details', $value('fashion_details'), false);
        self::textarea('Beauty & Cosmetics details', 'beauty_cosmetics_details', $value('beauty_cosmetics_details'), false);
        self::textarea('Regulatory / certification notes', 'regulatory_certification_notes', $value('regulatory_certification_notes'), false);

        self::formSection('Step 6 - Export & compliance');
        self::input('Where do you want your products sold?', 'target_markets', $value('target_markets', 'United States'), true);
        self::select('Have you exported before?', 'exported_before', ['Yes', 'No'], $value('exported_before'), true);
        self::select('Do you have regulatory documentation?', 'has_regulatory_documentation', ['Yes', 'No', 'Not sure what I need'], $value('has_regulatory_documentation'), true);
        self::select('Do your products have English-language labels?', 'english_language_labels', ['Yes', 'No'], $value('english_language_labels'), true);
        self::textarea('What does your packaging show?', 'packaging_information', $value('packaging_information'), true);
        self::select('Do you need compliance assistance?', 'compliance_assistance', ['Yes', 'No'], $value('compliance_assistance'), false);

        self::formSection('Step 7 - Fulfillment');
        self::select('How will you fulfill SAC orders?', 'fulfillment_method_setup', ['SAC U.S. Warehouse', 'SAC Consolidated Shipment', 'Self-Fulfillment', 'I Need Help Choosing'], $value('fulfillment_method_setup'), true);
        self::input('Current Inventory Location', 'current_inventory_location', $value('current_inventory_location'), true);
        self::select('Typical Processing Time', 'typical_processing_time', ['Same day', '1-2 days', '3-5 days', 'Other'], $value('typical_processing_time'), true);

        self::formSection('Step 8 - Payments');
        self::input('Account Holder / Business Name', 'payout_account_holder', $value('payout_account_holder'), true);
        self::select('Preferred Payout Method', 'preferred_payout_method', ['Bank', 'Supported payment method'], $value('preferred_payout_method'), true);
        self::select('Payout Currency', 'payout_currency', ['USD', 'NGN', 'GBP', 'CAD', 'Other'], $value('payout_currency'), false);
        echo '<div class="kyc-help-box"><strong>Payment privacy</strong><ul><li>Collect only details required by the active payout provider.</li><li>Payment information remains private and is not shown on your public store.</li></ul></div>';

        self::formSection('Final store review');
        self::textarea('Final review notes', 'final_review_notes', $value('final_review_notes'), false);
        echo '<button class="portal-action" type="submit">Save KYC setup details</button></form></section>';
    }

    private static function vendorPaidServiceOffers(): void
    {
        $offers = [
            [
                'kicker' => 'MANAGE MY STORE',
                'title' => 'Let us manage your store for you',
                'price' => '$12/year',
                'summary' => 'Do not have time to manage your Seller Africa store? Our team can help keep your store updated and ready for buyers. That is just $1/month, paid annually.',
                'features' => [
                    'Upload your products',
                    'Edit and improve product descriptions',
                    'Update product information',
                    'Organize your product listings',
                    'Keep your storefront looking professional',
                    'Alert you when you receive an order',
                ],
                'cta' => 'Subscribe',
                'url' => app_url('manage-store'),
                'tone' => 'gold',
            ],
            [
                'kicker' => 'RANK YOUR PRODUCTS',
                'title' => 'Get ranked. Get seen. Get discovered.',
                'price' => '$5/month',
                'summary' => 'Your products are listed, but are buyers actually seeing them? Boost visibility and get products ranked on the Seller Africa Marketplace homepage.',
                'features' => [
                    'Homepage ranking',
                    'Increased product visibility',
                    'More buyer exposure',
                    'Stand out from competing listings',
                    'More opportunities to generate sales',
                ],
                'cta' => 'Subscribe today',
                'url' => app_url('visibility-boost'),
                'tone' => 'green',
            ],
        ];

        echo '<section class="vendor-paid-services vendor-mt" aria-label="Paid vendor services">';
        echo '<div class="portal-panel-head"><div><p>After your application</p><h3>Optional services to help your store grow</h3></div></div>';
        echo '<div class="vendor-paid-services-grid">';
        foreach ($offers as $offer) {
            echo '<article class="vendor-paid-service-card is-' . e((string)$offer['tone']) . '">';
            echo '<div><span>' . e((string)$offer['kicker']) . '</span><h4>' . e((string)$offer['title']) . '</h4><strong>' . e((string)$offer['price']) . '</strong><p>' . e((string)$offer['summary']) . '</p></div>';
            echo '<ul>';
            foreach ((array)$offer['features'] as $feature) {
                echo '<li>' . e((string)$feature) . '</li>';
            }
            echo '</ul>';
            echo '<a href="' . e((string)$offer['url']) . '">' . e((string)$offer['cta']) . '</a>';
            echo '</article>';
        }
        echo '</div></section>';
    }

    private static function plansPage(array $vendor): void
    {
        VendorSubscriptionService::ensureSchema();

        $planNotice = isset($_GET['subscribed'])
            ? ['type' => 'success', 'message' => 'Subscription checkout received. Stripe will confirm your package shortly.']
            : (isset($_GET['cancelled'])
                ? ['type' => 'info', 'message' => 'Subscription checkout was cancelled.']
                : (isset($_GET['payment_pending'])
                    ? ['type' => 'info', 'message' => 'Payment is still pending. Your application remains saved, and you can retry payment from this page.']
                    : null));

        $quota = VendorSubscriptionService::quota((int)$vendor['id']);
        $subscription = (array)($quota['subscription'] ?? []);
        $paystackMethod = table_exists('payment_methods') ? db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1") : null;
        $paystackAvailable = $paystackMethod && (int)($paystackMethod['is_active'] ?? 0) === 1 && PaymentService::isPaystackConfigured($paystackMethod);
        $packages = VendorSubscriptionService::packages(true);
        $activePackageId = (int)($subscription['package_id'] ?? 0);
        $limitLabel = $quota['limit'] === null ? 'Unlimited' : number_format((int)$quota['limit']);
        $remainingLabel = $quota['remaining'] === null ? 'Unlimited' : number_format((int)$quota['remaining']);
        $status = ucwords(str_replace('_', ' ', (string)($subscription['status'] ?? 'active')));

        if ($planNotice !== null) {
            echo '<section class="vendor-alert"><div><strong>' . e(ucfirst((string)$planNotice['type'])) . '</strong><p>' . e((string)$planNotice['message']) . '</p></div></section>';
        }

        echo '<section class="vendor-grid vendor-grid-wide">';
        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Current plan</p><h3>' . e((string)($subscription['package_name'] ?? 'Free')) . '</h3></div><strong class="vendor-score">' . e($limitLabel) . '</strong></div>';
        echo '<div class="vendor-checklist">';
        echo '<div class="is-done"><i></i><span>Status: ' . e($status) . '</span></div>';
        echo '<div class="is-done"><i></i><span>Published products used: ' . e(number_format((int)$quota['used'])) . ' of ' . e($limitLabel) . '</span></div>';
        echo '<div class="is-done"><i></i><span>Remaining published slots: ' . e($remainingLabel) . '</span></div>';
        echo '</div></article>';
        echo '<article class="portal-panel"><div class="portal-panel-head"><div><p>Vendor growth</p><h3>Choose the right package</h3></div></div>';
        echo '<p>Upgrade when you need more product slots or a stronger marketplace plan. Paid packages continue through Stripe subscription checkout.</p>';
        echo '<a class="portal-action" href="' . e(app_url('packages')) . '">Open public plans page</a></article>';
        echo '</section>';

        echo '<section class="vendor-grid vendor-grid-wide vendor-mt">';
        foreach ($packages as $package) {
            $packageId = (int)($package['id'] ?? 0);
            $isCurrent = $activePackageId === $packageId;
            $productLimit = $package['product_limit'] === null ? 'Unlimited products' : number_format((int)$package['product_limit']) . ' products';
            $price = (float)($package['price'] ?? 0);
            $currency = (string)($package['currency'] ?? 'USD');
            $interval = (string)($package['billing_interval'] ?? 'month');
            $features = json_decode((string)($package['features'] ?? '[]'), true);
            $features = is_array($features) ? array_values($features) : [];

            echo '<article class="portal-panel">';
            echo '<div class="portal-panel-head"><div><p>' . e($productLimit) . '</p><h3>' . e((string)($package['name'] ?? 'Vendor Plan')) . '</h3></div>';
            echo '<strong class="vendor-score">' . e($price <= 0 ? 'Free' : self::money($price, $currency) . '/' . $interval) . '</strong></div>';
            if (trim((string)($package['description'] ?? '')) !== '') {
                echo '<p>' . e((string)$package['description']) . '</p>';
            }
            echo '<div class="vendor-checklist">';
            echo '<div class="is-done"><i></i><span>' . e($productLimit) . '</span></div>';
            echo '<div class="is-done"><i></i><span>' . e(number_format((float)($package['transaction_fee_percent'] ?? 0), 2)) . '% marketplace transaction fee</span></div>';
            foreach (array_slice($features, 0, 4) as $feature) {
                echo '<div class="is-done"><i></i><span>' . e((string)$feature) . '</span></div>';
            }
            echo '</div>';
            if ($isCurrent) {
                echo '<button class="portal-action" type="button" disabled>Current plan</button>';
            } else {
                echo '<form method="post">';
                echo self::csrf() . '<input type="hidden" name="intent" value="subscribe_plan"><input type="hidden" name="package_id" value="' . e((string)$packageId) . '">';
                if ($price > 0) {
                    echo '<div class="vendor-form-row" style="margin:12px 0">';
                    echo '<label class="vendor-field"><span>Payment method</span><select name="payment_provider"><option value="stripe">Stripe</option>';
                    if ($paystackAvailable) {
                        echo '<option value="paystack">Paystack</option>';
                    }
                    echo '</select></label></div>';
                }
                echo '<button class="portal-action" type="submit">' . e($price <= 0 ? 'Use free plan' : 'Upgrade plan') . '</button>';
                echo '</form>';
            }
            echo '</article>';
        }
        echo '</section>';
    }

    private static function settingsPage(array $vendor): void
    {
        $account = self::accountUser();
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Account</p><h3>Profile and password</h3></div></div>';
        echo '<div class="portal-account-grid">';
        echo '<form class="vendor-form" method="post">' . self::csrf() . '<input type="hidden" name="intent" value="save_profile">';
        echo '<div class="vendor-form-row">';
        self::input('First name', 'first_name', (string)($account['first_name'] ?? ''));
        self::input('Last name', 'last_name', (string)($account['last_name'] ?? ''));
        self::input('Display name', 'display_name', (string)($account['display_name'] ?? ''));
        echo '</div><div class="vendor-form-row">';
        self::input('Username', 'username', (string)($account['username'] ?? ''));
        self::input('Phone', 'phone', (string)($account['phone'] ?? ''));
        echo '<label class="vendor-field"><span>Email</span><input value="' . e((string)($account['email'] ?? '')) . '" disabled></label>';
        echo '</div><button class="portal-action" type="submit">Save profile</button></form>';

        echo '<form class="vendor-form" method="post">' . self::csrf() . '<input type="hidden" name="intent" value="change_password">';
        echo '<div class="vendor-form-row">';
        echo '<label class="vendor-field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>';
        echo '<label class="vendor-field"><span>New password</span><input type="password" name="new_password" required minlength="5" autocomplete="new-password"></label>';
        echo '<label class="vendor-field"><span>Confirm password</span><input type="password" name="new_password_confirmation" required minlength="5" autocomplete="new-password"></label>';
        echo '</div><button class="portal-action" type="submit">Change password</button></form></div></section>';

        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Store Setup</p><h3>Profile, branding and payout details</h3></div></div>';
        echo '<form class="vendor-form" method="post" enctype="multipart/form-data">' . self::csrf() . '<input type="hidden" name="intent" value="save_store">';
        echo '<div class="vendor-media-preview"><div><span>Profile photo/logo</span>' . self::imagePreview((string)($vendor['logo_path'] ?? '')) . '</div><div><span>Store banner</span>' . self::imagePreview((string)($vendor['banner_path'] ?? ''), true) . '</div></div>';
        echo '<div class="vendor-form-row">';
        self::input('Store name', 'store_name', (string)$vendor['store_name'], true);
        self::input('Store phone', 'store_phone', (string)($vendor['store_phone'] ?? ''));
        self::input('Store email', 'store_email', (string)($vendor['store_email'] ?? ''), true, 'email');
        echo '</div>';
        echo '<div class="vendor-form-row">';
        echo '<label class="vendor-field"><span>Origin region</span><select name="origin_region">';
        $originRegion = (string)($vendor['origin_region'] ?? '');
        foreach (['' => 'Choose region', 'West Africa' => 'West Africa', 'East Africa' => 'East Africa', 'Caribbean' => 'Caribbean', 'Other' => 'Other'] as $value => $label) {
            echo '<option value="' . e($value) . '"' . ($originRegion === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select></label>';
        self::input('Country of origin', 'country_of_origin', (string)($vendor['country_of_origin'] ?? ''));
        echo '</div>';
        self::textarea('Store description', 'description', (string)($vendor['description'] ?? ''), true);
        echo '<div class="vendor-form-row"><label class="vendor-field"><span>Profile photo/logo</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp">' . self::imageUploadGuidance('Profile image requirements') . '</label><label class="vendor-field"><span>Store banner</span><input type="file" name="banner" accept="image/jpeg,image/png,image/webp">' . self::imageUploadGuidance('Vendor banner requirements') . '</label></div>';
        echo '<div class="vendor-form-row">';
        self::input('Address line 1', 'address_line1', (string)($vendor['address_line1'] ?? ''));
        self::input('City', 'city', (string)($vendor['city'] ?? ''));
        self::input('State', 'state', (string)($vendor['state'] ?? ''));
        echo '<label class="vendor-field"><span>Country code</span><input name="country_code" maxlength="2" pattern="[A-Za-z]{2}" placeholder="US" value="' . e((string)($vendor['country_code'] ?? '')) . '"></label>';
        echo '</div>';
        $payoutMethod = (string)($vendor['payout_method'] ?? 'bank_transfer');
        echo '<div class="vendor-form-row"><label class="vendor-field"><span>Payout method</span><select name="payout_method">';
        foreach (['bank_transfer' => 'Bank transfer', 'paypal' => 'PayPal', 'manual' => 'Manual'] as $method => $label) {
            echo '<option value="' . e($method) . '"' . ($payoutMethod === $method ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select></label>';
        self::textarea('Payout details', 'payout_details', self::payoutDetailsForForm($vendor['payout_details'] ?? null));
        echo '</div><button class="portal-action" type="submit">Save store settings</button></form></section>';
    }

    private static function saveProduct(array $vendor, array $user): void
    {
        VendorSubscriptionService::assertCanUploadProduct((int)$vendor['id']);
        $categoryId = ProductCategoryService::requireCategory($_POST['category_id'] ?? null);

        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $price = (float)($_POST['regular_price'] ?? 0);
        $weight = (float)($_POST['weight'] ?? 0);
        $length = (float)($_POST['length'] ?? 0);
        $width = (float)($_POST['width'] ?? 0);
        $height = (float)($_POST['height'] ?? 0);

        if ($name === '' || $description === '' || $price <= 0 || $weight <= 0 || $length <= 0 || $width <= 0 || $height <= 0) {
            throw new \RuntimeException('Product name, detailed description, price, weight, and dimensions are required.');
        }

        $slug = self::uniqueSlug('products', 'slug', $name);
        $sku = self::productSku((string)($_POST['sku'] ?? ''), $name);
        $stock = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $variations = ProductVariationService::posted();
        db()->pdo()->beginTransaction();
        db()->query(
            "INSERT INTO products
             (vendor_id, type, sku, name, slug, short_description, description, status, visibility, regular_price, sale_price, currency, tax_status, stock_status, stock_quantity, manage_stock, weight, length, width, height, created_at, updated_at)
             VALUES (?, 'simple', ?, ?, ?, ?, ?, 'pending', 'visible', ?, ?, 'USD', 'taxable', ?, ?, 1, ?, ?, ?, ?, ?, ?)",
            [
                (int)$vendor['id'],
                $sku,
                $name,
                $slug,
                trim((string)($_POST['short_description'] ?? '')),
                $description,
                $price,
                self::nullableFloat($_POST['sale_price'] ?? null),
                $stock > 0 ? 'in_stock' : 'out_of_stock',
                $stock,
                $weight,
                $length,
                $width,
                $height,
                sql_now(),
                sql_now(),
            ]
        );
        $productId = (int)db()->lastInsertId();
        ProductCategoryService::assign($productId, $categoryId);
        $fileId = self::storeUpload('product_image', (int)$user['id'], 'product');
        db()->query('INSERT INTO product_media (product_id, file_id, role, sort_order, created_at) VALUES (?, ?, "primary", 0, ?)', [$productId, $fileId, sql_now()]);
        ProductVariationService::save($productId, (int)$vendor['id'], $variations, static fn (string $field): int => self::storeUpload($field, (int)$user['id'], 'product'));
        ProductCategoryService::inherit($productId);
        db()->pdo()->commit();
        audit('vendor.product.created', 'product', (string)$productId, [], ['vendor_id' => (int)$vendor['id']]);
        NotificationService::notifySuperAdmins(
            'New product submitted',
            'Product awaiting approval',
            '<p>Vendor <strong>' . e((string)$vendor['store_name']) . '</strong> submitted <strong>' . e($name) . '</strong> for review.</p><p>This notification was queued from the vendor product upload workflow.</p>',
            ['button_text' => 'Review Products', 'button_url' => app_url('dashboard/pending-products'), 'metadata' => ['product_id' => $productId, 'vendor_id' => (int)$vendor['id'], 'type' => 'vendor_product_submitted']]
        );
        self::refreshKycReadiness((int)$vendor['id']);
        flash('success', 'Product submitted for admin approval.');
    }

    private static function sendChatResponse(array $vendor): void
    {
        $conversationId = max(0, (int)($_POST['conversation_id'] ?? 0));
        $messageKey = (string)($_POST['message_key'] ?? '');
        if ($conversationId <= 0) {
            throw new \RuntimeException('Choose a valid chat conversation.');
        }

        ChatService::ensureSchema();
        $conversation = db()->fetch(
            'SELECT id FROM chat_conversations WHERE id = ? AND vendor_id = ? LIMIT 1',
            [$conversationId, (int)$vendor['id']]
        );
        if (!$conversation) {
            throw new \RuntimeException('This chat does not belong to your vendor account.');
        }
        if (!ChatService::sendSellerMessage($conversationId, $messageKey)) {
            throw new \RuntimeException(ChatService::SYSTEM_MESSAGES['system.message_not_approved']);
        }

        flash('success', 'Your chat response has been sent.');
    }

    private static function updateProduct(array $vendor, array $user): void
    {
        $productId = max(0, (int)($_POST['product_id'] ?? 0));
        $product = self::editableProduct((int)$vendor['id'], $productId);
        if (!$product) {
            throw new \RuntimeException('Product was not found for this vendor.');
        }

        $categoryId = ProductCategoryService::requireCategory($_POST['category_id'] ?? null);
        $previousCategoryId = ProductCategoryService::selected($productId);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $price = (float)($_POST['regular_price'] ?? 0);
        $stock = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $weight = (float)($_POST['weight'] ?? 0);
        $length = (float)($_POST['length'] ?? 0);
        $width = (float)($_POST['width'] ?? 0);
        $height = (float)($_POST['height'] ?? 0);

        if ($name === '' || $description === '' || $price <= 0 || $weight <= 0 || $length <= 0 || $width <= 0 || $height <= 0) {
            throw new \RuntimeException('Product name, description, price, weight, and dimensions are required.');
        }

        $sku = self::productSku((string)($_POST['sku'] ?? ''), $name, $productId);
        $variations = ProductVariationService::posted();
        db()->pdo()->beginTransaction();
        db()->query(
            "UPDATE products
             SET name = ?, sku = ?, short_description = ?, description = ?, status = 'pending',
                 regular_price = ?, sale_price = ?, stock_status = ?, stock_quantity = ?,
                 weight = ?, length = ?, width = ?, height = ?, updated_at = ?
             WHERE id = ? AND vendor_id = ?",
            [
                $name,
                $sku,
                trim((string)($_POST['short_description'] ?? '')),
                $description,
                $price,
                self::nullableFloat($_POST['sale_price'] ?? null),
                $stock > 0 ? 'in_stock' : 'out_of_stock',
                $stock,
                $weight,
                $length,
                $width,
                $height,
                sql_now(),
                $productId,
                (int)$vendor['id'],
            ]
        );

        ProductCategoryService::assign($productId, $categoryId, $previousCategoryId);
        if (is_array($_FILES['product_image'] ?? null) && (int)($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fileId = self::storeUpload('product_image', (int)$user['id'], 'product');
            db()->query('UPDATE product_media SET role = "gallery" WHERE product_id = ? AND role = "primary"', [$productId]);
            db()->query('INSERT INTO product_media (product_id, file_id, role, sort_order, created_at) VALUES (?, ?, "primary", 0, ?)', [$productId, $fileId, sql_now()]);
        }

        ProductVariationService::save($productId, (int)$vendor['id'], $variations, static fn (string $field): int => self::storeUpload($field, (int)$user['id'], 'product'));
        ProductCategoryService::inherit($productId);
        db()->pdo()->commit();
        audit('vendor.product.updated', 'product', (string)$productId, [], ['vendor_id' => (int)$vendor['id']]);
        self::refreshKycReadiness((int)$vendor['id']);
        flash('success', 'Product updated and sent for review.');
    }

    private static function archiveProduct(array $vendor): void
    {
        $productId = max(0, (int)($_POST['product_id'] ?? 0));
        $product = self::editableProduct((int)$vendor['id'], $productId);
        if (!$product) {
            throw new \RuntimeException('Product was not found for this vendor.');
        }

        db()->query(
            'UPDATE products SET status = "archived", visibility = "hidden", updated_at = ? WHERE id = ? AND vendor_id = ?',
            [sql_now(), $productId, (int)$vendor['id']]
        );

        audit('vendor.product.archived', 'product', (string)$productId, $product, ['vendor_id' => (int)$vendor['id'], 'status' => 'archived']);
        self::refreshKycReadiness((int)$vendor['id']);
        flash('success', 'Product archived.');
    }

    private static function deleteProduct(array $vendor): void
    {
        $productId = max(0, (int)($_POST['product_id'] ?? 0));
        $product = self::editableProduct((int)$vendor['id'], $productId);
        if (!$product) {
            throw new \RuntimeException('Product was not found for this vendor.');
        }

        $orderItems = (int)(db()->fetch('SELECT COUNT(*) total FROM order_items WHERE product_id = ? OR product_id IN (SELECT id FROM products WHERE parent_product_id=?)', [$productId,$productId])['total'] ?? 0);
        if ($orderItems > 0) {
            db()->query(
                'UPDATE products SET status = "archived", visibility = "hidden", updated_at = ? WHERE id = ? AND vendor_id = ?',
                [sql_now(), $productId, (int)$vendor['id']]
            );
            audit('vendor.product.archived_instead_of_deleted', 'product', (string)$productId, $product, ['vendor_id' => (int)$vendor['id'], 'order_items' => $orderItems]);
            flash('warning', 'Product has order history, so it was archived instead of deleted.');
            return;
        }

        db()->query('DELETE FROM products WHERE id = ? AND vendor_id = ?', [$productId, (int)$vendor['id']]);

        audit('vendor.product.deleted', 'product', (string)$productId, $product, ['vendor_id' => (int)$vendor['id']]);
        self::refreshKycReadiness((int)$vendor['id']);
        flash('success', 'Product deleted.');
    }

    private static function updateOrder(array $vendor): void
    {
        $splitId = (int)($_POST['split_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'processing');
        if (!in_array($status, ['processing', 'shipped', 'completed', 'cancelled'], true)) {
            throw new \RuntimeException('Invalid order status.');
        }

        $split = db()->fetch(
            "SELECT ovs.*
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.id = ? AND ovs.vendor_id = ? AND o.payment_status = 'paid'
             LIMIT 1",
            [$splitId, (int)$vendor['id']]
        );
        if (!$split) {
            throw new \RuntimeException('Order not found for this vendor.');
        }

        db()->beginTransaction();
        try {
            db()->query('UPDATE order_vendor_splits SET status = ?, updated_at = ? WHERE id = ?', [$status, sql_now(), $splitId]);
            if ($status === 'cancelled' && (string)$split['status'] !== 'cancelled') {
                InventoryService::restoreVendorSplit($splitId, (int)($vendor['user_id'] ?? ($_SESSION['user_id'] ?? 0)) ?: null);
            }
            db()->commit();
        } catch (\Throwable $e) {
            if (db()->pdo()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
        $trackingNumber = trim((string)($_POST['tracking_number'] ?? ''));
        $trackingUrl = trim((string)($_POST['tracking_url'] ?? ''));
        if ($trackingNumber !== '' || $trackingUrl !== '') {
            $shipment = db()->fetch('SELECT id FROM shipments WHERE order_id = ? AND vendor_id = ? LIMIT 1', [(int)$split['order_id'], (int)$vendor['id']]);
            if ($shipment) {
                db()->query('UPDATE shipments SET tracking_number = ?, tracking_url = ?, status = ?, updated_at = ? WHERE id = ?', [$trackingNumber, $trackingUrl, $status === 'completed' ? 'delivered' : 'in_transit', sql_now(), (int)$shipment['id']]);
            } else {
                db()->query('INSERT INTO shipments (order_id, vendor_id, tracking_number, tracking_url, status, shipped_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [(int)$split['order_id'], (int)$vendor['id'], $trackingNumber, $trackingUrl, $status === 'completed' ? 'delivered' : 'in_transit', sql_now(), sql_now(), sql_now()]);
            }
        }

        $order = db()->fetch(
            "SELECT o.order_number, COALESCE(u.email, o.guest_email) AS email, COALESCE(NULLIF(u.display_name, ''), o.guest_email) AS name
             FROM orders o
             LEFT JOIN users u ON u.id = o.customer_id
             WHERE o.id = ?
             LIMIT 1",
            [(int)$split['order_id']]
        );
        if ($order) {
            NotificationService::orderUpdated(
                $order,
                'Vendor order update',
                [
                    'Vendor fulfillment status' => ucwords(str_replace('_', ' ', $status)),
                    'Previous vendor status' => ucwords(str_replace('_', ' ', (string)$split['status'])),
                ],
                $trackingNumber,
                $trackingUrl
            );
        }

        audit('vendor.order.updated', 'order_vendor_split', (string)$splitId, ['status' => $split['status']], ['status' => $status]);
        flash('success', 'Order status updated.');
    }

    private static function requestPayout(array $vendor): void
    {
        $amount = (float)($_POST['amount'] ?? 0);
        $available = self::availableBalance((int)$vendor['id']);
        if ($amount <= 0 || $amount > $available) {
            throw new \RuntimeException('Enter a valid payout amount within your available balance.');
        }

        db()->query(
            'INSERT INTO vendor_withdrawals (vendor_id, amount, currency, method, status, note, requested_at) VALUES (?, ?, "USD", ?, "pending", ?, ?)',
            [(int)$vendor['id'], $amount, (string)($_POST['method'] ?? 'bank_transfer'), trim((string)($_POST['note'] ?? '')), sql_now()]
        );
        audit('vendor.payout.requested', 'vendor', (string)$vendor['id'], [], ['amount' => $amount]);
        flash('success', 'Payout request submitted for review.');
    }

    private static function subscribePlan(array $vendor): void
    {
        $packageId = max(0, (int)($_POST['package_id'] ?? 0));
        $provider = in_array((string)($_POST['payment_provider'] ?? 'stripe'), ['stripe', 'paystack'], true)
            ? (string)$_POST['payment_provider']
            : 'stripe';
        $checkoutUrl = VendorSubscriptionService::createCheckoutSession(
            (int)$vendor['id'],
            $packageId,
            app_url('vendor/plans?subscribed=1'),
            app_url('vendor/plans?cancelled=1'),
            $provider
        );

        redirect($checkoutUrl);
    }

    private static function saveStore(array $vendor, array $user): void
    {
        $name = trim((string)($_POST['store_name'] ?? ''));
        $email = trim((string)($_POST['store_email'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        if ($name === '' || $email === '' || $description === '') {
            throw new \RuntimeException('Store name, email, and description are required.');
        }

        $logoId = self::optionalUpload('logo', (int)$user['id'], 'store');
        $bannerId = self::optionalUpload('banner', (int)$user['id'], 'store');
        $addressId = self::saveAddress($vendor);

        $payoutMethod = (string)($_POST['payout_method'] ?? 'bank_transfer');
        $payoutDetails = self::normalizePayoutDetails($payoutMethod, trim((string)($_POST['payout_details'] ?? '')));

        $sets = ['store_name = ?', 'store_email = ?', 'store_phone = ?', 'description = ?', 'payout_method = ?', 'payout_details = ?', 'address_id = ?', 'updated_at = ?'];
        $params = [$name, $email, trim((string)($_POST['store_phone'] ?? '')), $description, $payoutMethod, $payoutDetails, $addressId, sql_now()];
        if (self::columnExists('vendors', 'origin_region')) {
            $sets[] = 'origin_region = ?';
            $originRegion = (string)($_POST['origin_region'] ?? '');
            $params[] = in_array($originRegion, ['West Africa', 'East Africa', 'Caribbean', 'Other'], true) ? $originRegion : null;
        }
        if (self::columnExists('vendors', 'country_of_origin')) {
            $sets[] = 'country_of_origin = ?';
            $params[] = trim((string)($_POST['country_of_origin'] ?? '')) ?: null;
        }
        if ($logoId !== null) {
            $sets[] = 'logo_file_id = ?';
            $params[] = $logoId;
        }
        if ($bannerId !== null) {
            $sets[] = 'banner_file_id = ?';
            $params[] = $bannerId;
        }
        $params[] = (int)$vendor['id'];
        db()->query('UPDATE vendors SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

        audit('vendor.store.updated', 'vendor', (string)$vendor['id']);
        self::refreshKycReadiness((int)$vendor['id']);
        flash('success', 'Store settings saved.');
    }

    private static function accountUser(): array
    {
        try {
            $row = db()->fetch(
                'SELECT id, email, username, first_name, last_name, display_name, phone, password_hash FROM users WHERE id = ? LIMIT 1',
                [(int)($_SESSION['user_id'] ?? 0)]
            );
            return $row ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function saveProfile(array $user): void
    {
        $userId = (int)($user['id'] ?? ($_SESSION['user_id'] ?? 0));
        $username = trim((string)($_POST['username'] ?? ''));
        if ($username !== '') {
            $duplicate = db()->fetch('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1', [$username, $userId]);
            if ($duplicate) {
                throw new \RuntimeException('Another account already uses that username.');
            }
        }

        db()->query(
            'UPDATE users SET username = ?, first_name = ?, last_name = ?, display_name = ?, phone = ?, updated_at = ? WHERE id = ?',
            [
                $username !== '' ? $username : null,
                trim((string)($_POST['first_name'] ?? '')),
                trim((string)($_POST['last_name'] ?? '')),
                trim((string)($_POST['display_name'] ?? '')),
                trim((string)($_POST['phone'] ?? '')),
                sql_now(),
                $userId,
            ]
        );
        $_SESSION['display_name'] = trim((string)($_POST['display_name'] ?? '')) ?: ($_SESSION['display_name'] ?? 'Vendor');
        audit('vendor.profile.updated', 'users', (string)$userId, [], ['vendor_id' => (int)($_POST['vendor_id'] ?? 0)], $userId);
        flash('success', 'Profile saved.');
    }

    private static function changePassword(array $user): void
    {
        $userId = (int)($user['id'] ?? ($_SESSION['user_id'] ?? 0));
        $account = self::accountUser();
        if (!$account || !password_verify((string)($_POST['current_password'] ?? ''), (string)($account['password_hash'] ?? ''))) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $new = (string)($_POST['new_password'] ?? '');
        foreach (SecurityService::passwordErrors($new) as $passwordError) {
            throw new \RuntimeException($passwordError);
        }
        if ($new !== (string)($_POST['new_password_confirmation'] ?? '')) {
            throw new \RuntimeException('Password confirmation does not match.');
        }

        db()->query(
            'UPDATE users SET password_hash = ?, legacy_password_reset_at = COALESCE(legacy_password_reset_at, ?), email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
            [SecurityService::passwordHash($new), sql_now(), sql_now(), sql_now(), $userId]
        );
        audit('vendor.password.changed', 'users', (string)$userId, [], [], $userId);
        flash('success', 'Password changed.');
    }

    private static function saveKycDetails(array $vendor): void
    {
        $keys = [
            'legal_business_name', 'brand_trading_name', 'country_registration', 'business_registration_number',
            'year_established', 'registered_business_address', 'business_website', 'social_media',
            'owner_full_name', 'owner_date_of_birth', 'owner_residential_address', 'owner_phone_whatsapp', 'government_id_type',
            'store_name_setup', 'store_description_setup', 'business_category_setup', 'country_products_made_in',
            'customer_support_email', 'customer_support_whatsapp',
            'setup_product_name', 'setup_product_category', 'setup_product_description', 'setup_selling_price_usd',
            'setup_original_price', 'setup_sku', 'setup_quantity_available', 'setup_net_weight', 'setup_package_weight',
            'setup_package_dimensions', 'food_grocery_details', 'fashion_details', 'beauty_cosmetics_details',
            'regulatory_certification_notes', 'target_markets', 'exported_before', 'has_regulatory_documentation',
            'english_language_labels', 'packaging_information', 'compliance_assistance', 'fulfillment_method_setup',
            'current_inventory_location', 'typical_processing_time', 'payout_account_holder', 'preferred_payout_method',
            'payout_currency', 'final_review_notes',
        ];
        $required = [
            'legal_business_name' => 'Legal Business Name',
            'brand_trading_name' => 'Brand / Trading Name',
            'country_registration' => 'Country of Registration',
            'business_registration_number' => 'Business Registration Number',
            'registered_business_address' => 'Registered Business Address',
            'owner_full_name' => 'Owner / Authorized Representative Full Name',
            'owner_date_of_birth' => 'Date of Birth',
            'owner_residential_address' => 'Residential Address',
            'owner_phone_whatsapp' => 'Phone / WhatsApp',
            'store_name_setup' => 'Store Name',
            'store_description_setup' => 'Store Description',
            'business_category_setup' => 'Business Category',
            'country_products_made_in' => 'Country Products Are Made In',
            'customer_support_email' => 'Customer Support Email',
            'setup_product_name' => 'Product Name',
            'setup_product_category' => 'Product Category',
            'setup_product_description' => 'Product Description',
            'setup_selling_price_usd' => 'Selling Price',
            'setup_quantity_available' => 'Quantity Available',
            'setup_net_weight' => 'Net Weight',
            'setup_package_weight' => 'Package Weight',
            'setup_package_dimensions' => 'Package Dimensions',
            'target_markets' => 'Target Markets',
            'exported_before' => 'Export History',
            'has_regulatory_documentation' => 'Regulatory Documentation',
            'english_language_labels' => 'English-language Labels',
            'packaging_information' => 'Packaging Information',
            'fulfillment_method_setup' => 'Fulfillment Method',
            'current_inventory_location' => 'Current Inventory Location',
            'typical_processing_time' => 'Typical Processing Time',
            'payout_account_holder' => 'Account Holder / Business Name',
            'preferred_payout_method' => 'Preferred Payout Method',
        ];

        $details = [];
        foreach ($keys as $key) {
            $details[$key] = trim((string)($_POST[$key] ?? ''));
        }
        foreach ($required as $key => $label) {
            if (($details[$key] ?? '') === '') {
                throw new \RuntimeException($label . ' is required.');
            }
        }
        if (($details['customer_support_email'] ?? '') !== '' && !filter_var($details['customer_support_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Customer Support Email must be valid.');
        }
        if ((float)($details['setup_selling_price_usd'] ?? 0) <= 0) {
            throw new \RuntimeException('Selling Price must be greater than zero.');
        }
        if ((int)($details['setup_quantity_available'] ?? 0) < 0) {
            throw new \RuntimeException('Quantity Available cannot be negative.');
        }

        $application = self::latestVendorApplication((int)$vendor['id']);
        $payload = [];
        if ($application && trim((string)($application['application_data'] ?? '')) !== '') {
            $decoded = json_decode((string)$application['application_data'], true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        $payload['approved_vendor_setup'] = $details;
        $payload['approved_vendor_setup_saved_at'] = sql_now();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($application) {
            db()->query(
                'UPDATE vendor_applications SET business_name = ?, owner_name = ?, product_category = ?, application_data = ? WHERE id = ?',
                [
                    $details['brand_trading_name'] !== '' ? $details['brand_trading_name'] : (string)($vendor['store_name'] ?? ''),
                    $details['owner_full_name'],
                    $details['business_category_setup'] !== '' ? $details['business_category_setup'] : $details['setup_product_category'],
                    $json,
                    (int)$application['id'],
                ]
            );
        } else {
            db()->query(
                'INSERT INTO vendor_applications (vendor_id, user_id, application_type, business_name, owner_name, product_category, application_data, submitted_at) VALUES (?, ?, "vendor", ?, ?, ?, ?, ?)',
                [
                    (int)$vendor['id'],
                    (int)$vendor['user_id'],
                    $details['brand_trading_name'] !== '' ? $details['brand_trading_name'] : (string)($vendor['store_name'] ?? ''),
                    $details['owner_full_name'],
                    $details['business_category_setup'] !== '' ? $details['business_category_setup'] : $details['setup_product_category'],
                    $json,
                    sql_now(),
                ]
            );
        }

        db()->query('UPDATE vendors SET kyc_status = IF(kyc_status = "approved", kyc_status, "pending"), updated_at = ? WHERE id = ?', [sql_now(), (int)$vendor['id']]);
        audit('vendor.kyc.details_saved', 'vendor', (string)$vendor['id']);
        flash('success', 'KYC setup details saved for review.');
    }

    private static function uploadKyc(array $vendor, array $user): void
    {
        $fileId = self::storeUpload('document_file', (int)$user['id'], 'kyc', ['application/pdf', ...self::UPLOAD_TYPES]);
        db()->query(
            'INSERT INTO vendor_kyc_documents (vendor_id, document_type, document_number, file_id, status, submitted_at) VALUES (?, ?, ?, ?, "pending", ?)',
            [(int)$vendor['id'], (string)($_POST['document_type'] ?? 'business_registration'), trim((string)($_POST['document_number'] ?? '')), $fileId, sql_now()]
        );
        db()->query('UPDATE vendors SET kyc_status = IF(kyc_status = "approved", kyc_status, "pending"), updated_at = ? WHERE id = ?', [sql_now(), (int)$vendor['id']]);
        audit('vendor.kyc.uploaded', 'vendor', (string)$vendor['id']);
        flash('success', 'KYC document uploaded for review.');
    }

    private static function vendor(array $user): array
    {
        $userId = (int)($user['id'] ?? ($_SESSION['user_id'] ?? 0));
        $vendor = db()->fetch(
            "SELECT v.*, lf.path logo_path, bf.path banner_path, a.address_line1, a.city, a.state, a.country_code
             FROM vendors v
             LEFT JOIN files lf ON lf.id = v.logo_file_id
             LEFT JOIN files bf ON bf.id = v.banner_file_id
             LEFT JOIN addresses a ON a.id = v.address_id
             WHERE v.user_id = ?
             LIMIT 1",
            [$userId]
        );

        if ($vendor) {
            return $vendor;
        }

        $storeName = trim((string)($user['display_name'] ?? 'Vendor Store')) ?: 'Vendor Store';
        db()->query(
            "INSERT INTO vendors (user_id, store_name, store_slug, store_email, status, kyc_status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', 'not_started', ?, ?)",
            [$userId, $storeName, self::uniqueSlug('vendors', 'store_slug', $storeName), (string)($user['email'] ?? ''), sql_now(), sql_now()]
        );

        return self::vendor($user);
    }

    private static function verification(array $vendor): array
    {
        if (DistributorService::allowance((int)$vendor['id']) !== null) {
            return ['checks'=>[['label'=>'Distribution application approved','done'=>true]],'percent'=>100,'complete'=>true,'status'=>'approved'];
        }
        $productReady = (bool)db()->fetch(
            "SELECT p.id
             FROM products p
             WHERE p.vendor_id = ?
               AND p.regular_price > 0
               AND COALESCE(p.weight, 0) > 0
               AND COALESCE(p.length, 0) > 0
               AND COALESCE(p.width, 0) > 0
               AND COALESCE(p.height, 0) > 0
               AND TRIM(COALESCE(p.description, '')) <> ''
               AND EXISTS (SELECT 1 FROM product_media pm WHERE pm.product_id = p.id AND pm.role IN ('primary', 'gallery'))
             LIMIT 1",
            [(int)$vendor['id']]
        );
        $application = db()->fetch(
            "SELECT application_data
             FROM vendor_applications
             WHERE vendor_id = ?
             ORDER BY submitted_at DESC, id DESC
             LIMIT 1",
            [(int)$vendor['id']]
        );
        $applicationData = json_decode((string)($application['application_data'] ?? ''), true);
        $applicationData = is_array($applicationData) ? $applicationData : [];
        $readiness = is_array($applicationData['readiness'] ?? null) ? $applicationData['readiness'] : [];
        $fileIds = is_array($applicationData['file_ids'] ?? null) ? $applicationData['file_ids'] : [];
        $applicationProductImageReady = (int)($fileIds['product_image'] ?? 0) > 0;
        $applicationProductDetailsReady = trim((string)($readiness['product_description'] ?? '')) !== ''
            && (float)($readiness['product_price'] ?? 0) > 0
            && (float)($readiness['product_weight'] ?? 0) > 0
            && (float)($readiness['package_length'] ?? 0) > 0
            && (float)($readiness['package_width'] ?? 0) > 0
            && (float)($readiness['package_height'] ?? 0) > 0;

        $checks = [
            ['label' => 'Store profile photo/logo uploaded', 'done' => !empty($vendor['logo_file_id'])],
            ['label' => 'Store banner uploaded', 'done' => !empty($vendor['banner_file_id'])],
            ['label' => 'At least one product image is uploaded', 'done' => $productReady || $applicationProductImageReady],
            ['label' => 'Product description, price, weight and package dimensions are provided', 'done' => $productReady || $applicationProductDetailsReady],
        ];
        if (($applicationData['onboarding']['version'] ?? 0) === 2) {
            $checks = VendorOnboardingService::checks($applicationData);
        }
        $done = count(array_filter($checks, static fn (array $check): bool => (bool)$check['done']));
        $complete = $done === count($checks);

        return [
            'checks' => $checks,
            'percent' => (int)round(($done / max(1, count($checks))) * 100),
            'complete' => $complete,
            'status' => (string)($vendor['kyc_status'] ?? 'not_started'),
        ];
    }

    private static function refreshKycReadiness(int $vendorId): void
    {
        $vendor = db()->fetch('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
        if (!$vendor) {
            return;
        }

        $verification = self::verification($vendor);
        if ($verification['complete'] && !in_array((string)$vendor['kyc_status'], ['approved', 'pending'], true)) {
            db()->query('UPDATE vendors SET kyc_status = "pending", updated_at = ? WHERE id = ?', [sql_now(), $vendorId]);
        }
    }

    private static function metrics(int $vendorId): array
    {
        $analytics = self::dashboardAnalytics($vendorId);
        $totalProducts = (int)$analytics['total_products'];
        $totalCustomers = (int)$analytics['total_customers'];
        $returningCustomers = (int)$analytics['returning_customers'];
        $conversion = $analytics['product_views'] > 0
            ? round(((int)$analytics['units_sold'] / max(1, (int)$analytics['product_views'])) * 100, 1) . '%'
            : '0%';

        return [
            ['label' => 'Total Sales', 'value' => self::money((float)$analytics['total_sales']), 'hint' => 'Gross vendor sales', 'tone' => 'green'],
            ['label' => 'Today', 'value' => self::money((float)$analytics['today_sales']), 'hint' => number_format((int)$analytics['today_orders']) . ' orders today', 'tone' => 'blue'],
            ['label' => 'This Month', 'value' => self::money((float)$analytics['month_sales']), 'hint' => 'Month-to-date revenue', 'tone' => 'green'],
            ['label' => 'Available', 'value' => self::money((float)$analytics['available_balance']), 'hint' => 'Ready for withdrawal', 'tone' => 'blue'],
            ['label' => 'Pending Orders', 'value' => number_format((int)$analytics['pending_orders']), 'hint' => 'Needs review', 'tone' => 'orange'],
            ['label' => 'Completed', 'value' => number_format((int)$analytics['completed_orders']), 'hint' => 'Fulfilled vendor orders', 'tone' => 'green'],
            ['label' => 'Cancelled', 'value' => number_format((int)$analytics['cancelled_orders']), 'hint' => 'Cancelled or failed', 'tone' => 'orange'],
            ['label' => 'Pending Payout', 'value' => self::money((float)$analytics['pending_withdrawal']), 'hint' => 'Awaiting approval/payment', 'tone' => 'blue'],
            ['label' => 'Products', 'value' => number_format($totalProducts), 'hint' => number_format((int)$analytics['active_products']) . ' published', 'tone' => 'green'],
            ['label' => 'Stock Alerts', 'value' => number_format((int)$analytics['low_stock_products'] + (int)$analytics['out_stock_products']), 'hint' => number_format((int)$analytics['out_stock_products']) . ' out of stock', 'tone' => 'orange'],
            ['label' => 'Customers', 'value' => number_format($totalCustomers), 'hint' => number_format($returningCustomers) . ' returning customers', 'tone' => 'blue'],
            ['label' => 'Rating', 'value' => number_format((float)$analytics['average_rating'], 1), 'hint' => number_format((int)$analytics['review_count']) . ' reviews · ' . $conversion . ' conversion', 'tone' => 'green'],
        ];
    }

    private static function dashboardAnalytics(int $vendorId): array
    {
        $sales = db()->fetch(
            "SELECT
                COALESCE(SUM(ovs.gross_total), 0) total_sales,
                COALESCE(SUM(CASE WHEN DATE(o.created_at) = CURDATE() THEN ovs.gross_total ELSE 0 END), 0) today_sales,
                COALESCE(SUM(CASE WHEN o.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN ovs.gross_total ELSE 0 END), 0) month_sales,
                COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ovs.gross_total ELSE 0 END), 0) last_30_sales,
                COUNT(*) orders_total,
                COALESCE(SUM(CASE WHEN DATE(o.created_at) = CURDATE() THEN 1 ELSE 0 END), 0) today_orders,
                COALESCE(SUM(CASE WHEN ovs.status IN ('pending','processing') THEN 1 ELSE 0 END), 0) pending_orders,
                COALESCE(SUM(CASE WHEN ovs.status = 'completed' THEN 1 ELSE 0 END), 0) completed_orders,
                COALESCE(SUM(CASE WHEN ovs.status IN ('cancelled','failed') THEN 1 ELSE 0 END), 0) cancelled_orders,
                COALESCE(SUM(CASE WHEN ovs.status = 'refunded' THEN 1 ELSE 0 END), 0) refunded_orders,
                COALESCE(SUM(ovs.platform_commission), 0) platform_fees,
                COALESCE(SUM(ovs.gateway_fee), 0) gateway_fees,
                COALESCE(SUM(ovs.shipping_total), 0) shipping_total,
                COALESCE(SUM(ovs.tax_total), 0) tax_total,
                COALESCE(SUM(ovs.vendor_earning), 0) total_earnings
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ?
               AND o.payment_status = 'paid'",
            [$vendorId]
        ) ?: [];

        $products = db()->fetch(
            "SELECT
                COUNT(*) total_products,
                COALESCE(SUM(status = 'active'), 0) active_products,
                COALESCE(SUM(status = 'pending'), 0) pending_products,
                COALESCE(SUM(status = 'draft'), 0) draft_products,
                COALESCE(SUM(status = 'archived'), 0) archived_products,
                COALESCE(SUM(stock_status = 'out_of_stock' OR COALESCE(stock_quantity, 0) <= 0), 0) out_stock_products,
                COALESCE(SUM(stock_status <> 'out_of_stock' AND COALESCE(stock_quantity, 0) > 0 AND COALESCE(stock_quantity, 0) <= COALESCE(low_stock_threshold, 5)), 0) low_stock_products,
                COALESCE(SUM(total_sales), 0) units_sold,
                COALESCE(SUM(review_count), 0) review_count,
                COALESCE(AVG(NULLIF(average_rating, 0)), 0) average_rating
             FROM products
             WHERE vendor_id = ?",
            [$vendorId]
        ) ?: [];

        $customers = db()->fetch(
            "SELECT COUNT(*) total_customers, COALESCE(SUM(order_count > 1), 0) returning_customers
             FROM (
                SELECT COALESCE(u.email, o.guest_email) customer_key, COUNT(DISTINCT o.id) order_count
                FROM order_vendor_splits ovs
                INNER JOIN orders o ON o.id = ovs.order_id
                LEFT JOIN users u ON u.id = o.customer_id
                WHERE ovs.vendor_id = ?
                  AND o.payment_status = 'paid'
                GROUP BY COALESCE(u.email, o.guest_email)
             ) customer_orders",
            [$vendorId]
        ) ?: [];

        $returns = table_exists('return_requests')
            ? db()->fetch("SELECT COALESCE(SUM(status = 'requested'), 0) pending_returns, COUNT(*) total_returns FROM return_requests WHERE vendor_id = ?", [$vendorId]) ?: []
            : [];

        $withdrawals = db()->fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('pending','approved') THEN amount ELSE 0 END), 0) pending_withdrawal,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) withdrawn_total
             FROM vendor_withdrawals
             WHERE vendor_id = ?",
            [$vendorId]
        ) ?: [];

        $views = 0;
        if (table_exists('product_analytics')) {
            $views = (int)(db()->fetch(
                "SELECT COALESCE(SUM(pa.views), 0) views
                 FROM product_analytics pa
                 INNER JOIN products p ON p.id = pa.product_id
                 WHERE p.vendor_id = ?",
                [$vendorId]
            )['views'] ?? 0);
        }

        $statuses = db()->fetchAll(
            "SELECT status, COUNT(*) total
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ?
               AND o.payment_status = 'paid'
             GROUP BY ovs.status",
            [$vendorId]
        );

        $statusMap = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'completed' => 0, 'cancelled' => 0, 'refunded' => 0];
        foreach ($statuses as $row) {
            $statusMap[(string)$row['status']] = (int)$row['total'];
        }

        return [
            ...$sales,
            ...$products,
            ...$customers,
            ...$returns,
            ...$withdrawals,
            'available_balance' => self::availableBalance($vendorId),
            'product_views' => $views,
            'order_statuses' => $statusMap,
            'revenue_series' => self::revenueSeries($vendorId),
        ];
    }

    private static function revenueSeries(int $vendorId): array
    {
        $rows = db()->fetchAll(
            "SELECT DATE(o.created_at) day, COALESCE(SUM(ovs.gross_total), 0) total
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ?
               AND o.payment_status = 'paid'
               AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(o.created_at)
             ORDER BY day ASC",
            [$vendorId]
        );
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string)$row['day']] = (float)$row['total'];
        }

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $series[] = ['label' => date('M j', strtotime($day)), 'value' => $byDay[$day] ?? 0.0];
        }
        return $series;
    }

    private static function products(int $vendorId, int $limit, int $offset = 0, string $search = ''): array
    {
        $limit=max(1,min(100,$limit)); $offset=max(0,$offset);
        $filter=$search!==''?' AND (p.name LIKE ? OR p.sku LIKE ?)':'';
        $params=$search!==''?[$vendorId,'%'.$search.'%','%'.$search.'%']:[$vendorId];
        return db()->fetchAll(
            "SELECT p.*, f.path image_path
             FROM products p
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2 WHERE pm2.product_id = p.id ORDER BY pm2.role = 'primary' DESC, pm2.sort_order ASC, pm2.id ASC LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE p.vendor_id = ? AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=p.id AND vt.slug='sac-variation-options') {$filter}
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    private static function inventoryRows(int $vendorId): array
    {
        $movementSelect = table_exists('product_inventory_movements')
            ? "(SELECT MAX(pim.created_at) FROM product_inventory_movements pim WHERE pim.product_id = p.id)"
            : "NULL";

        return db()->fetchAll(
            "SELECT p.id, p.name, p.sku, p.stock_quantity, p.stock_status, p.low_stock_threshold, p.updated_at,
                    COALESCE((
                        SELECT SUM(oi.quantity)
                        FROM order_items oi
                        INNER JOIN orders o ON o.id = oi.order_id
                        WHERE oi.product_id = p.id
                          AND oi.vendor_id = p.vendor_id
                          AND oi.item_type = 'product'
                          AND DATE(o.created_at) = CURDATE()
                    ), 0) sold_today,
                    {$movementSelect} last_movement_at
             FROM products p
             WHERE p.vendor_id = ?
             ORDER BY
                (p.stock_status = 'out_of_stock') DESC,
                (COALESCE(p.stock_quantity, 0) <= COALESCE(p.low_stock_threshold, 5)) DESC,
                p.updated_at DESC
             LIMIT 120",
            [$vendorId]
        );
    }

    private static function editableProduct(int $vendorId, int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $row = db()->fetch("SELECT * FROM products WHERE id = ? AND vendor_id = ? AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=products.id AND vt.slug='sac-variation-options') LIMIT 1", [$productId, $vendorId]);
        return $row ?: null;
    }

    private static function orders(int $vendorId, int $limit): array
    {
        return db()->fetchAll(
            "SELECT ovs.*, o.order_number, o.currency, o.payment_status, o.created_at, o.placed_at, u.display_name customer_name,
                    COALESCE(u.email, o.guest_email) customer_email,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_count,
                    (SELECT GROUP_CONCAT(CONCAT(COALESCE(oi.product_id, 0), '::', COALESCE(p.slug, ''), '::', oi.name) ORDER BY oi.id SEPARATOR '||')
                     FROM order_items oi
                     LEFT JOIN products p ON p.id = oi.product_id
                     WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') product_links,
                    s.tracking_number, s.tracking_url, s.status shipment_status, s.updated_at shipment_updated_at,
                    c.name carrier_name, c.tracking_url_template
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             LEFT JOIN users u ON u.id = o.customer_id
             LEFT JOIN shipments s ON s.id = (
                SELECT s2.id FROM shipments s2 WHERE s2.order_id = o.id AND s2.vendor_id = ovs.vendor_id ORDER BY s2.id DESC LIMIT 1
             )
             LEFT JOIN shipping_carriers c ON c.id = s.carrier_id
             WHERE ovs.vendor_id = ?
               AND o.payment_status = 'paid'
             ORDER BY o.created_at DESC, ovs.id DESC
             LIMIT {$limit}",
            [$vendorId]
        );
    }

    private static function customers(int $vendorId): array
    {
        return db()->fetchAll(
            "SELECT COALESCE(u.display_name, 'Guest customer') customer_name,
                    COALESCE(u.email, o.guest_email) customer_email,
                    COALESCE(o.currency, 'USD') currency,
                    COUNT(DISTINCT o.id) orders_count,
                    COALESCE(SUM(ovs.gross_total), 0) spent_total,
                    MAX(o.created_at) last_order_at
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             LEFT JOIN users u ON u.id = o.customer_id
             WHERE ovs.vendor_id = ?
               AND o.payment_status = 'paid'
             GROUP BY COALESCE(u.email, o.guest_email), COALESCE(u.display_name, 'Guest customer'), COALESCE(o.currency, 'USD')
             ORDER BY last_order_at DESC
             LIMIT 100",
            [$vendorId]
        );
    }

    private static function reviews(int $vendorId): array
    {
        return db()->fetchAll(
            "SELECT r.*, p.name product_name, p.sku, COALESCE(NULLIF(u.display_name, ''), u.email) customer_name
             FROM reviews r
             INNER JOIN products p ON p.id = r.product_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE p.vendor_id = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 100",
            [$vendorId]
        );
    }

    private static function compactOrders(int $vendorId): void
    {
        $orders = self::orders($vendorId, 5);
        echo '<div class="portal-table">';
        foreach ($orders as $order) {
            echo '<div class="portal-row"><div><strong>' . e((string)$order['order_number']) . '</strong><span>' . e((string)($order['customer_email'] ?? 'Customer') . ' · ' . self::money((float)$order['gross_total'], (string)$order['currency'])) . '</span></div><em>' . e((string)$order['status']) . '</em></div>';
        }
        if ($orders === []) {
            echo '<div class="portal-row"><div><strong>No orders yet</strong><span>Customer orders will appear here.</span></div><em>Ready</em></div>';
        }
        echo '</div>';
    }

    private static function compactProducts(int $vendorId): void
    {
        self::productTable(self::products($vendorId, 8), false);
    }

    private static function quickActions(): void
    {
        $actions = [
            ['label' => 'Upload Product', 'title' => 'Add or edit products', 'hint' => 'Create product listings, upload images, set stock, pricing, and dimensions.', 'url' => app_url('vendor/product-upload'), 'tone' => 'green'],
            ['label' => 'Verification', 'title' => 'Complete seller verification', 'hint' => 'Upload one valid document and finish store readiness checks.', 'url' => app_url('vendor/kyc'), 'tone' => 'gold'],
            ['label' => 'Orders', 'title' => 'Manage orders', 'hint' => 'Update fulfillment status, tracking links, and shipment progress.', 'url' => app_url('vendor/orders'), 'tone' => 'blue'],
            ['label' => 'Inventory', 'title' => 'Update stock', 'hint' => 'Review low stock items and jump into product stock edits.', 'url' => app_url('vendor/inventory'), 'tone' => 'slate'],
            ['label' => 'Messages', 'title' => 'Reply to buyers', 'hint' => 'Open customer chats and send approved marketplace responses.', 'url' => app_url('vendor/chats'), 'tone' => 'teal'],
            ['label' => 'Plans', 'title' => 'View or upgrade plan', 'hint' => 'See your package, published product slots, remaining uploads, and subscription options.', 'url' => app_url('vendor/plans'), 'tone' => 'green'],
            ['label' => '$1 Store Help', 'title' => 'Manage My Store', 'hint' => 'Subscribe so our team can help upload, edit, organize, and maintain your storefront.', 'url' => app_url('manage-store'), 'tone' => 'gold'],
            ['label' => 'Payouts', 'title' => 'Request withdrawal', 'hint' => 'View your available balance and submit payout requests.', 'url' => app_url('vendor/payouts'), 'tone' => 'purple'],
            ['label' => 'Store Setup', 'title' => 'Edit store profile', 'hint' => 'Update branding, public store details, address, and payout settings.', 'url' => app_url('vendor/settings'), 'tone' => 'orange'],
            ['label' => 'Community', 'title' => 'Join vendor community', 'hint' => 'Get updates, support, and marketplace announcements on WhatsApp.', 'url' => self::VENDOR_COMMUNITY_URL, 'external' => true, 'tone' => 'green'],
        ];

        echo '<section class="vendor-page-hub" aria-labelledby="vendor-page-hub-title">';
        echo '<div class="vendor-page-hub__head"><div><p>Vendor pages</p><h3 id="vendor-page-hub-title">Choose what you want to manage</h3></div><a href="' . e(app_url('vendor/product-upload')) . '">Upload product</a></div>';
        echo '<div class="vendor-page-hub__grid">';
        foreach ($actions as $action) {
            $externalAttributes = !empty($action['external']) ? ' target="_blank" rel="noopener noreferrer"' : '';
            echo '<a class="vendor-page-card tone-' . e((string)$action['tone']) . '" href="' . e((string)$action['url']) . '"' . $externalAttributes . '>';
            echo '<span>' . e((string)$action['label']) . '</span><strong>' . e((string)$action['title']) . '</strong><em>' . e((string)$action['hint']) . '</em><b>Open page</b></a>';
        }
        echo '</div></section>';
    }

    private static function storeHealth(array $vendor, array $verification, array $analytics): array
    {
        $checks = [
            ['label' => 'Store profile and banner are complete', 'done' => !empty($vendor['logo_file_id']) && !empty($vendor['banner_file_id'])],
            ['label' => 'KYC documents are approved or under review', 'done' => in_array((string)($vendor['kyc_status'] ?? ''), ['pending', 'approved'], true)],
            ['label' => 'At least one product is ready with image and dimensions', 'done' => (bool)$verification['complete']],
            ['label' => 'No urgent stock alert backlog', 'done' => ((int)$analytics['low_stock_products'] + (int)$analytics['out_stock_products']) === 0],
            ['label' => 'Orders are moving through fulfillment', 'done' => (int)$analytics['pending_orders'] <= 3],
            ['label' => 'Refund and return queue is under control', 'done' => (int)($analytics['pending_returns'] ?? 0) === 0],
            ['label' => 'Customer rating is healthy', 'done' => (float)$analytics['average_rating'] >= 4 || (int)$analytics['review_count'] === 0],
        ];
        $done = count(array_filter($checks, static fn (array $check): bool => (bool)$check['done']));

        return [
            'score' => (int)round(($done / max(1, count($checks))) * 100),
            'checks' => $checks,
        ];
    }

    private static function attentionQueue(array $analytics): void
    {
        $items = [
            ['label' => 'Pending orders', 'value' => (int)$analytics['pending_orders'], 'hint' => 'Confirm, process, or ship these orders.', 'url' => app_url('vendor/orders')],
            ['label' => 'Low stock products', 'value' => (int)$analytics['low_stock_products'], 'hint' => 'Update stock before best sellers run out.', 'url' => app_url('vendor/inventory')],
            ['label' => 'Out of stock products', 'value' => (int)$analytics['out_stock_products'], 'hint' => 'Restock or archive unavailable items.', 'url' => app_url('vendor/inventory')],
            ['label' => 'Pending returns', 'value' => (int)($analytics['pending_returns'] ?? 0), 'hint' => 'Review customer return/refund requests.', 'url' => app_url('vendor/orders')],
            ['label' => 'Pending withdrawals', 'value' => (float)$analytics['pending_withdrawal'], 'hint' => 'Payouts waiting for approval or payment.', 'url' => app_url('vendor/payouts'), 'money' => true],
        ];

        echo '<div class="portal-table vendor-attention-list">';
        foreach ($items as $item) {
            $value = !empty($item['money']) ? self::money((float)$item['value']) : number_format((int)$item['value']);
            echo '<a class="portal-row" href="' . e((string)$item['url']) . '"><div><strong>' . e((string)$item['label']) . '</strong><span>' . e((string)$item['hint']) . '</span></div><em>' . e($value) . '</em></a>';
        }
        echo '</div>';
    }

    private static function barChart(array $series, string $format = 'number'): void
    {
        $max = max(1.0, ...array_map(static fn (array $row): float => (float)$row['value'], $series));
        echo '<div class="vendor-chart">';
        foreach ($series as $row) {
            $value = (float)$row['value'];
            $height = max(4, (int)round(($value / $max) * 100));
            $label = $format === 'money' ? self::money($value) : number_format((int)$value);
            echo '<div class="vendor-chart-bar"><span style="height:' . e((string)$height) . '%"></span><strong>' . e((string)$row['label']) . '</strong><em>' . e($label) . '</em></div>';
        }
        echo '</div>';
    }

    private static function statusBars(array $statuses): void
    {
        $labels = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];
        $max = max(1, array_sum(array_map('intval', $statuses)));
        echo '<div class="vendor-status-bars">';
        foreach ($labels as $key => $label) {
            $value = (int)($statuses[$key] ?? 0);
            $width = (int)round(($value / $max) * 100);
            echo '<div><span><strong>' . e($label) . '</strong><em>' . e(number_format($value)) . '</em></span><i><b style="width:' . e((string)$width) . '%"></b></i></div>';
        }
        echo '</div>';
    }

    private static function topProducts(int $vendorId): void
    {
        $rows = db()->fetchAll(
            "SELECT p.id, p.name, p.slug, p.currency, p.total_sales,
                    COALESCE(s.units, 0) units,
                    COALESCE(s.revenue, 0) revenue,
                    COALESCE(w.wishlist_count, 0) wishlist_count
             FROM products p
             LEFT JOIN (
                SELECT product_id, COALESCE(SUM(quantity), 0) units, COALESCE(SUM(total), 0) revenue
                FROM order_items
                WHERE vendor_id = ? AND item_type = 'product'
                GROUP BY product_id
             ) s ON s.product_id = p.id
             LEFT JOIN (
                SELECT product_id, COUNT(*) wishlist_count
                FROM wishlist_items
                GROUP BY product_id
             ) w ON w.product_id = p.id
             WHERE p.vendor_id = ?
             ORDER BY revenue DESC, p.total_sales DESC, p.updated_at DESC
             LIMIT 6",
            [$vendorId, $vendorId]
        );

        echo '<div class="vendor-table-wrap"><table class="vendor-table vendor-compact-table"><thead><tr><th>Product</th><th>Units</th><th>Revenue</th><th>Wishlists</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $url = (string)($row['slug'] ?? '') !== '' ? app_url('product/' . rawurlencode((string)$row['slug'])) : '';
            echo '<tr><td data-label="Product"><strong>' . ($url !== '' ? '<a class="vendor-text-link" href="' . e($url) . '" target="_blank" rel="noopener">' . e((string)$row['name']) . '</a>' : e((string)$row['name'])) . '</strong></td>';
            echo '<td data-label="Units">' . e(number_format((float)$row['units'], 0)) . '</td><td data-label="Revenue">' . e(self::money((float)$row['revenue'], (string)$row['currency'])) . '</td><td data-label="Wishlists">' . e(number_format((int)$row['wishlist_count'])) . '</td></tr>';
        }
        if ($rows === []) {
            echo '<tr><td colspan="4">No product performance data yet.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function marketSignals(int $vendorId, array $analytics): void
    {
        $categories = self::topCategories($vendorId);
        $customers = self::customers($vendorId);
        $bestCustomers = array_slice($customers, 0, 3);

        echo '<div class="vendor-signal-grid">';
        echo '<div><h4>Top categories</h4><div class="portal-table">';
        foreach ($categories as $category) {
            echo '<div class="portal-row"><div><strong>' . e((string)$category['name']) . '</strong><span>' . e(number_format((int)$category['units']) . ' units sold') . '</span></div><em>' . e(self::money((float)$category['revenue'])) . '</em></div>';
        }
        if ($categories === []) {
            echo '<div class="portal-row"><div><strong>No category sales yet</strong><span>Sales by product category will appear here.</span></div><em>Ready</em></div>';
        }
        echo '</div></div>';

        echo '<div><h4>Customer snapshot</h4><div class="portal-table">';
        echo '<div class="portal-row"><div><strong>' . e(number_format((int)$analytics['total_customers'])) . ' total customers</strong><span>' . e(number_format((int)$analytics['returning_customers'])) . ' have ordered more than once.</span></div><em>' . e(number_format((float)$analytics['orders_total'], 0)) . '</em></div>';
        foreach ($bestCustomers as $customer) {
            echo '<div class="portal-row"><div><strong>' . e((string)($customer['customer_name'] ?: 'Guest customer')) . '</strong><span>' . e((string)$customer['customer_email']) . '</span></div><em>' . e(self::money((float)$customer['spent_total'], (string)$customer['currency'])) . '</em></div>';
        }
        echo '</div></div></div>';
    }

    private static function topCategories(int $vendorId): array
    {
        if (!table_exists('product_categories') || !table_exists('categories')) {
            return [];
        }

        return db()->fetchAll(
            "SELECT c.name, COALESCE(SUM(oi.quantity), 0) units, COALESCE(SUM(oi.total), 0) revenue
             FROM product_categories pc
             INNER JOIN categories c ON c.id = pc.category_id
             INNER JOIN products p ON p.id = pc.product_id
             LEFT JOIN order_items oi ON oi.product_id = p.id AND oi.vendor_id = p.vendor_id AND oi.item_type = 'product'
             WHERE p.vendor_id = ?
             GROUP BY c.id, c.name
             ORDER BY revenue DESC, units DESC, c.name ASC
             LIMIT 5",
            [$vendorId]
        );
    }

    private static function financeSnapshot(array $analytics): void
    {
        $net = (float)$analytics['total_earnings'];
        if ($net <= 0) {
            $net = (float)$analytics['total_sales'] - (float)$analytics['platform_fees'] - (float)$analytics['gateway_fees'];
        }

        $items = [
            ['label' => 'Total earnings', 'value' => self::money($net), 'hint' => 'Net after marketplace and gateway fees where available.'],
            ['label' => 'Available balance', 'value' => self::money((float)$analytics['available_balance']), 'hint' => 'Completed or shipped earnings not yet withdrawn.'],
            ['label' => 'Pending withdrawal', 'value' => self::money((float)$analytics['pending_withdrawal']), 'hint' => 'Withdrawal requests awaiting approval or payment.'],
            ['label' => 'Marketplace fees', 'value' => self::money((float)$analytics['platform_fees']), 'hint' => 'Platform commission across vendor orders.'],
            ['label' => 'Refund requests', 'value' => number_format((int)($analytics['pending_returns'] ?? 0)), 'hint' => number_format((int)($analytics['total_returns'] ?? 0)) . ' total return records.'],
        ];

        echo '<div class="portal-table">';
        foreach ($items as $item) {
            echo '<div class="portal-row"><div><strong>' . e((string)$item['label']) . '</strong><span>' . e((string)$item['hint']) . '</span></div><em>' . e((string)$item['value']) . '</em></div>';
        }
        echo '</div>';
    }

    private static function productTable(array $products, bool $withImage): void
    {
        echo '<div class="vendor-table-wrap"><table class="vendor-table vendor-products-table"><thead><tr>' . ($withImage ? '<th>Image</th>' : '') . '<th>Product</th><th>Price</th><th>Stock</th><th>Status</th><th>Sales</th><th>Action</th></tr></thead><tbody>';
        foreach ($products as $product) {
            echo '<tr>';
            if ($withImage) {
                echo '<td data-label="Image">' . self::imagePreview((string)($product['image_path'] ?? '')) . '</td>';
            }
            $productUrl = (string)($product['slug'] ?? '') !== '' ? app_url('product/' . rawurlencode((string)$product['slug'])) : '';
            echo '<td data-label="Product"><strong>' . ($productUrl !== '' ? '<a class="vendor-text-link" href="' . e($productUrl) . '" target="_blank" rel="noopener">' . e((string)$product['name']) . '</a>' : e((string)$product['name'])) . '</strong><span>' . e((string)($product['sku'] ?? '')) . '</span></td>';
            echo '<td data-label="Price">' . e(self::money((float)$product['regular_price'], (string)$product['currency'])) . '</td><td data-label="Stock">' . e((string)$product['stock_quantity']) . '</td>';
            echo '<td data-label="Status"><em class="vendor-badge">' . e((string)$product['status']) . '</em></td><td data-label="Sales">' . e((string)$product['total_sales']) . '</td>';
            echo '<td data-label="Action"><div class="vendor-action-stack">';
            echo '<a class="vendor-link-btn" href="' . e(app_url('vendor/product-upload?edit_product=' . (int)$product['id'])) . '">Edit</a>';
            if ($productUrl !== '') {
                echo '<a class="vendor-text-link" href="' . e($productUrl) . '" target="_blank" rel="noopener">View</a>';
            }
            if ((string)($product['status'] ?? '') !== 'archived') {
                echo '<form class="vendor-action-form" method="post" onsubmit="return confirm(\'Archive this product? It will be hidden from shoppers.\')">' . self::csrf() . '<input type="hidden" name="intent" value="archive_product"><input type="hidden" name="product_id" value="' . e((string)$product['id']) . '"><button type="submit">Archive</button></form>';
            }
            echo '<form class="vendor-action-form" method="post" onsubmit="return confirm(\'Delete this product? Products with order history will be archived instead.\')">' . self::csrf() . '<input type="hidden" name="intent" value="delete_product"><input type="hidden" name="product_id" value="' . e((string)$product['id']) . '"><button class="is-danger" type="submit">Delete</button></form>';
            echo '</div></td></tr>';
        }
        if ($products === []) {
            echo '<tr><td colspan="' . ($withImage ? '7' : '6') . '">No products yet. Add your first product to begin verification.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function productLinks(string $encodedLinks, int $fallbackCount): string
    {
        $encodedLinks = trim($encodedLinks);
        if ($encodedLinks === '') {
            return e((string)$fallbackCount);
        }

        $html = [];
        foreach (explode('||', $encodedLinks) as $encoded) {
            [$productId, $slug, $name] = array_pad(explode('::', $encoded, 3), 3, '');
            $name = trim($name) !== '' ? trim($name) : ('Product #' . (int)$productId);
            if (trim($slug) !== '') {
                $html[] = '<a class="vendor-text-link vendor-product-link" href="' . e(app_url('product/' . rawurlencode(trim($slug)))) . '" target="_blank" rel="noopener">' . e($name) . '</a>';
            } else {
                $html[] = '<span class="vendor-product-link">' . e($name) . '</span>';
            }
        }

        return implode('', $html);
    }

    private static function trackingUrl(array $order): string
    {
        $stored = trim((string)($order['tracking_url'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $trackingNumber = trim((string)($order['tracking_number'] ?? ''));
        $template = trim((string)($order['tracking_url_template'] ?? ''));
        if ($trackingNumber === '' || $template === '') {
            return '';
        }

        return str_replace(['{tracking_number}', '{tracking}'], rawurlencode($trackingNumber), $template);
    }

    private static function orderStatusSelect(string $current): string
    {
        $html = '<select name="status">';
        foreach (['processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label) {
            $html .= '<option value="' . e($value) . '"' . ($current === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        return $html . '</select>';
    }

    private static function kycDocuments(int $vendorId): void
    {
        $rows = db()->fetchAll('SELECT d.*, f.path FROM vendor_kyc_documents d LEFT JOIN files f ON f.id = d.file_id WHERE d.vendor_id = ? ORDER BY d.submitted_at DESC LIMIT 20', [$vendorId]);
        echo '<div class="portal-table">';
        foreach ($rows as $row) {
            echo '<div class="portal-row"><div><strong>' . e(ucwords(str_replace('_', ' ', (string)$row['document_type']))) . '</strong><span>' . e((string)($row['document_number'] ?: 'No document number') . ' · ' . self::date((string)$row['submitted_at'])) . '</span></div><em>' . e((string)$row['status']) . '</em></div>';
        }
        if ($rows === []) {
            echo '<div class="portal-row"><div><strong>No KYC documents uploaded</strong><span>Upload business documents for admin review.</span></div><em>Pending</em></div>';
        }
        echo '</div>';
    }

    private static function kycDocumentSteps(int $vendorId): array
    {
        $rows = db()->fetchAll(
            'SELECT document_type, document_number, status, submitted_at
             FROM vendor_kyc_documents
             WHERE vendor_id = ?
             ORDER BY submitted_at DESC, id DESC',
            [$vendorId]
        );
        $latest = [];
        foreach ($rows as $row) {
            $type = (string)($row['document_type'] ?? '');
            if ($type !== '' && !isset($latest[$type])) {
                $latest[$type] = $row;
            }
        }

        $steps = [
            ['key' => 'business_registration', 'label' => 'Business registration certificate', 'help' => 'Upload your CAC, company registration certificate, business license, or equivalent registration document.', 'required' => true],
            ['key' => 'regulatory_documents', 'label' => 'Product / regulatory documents', 'help' => 'Optional: upload food, beauty, export, certification, permit, laboratory, or category-specific documents if applicable.', 'required' => false],
            ['key' => 'identity', 'label' => 'Government-issued ID', 'help' => 'Optional unless requested: passport, national ID, or driver license.', 'required' => false],
            ['key' => 'address', 'label' => 'Proof of address', 'help' => 'Optional unless requested: utility bill, bank statement, lease, or other address proof.', 'required' => false],
        ];

        foreach ($steps as &$step) {
            $row = $latest[$step['key']] ?? [];
            $step['status'] = (string)($row['status'] ?? 'not_uploaded');
            $step['number'] = (string)($row['document_number'] ?? '');
            $step['submitted_at'] = (string)($row['submitted_at'] ?? '');
        }
        unset($step);

        return $steps;
    }

    private static function latestVendorApplication(int $vendorId): ?array
    {
        if (!function_exists('table_exists') || !table_exists('vendor_applications')) {
            return null;
        }

        $row = db()->fetch(
            'SELECT * FROM vendor_applications WHERE vendor_id = ? AND application_type = "vendor" ORDER BY submitted_at DESC, id DESC LIMIT 1',
            [$vendorId]
        );

        return $row ?: null;
    }

    private static function kycSetupDetails(int $vendorId): array
    {
        $application = self::latestVendorApplication($vendorId);
        if (!$application || trim((string)($application['application_data'] ?? '')) === '') {
            return [];
        }

        $payload = json_decode((string)$application['application_data'], true);
        if (!is_array($payload)) {
            return [];
        }

        $setup = $payload['approved_vendor_setup'] ?? [];
        return is_array($setup) ? $setup : [];
    }

    private static function payoutDetailsForForm(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $raw;
        }

        if (isset($decoded['details']) && is_string($decoded['details'])) {
            return $decoded['details'];
        }

        $lines = [];
        foreach ($decoded as $key => $item) {
            if (is_scalar($item) && !in_array((string)$key, ['method', 'updated_at'], true)) {
                $lines[] = ucwords(str_replace('_', ' ', (string)$key)) . ': ' . (string)$item;
            }
        }

        return implode("\n", $lines);
    }

    private static function normalizePayoutDetails(string $method, string $details): ?string
    {
        if ($details === '') {
            return null;
        }

        $decoded = json_decode($details, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $decoded['method'] = $decoded['method'] ?? $method;
            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'method' => $method,
            'details' => $details,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function withdrawals(int $vendorId): array
    {
        return db()->fetchAll('SELECT * FROM vendor_withdrawals WHERE vendor_id = ? ORDER BY requested_at DESC, id DESC LIMIT 25', [$vendorId]);
    }

    private static function availableBalance(int $vendorId): float
    {
        $earnedRow = db()->fetch(
            'SELECT COALESCE(SUM(ovs.vendor_earning), 0) total
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ?
               AND ovs.status IN ("shipped","completed")
               AND o.payment_status = "paid"',
            [$vendorId]
        );
        $earned = (float)($earnedRow['total'] ?? 0);
        if ($earned <= 0) {
            $grossRow = db()->fetch(
                'SELECT COALESCE(SUM(ovs.gross_total), 0) total
                 FROM order_vendor_splits ovs
                 INNER JOIN orders o ON o.id = ovs.order_id
                 WHERE ovs.vendor_id = ?
                   AND ovs.status IN ("shipped","completed")
                   AND o.payment_status = "paid"',
                [$vendorId]
            );
            $earned = (float)($grossRow['total'] ?? 0);
        }
        $requested = self::sum('vendor_withdrawals', 'amount', 'vendor_id = ? AND status IN ("pending","approved","paid")', [$vendorId]);
        return max(0.0, $earned - $requested);
    }

    private static function saveAddress(array $vendor): ?int
    {
        $line1 = trim((string)($_POST['address_line1'] ?? ''));
        if ($line1 === '') {
            return !empty($vendor['address_id']) ? (int)$vendor['address_id'] : null;
        }

        $countryCode = self::normalizeCountryCode((string)($_POST['country_code'] ?? ''));
        $params = [
            $line1,
            trim((string)($_POST['city'] ?? '')),
            trim((string)($_POST['state'] ?? '')),
            $countryCode,
            sql_now(),
        ];

        if (!empty($vendor['address_id'])) {
            db()->query('UPDATE addresses SET address_line1 = ?, city = ?, state = ?, country_code = ?, updated_at = ? WHERE id = ?', [...$params, (int)$vendor['address_id']]);
            return (int)$vendor['address_id'];
        }

        db()->query('INSERT INTO addresses (user_id, type, address_line1, city, state, country_code, is_default, created_at, updated_at) VALUES (?, "store", ?, ?, ?, ?, 1, ?, ?)', [(int)$vendor['user_id'], $line1, $params[1], $params[2], $params[3], sql_now(), sql_now()]);
        return (int)db()->lastInsertId();
    }

    private static function normalizeCountryCode(string $country): ?string
    {
        $country = trim($country);
        if ($country === '') {
            return null;
        }

        $key = strtolower(preg_replace('/[^a-z]+/i', '', $country) ?: '');
        $known = [
            'canada' => 'CA',
            'ghana' => 'GH',
            'greatbritain' => 'GB',
            'jamaica' => 'JM',
            'kenya' => 'KE',
            'nigeria' => 'NG',
            'southafrica' => 'ZA',
            'trinidadandtobago' => 'TT',
            'uk' => 'GB',
            'unitedkingdom' => 'GB',
            'unitedstates' => 'US',
            'unitedstatesofamerica' => 'US',
            'us' => 'US',
            'usa' => 'US',
        ];
        if (isset($known[$key])) {
            return $known[$key];
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $country) ?: '');
        return $letters !== '' ? substr($letters, 0, 2) : null;
    }

    private static function storeUpload(string $field, int $ownerUserId, string $bucket, ?array $allowed = null): int
    {
        $allowed ??= self::UPLOAD_TYPES;
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Please choose a valid upload file.');
        }

        $tmp = (string)$file['tmp_name'];
        $mime = (string)(mime_content_type($tmp) ?: '');
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Unsupported file type.');
        }

        $vendorId = (int)(db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [$ownerUserId])['id'] ?? 0);
        $dir = APP_ROOT . '/public/uploads/vendor/' . max(1, $vendorId) . '/' . $bucket;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Upload directory could not be created.');
        }

        $ext = self::extension($mime, (string)$file['name']);
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Upload could not be saved.');
        }

        $relative = 'uploads/vendor/' . max(1, $vendorId) . '/' . $bucket . '/' . $name;
        $size = filesize($target) ?: 0;
        $dimensions = str_starts_with($mime, 'image/') ? @getimagesize($target) : false;

        db()->query(
            'INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text, created_at) VALUES (?, "public", ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ownerUserId, $relative, (string)$file['name'], $mime, $size, $dimensions[0] ?? null, $dimensions[1] ?? null, pathinfo((string)$file['name'], PATHINFO_FILENAME), sql_now()]
        );

        return (int)db()->lastInsertId();
    }

    private static function optionalUpload(string $field, int $ownerUserId, string $bucket): ?int
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return self::storeUpload($field, $ownerUserId, $bucket);
    }

    private static function extension(string $mime, string $original): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: 'bin'),
        };
    }

    private static function checklist(array $checks): void
    {
        echo '<div class="vendor-checklist">';
        foreach ($checks as $check) {
            echo '<div class="' . ((bool)$check['done'] ? 'is-done' : '') . '"><i></i><span>' . e((string)$check['label']) . '</span></div>';
        }
        echo '</div>';
    }

    private static function input(string $label, string $name, string $value = '', bool $required = false, string $type = 'text', string $step = ''): void
    {
        $unit = match ($name) {
            'weight', 'setup_net_weight', 'setup_package_weight' => 'kg',
            'length', 'width', 'height' => 'cm',
            'regular_price', 'sale_price' => 'USD',
            default => '',
        };
        $unitHtml = $unit !== '' ? ' (<strong style="font-weight:700">' . e($unit) . '</strong>)' : '';
        echo '<label class="vendor-field"><span>' . e($label) . $unitHtml . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . ($step !== '' ? ' step="' . e($step) . '"' : '') . '></label>';
    }

    private static function textarea(string $label, string $name, string $value = '', bool $required = false): void
    {
        echo '<label class="vendor-field"><span>' . e($label) . '</span><textarea name="' . e($name) . '"' . ($required ? ' required' : '') . '>' . e($value) . '</textarea></label>';
    }

    private static function select(string $label, string $name, array $options, string $value = '', bool $required = false): void
    {
        echo '<label class="vendor-field"><span>' . e($label) . '</span><select name="' . e($name) . '"' . ($required ? ' required' : '') . '>';
        echo '<option value="">Choose</option>';
        foreach ($options as $option) {
            $option = (string)$option;
            echo '<option value="' . e($option) . '"' . ($option === $value ? ' selected' : '') . '>' . e($option) . '</option>';
        }
        echo '</select></label>';
    }

    private static function formSection(string $title): void
    {
        echo '<div class="vendor-form-section"><h4>' . e($title) . '</h4></div>';
    }

    private static function imagePreview(string $path, bool $wide = false): string
    {
        $url = self::fileUrl($path);
        if ($url === '') {
            return '<div class="vendor-image-empty' . ($wide ? ' is-wide' : '') . '">No image</div>';
        }

        return '<img class="vendor-image-preview' . ($wide ? ' is-wide' : '') . '" src="' . e($url) . '" alt="">';
    }

    private static function imageUploadGuidance(string $label): string
    {
        return '<small class="vendor-upload-guidance">' . e($label . ': Minimum size 1,000 x 1,000 pixels to enable zoom. Recommended size 2,000 x 2,000 pixels. Format: JPEG (.jpg). Use a pure white background (RGB 255, 255, 255). Product should fill at least 85% of the image. No text, logos, watermarks, or additional graphics.') . '</small>';
    }

    private static function fileUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        return app_url(ltrim($path, '/'));
    }

    private static function nav(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => app_url(self::$distributionWorkspace ? 'distributor' : 'vendor')],
            ['key' => 'products', 'label' => 'Product Upload', 'url' => app_url('vendor/product-upload')],
            ['key' => 'inventory', 'label' => 'Inventory', 'url' => app_url('vendor/inventory')],
            ['key' => 'orders', 'label' => 'Orders', 'url' => app_url('vendor/orders')],
            ['key' => 'chats', 'label' => 'Chats', 'url' => app_url('vendor/chats')],
            ['key' => 'tracking', 'label' => 'Tracking', 'url' => app_url('vendor/tracking')],
            ['key' => 'customers', 'label' => 'Customers', 'url' => app_url('vendor/customers')],
            ['key' => 'reviews', 'label' => 'Reviews', 'url' => app_url('vendor/reviews')],
            ['key' => 'payouts', 'label' => 'Payouts', 'url' => app_url('vendor/payouts')],
            ['key' => 'plans', 'label' => 'Plans', 'url' => app_url('vendor/plans')],
            ['key' => 'kyc', 'label' => 'Verification', 'url' => app_url('vendor/kyc')],
            ['key' => 'settings', 'label' => 'Store Setup', 'url' => app_url('vendor/settings')],
        ];
    }

    private static function title(string $active): string
    {
        return match ($active) {
            'products' => 'Product Upload',
            'inventory' => 'Inventory',
            'orders' => 'Orders',
            'chats' => 'Chats',
            'tracking' => 'Tracking',
            'customers' => 'Customers',
            'reviews' => 'Reviews',
            'payouts' => 'Payouts',
            'plans' => 'Plans',
            'kyc' => 'Verification',
            'settings' => 'Store Setup',
            default => 'Vendor Dashboard',
        };
    }

    private static function pageMeta(string $active): array
    {
        return match ($active) {
            'products' => [
                'eyebrow' => 'Catalog',
                'title' => 'Product Upload',
                'description' => 'Create a product listing, upload a clear image, set pricing, stock, weight, and dimensions, then submit it for admin review.',
                'tone' => 'green',
            ],
            'inventory' => [
                'eyebrow' => 'Stock',
                'title' => 'Inventory',
                'description' => 'Monitor available stock, low-stock alerts, out-of-stock products, and jump directly into product edits when quantities change.',
                'tone' => 'slate',
            ],
            'orders' => [
                'eyebrow' => 'Fulfillment',
                'title' => 'Orders',
                'description' => 'Review customer orders, update fulfillment status, add tracking information, and keep buyers informed as orders move.',
                'tone' => 'blue',
            ],
            'chats' => [
                'eyebrow' => 'Messages',
                'title' => 'Chats',
                'description' => 'Reply to buyer conversations with approved marketplace responses and open full threads when customers need more help.',
                'tone' => 'teal',
            ],
            'tracking' => [
                'eyebrow' => 'Shipping',
                'title' => 'Tracking',
                'description' => 'See shipment status, tracking numbers, carrier links, and recently updated delivery records for your vendor orders.',
                'tone' => 'blue',
            ],
            'customers' => [
                'eyebrow' => 'Relationships',
                'title' => 'Customers',
                'description' => 'View customers who have ordered from your store, their order counts, total spend, and most recent purchase activity.',
                'tone' => 'purple',
            ],
            'reviews' => [
                'eyebrow' => 'Trust',
                'title' => 'Reviews',
                'description' => 'Track ratings and product feedback, open your shareable review link, and understand what shoppers are saying.',
                'tone' => 'gold',
            ],
            'payouts' => [
                'eyebrow' => 'Finance',
                'title' => 'Payouts',
                'description' => 'Check your available balance, submit withdrawal requests, and review payout history from one finance page.',
                'tone' => 'green',
            ],
            'plans' => [
                'eyebrow' => 'Subscription',
                'title' => 'Vendor Plans',
                'description' => 'Review your current package, published product limit, remaining slots, and upgrade your Seller Africa vendor plan.',
                'tone' => 'green',
            ],
            'kyc' => [
                'eyebrow' => 'Approval',
                'title' => 'Verification',
                'description' => 'Complete your store readiness checklist and upload business, identity, tax, or address documents for team review.',
                'tone' => 'gold',
            ],
            'settings' => [
                'eyebrow' => 'Store Profile',
                'title' => 'Store Setup',
                'description' => 'Update your account profile, password, store logo, banner, origin details, address, and payout information.',
                'tone' => 'orange',
            ],
            default => [
                'eyebrow' => 'Overview',
                'title' => 'Vendor Dashboard',
                'description' => 'Review sales, orders, store health, and the next actions needed to grow your Seller Africa store.',
                'tone' => 'green',
            ],
        };
    }

    private static function pageUrl(string $active): string
    {
        return match ($active) {
            'dashboard' => app_url('vendor'),
            'products' => app_url('vendor/product-upload'),
            'kyc' => app_url('vendor/kyc'),
            'chats' => app_url('vendor/chats'),
            default => app_url('vendor/' . $active),
        };
    }

    private static function csrf(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">';
    }

    private static function toastStack(array $toasts): void
    {
        if ($toasts === []) {
            return;
        }
        echo '<div class="vendor-toasts">';
        foreach ($toasts as $toast) {
            echo '<div class="vendor-toast is-' . e((string)($toast['type'] ?? 'info')) . '">' . e((string)($toast['message'] ?? '')) . '</div>';
        }
        echo '</div>';
    }

    private static function uniqueSlug(string $table, string $column, string $value): string
    {
        $base = trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $value)), '-') ?: 'vendor-item';
        $slug = $base;
        $i = 2;
        while (db()->fetch("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1", [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private static function ensureVendorSchema(): void
    {
        if (!function_exists('table_exists') || !table_exists('vendors')) {
            return;
        }
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                application_type VARCHAR(40) NOT NULL DEFAULT 'vendor',
                business_name VARCHAR(190) NOT NULL,
                owner_name VARCHAR(190) NOT NULL,
                product_category VARCHAR(120) NULL,
                application_data JSON NULL,
                submitted_at DATETIME NOT NULL,
                INDEX idx_vendor_applications_vendor (vendor_id),
                INDEX idx_vendor_applications_user (user_id),
                INDEX idx_vendor_applications_type (application_type),
                INDEX idx_vendor_applications_submitted (submitted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        if (function_exists('table_exists') && table_exists('vendor_applications') && !self::columnExists('vendor_applications', 'application_type')) {
            db()->pdo()->exec("ALTER TABLE vendor_applications ADD application_type VARCHAR(40) NOT NULL DEFAULT 'vendor' AFTER user_id");
        }
        if (!self::columnExists('vendors', 'origin_region')) {
            db()->pdo()->exec('ALTER TABLE vendors ADD origin_region VARCHAR(80) NULL AFTER description');
        }
        if (!self::columnExists('vendors', 'country_of_origin')) {
            db()->pdo()->exec('ALTER TABLE vendors ADD country_of_origin VARCHAR(120) NULL AFTER origin_region');
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        try {
            $config = db_config();
            $row = db()->fetch(
                "SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [(string)($config['database'] ?? ''), $table, $column]
            );

            return (int)($row['total'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function productSku(string $input, string $name, ?int $ignoreProductId = null): string
    {
        $base = ProductService::normalizeSku($input);
        if ($base === '') {
            $base = ProductService::normalizeSku($name);
        }
        if ($base === '') {
            $base = 'SAC-PRODUCT';
        }

        $base = substr($base, 0, 86);
        $sku = $base;
        $counter = 2;
        $params = [$sku];
        $sql = 'SELECT id FROM products WHERE sku = ?';
        if ($ignoreProductId !== null && $ignoreProductId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreProductId;
        }

        while (db()->fetch($sql . ' LIMIT 1', $params)) {
            $suffix = '-' . $counter++;
            $sku = substr($base, 0, 100 - strlen($suffix)) . $suffix;
            $params = [$sku];
            if ($ignoreProductId !== null && $ignoreProductId > 0) {
                $params[] = $ignoreProductId;
            }
        }

        return $sku;
    }

    private static function count(string $table, string $where, array $params): int
    {
        $row = db()->fetch("SELECT COUNT(*) total FROM {$table} WHERE {$where}", $params);
        return (int)($row['total'] ?? 0);
    }

    private static function sum(string $table, string $field, string $where, array $params): float
    {
        $row = db()->fetch("SELECT COALESCE(SUM({$field}), 0) total FROM {$table} WHERE {$where}", $params);
        return (float)($row['total'] ?? 0);
    }

    private static function money(float $amount, string $currency = 'USD'): string
    {
        $symbol = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency) . ' ';
        return $symbol . number_format($amount, 2);
    }

    private static function date(string $date): string
    {
        return $date !== '' ? date('M j, Y', strtotime($date)) : 'Not dated';
    }

    private static function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status ?: 'pending'));
    }

    private static function nullableFloat(mixed $value): ?float
    {
        $value = trim((string)$value);
        return $value === '' ? null : (float)$value;
    }

    private static function assetVersion(string $type): string
    {
        $file = APP_ROOT . '/public/assets/' . $type . '/role-dashboard.' . $type;
        return is_file($file) ? (string)filemtime($file) : '1';
    }
}
