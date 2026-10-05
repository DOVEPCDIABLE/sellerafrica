<?php

declare(strict_types=1);

namespace App;

final class RoleDashboardService
{
    public static function requireRole(string $role): void
    {
        if (!AuthService::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? app_url($role);
            redirect('login');
        }

        $roles = $_SESSION['roles'] ?? [];
        if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true) || in_array($role, $roles, true)) {
            if (!in_array('super_admin', $roles, true) && !in_array('admin', $roles, true)) {
                if ($role === 'affiliate') {
                    SecurityService::assertAffiliateApproved((int)($_SESSION['user_id'] ?? 0));
                }
            }
            return;
        }

        render_restricted_page(403, 'This page is restricted', 'This account does not have access to this dashboard. Go home or open the correct dashboard for your account.', ['reason' => 'role_denied', 'role' => $role]);
        exit;
    }

    public static function portal(string $portal, string $active, string $title, array $cards = [], array $rows = []): void
    {
        $intent = (string)($_POST['intent'] ?? '');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $portal === 'buyer' && $intent === 'upgrade_to_vendor') {
            flash('info', 'Please complete the full vendor application so our team can review your business and KYC details.');
            redirect('vendor/register');
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($active === 'profile' || in_array($intent, ['save_profile', 'change_password'], true))) {
            self::handleAccountPost($portal, $active);
        } elseif ($portal === 'affiliate' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::handleAffiliatePost($active);
        }

        $brand = app_branding();
        $user = AuthService::user() ?? [];
        $nav = self::navigation($portal);
        $accent = match ($portal) {
            'vendor' => '#16a34a',
            'affiliate' => '#f97316',
            default => '#006b52',
        };
        $portalLabel = ucfirst($portal);

        echo '<!doctype html><html lang="en"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . e($title . ' | ' . $brand['name']) . '</title>';
        echo '<link rel="stylesheet" href="' . e(asset('css/role-dashboard.css?v=' . self::assetVersion())) . '">';
        echo '</head><body style="--portal-accent:' . e($accent) . '">';
        echo '<div class="portal-shell">';
        self::sidebar($portal, $active, $nav, $brand, $portalLabel);
        echo '<main class="portal-main">';
        self::topbar($title, $portalLabel, $user);
        self::toasts();
        if ($portal === 'buyer' && $active === 'dashboard') {
            self::buyerDashboard($user);
        } else {
            self::hero($title, $portalLabel);
            self::cards($cards);
            self::workspace($portal, $active, $rows);
        }
        echo '</main></div>';
        echo '<script src="' . e(asset('js/role-dashboard.js?v=' . self::assetVersion('js'))) . '"></script>';
        echo app_chat_widget_embed();
        echo '</body></html>';
    }

    public static function stats(string $portal): array
    {
        return match ($portal) {
            'vendor' => [
                ['label' => 'Products', 'value' => self::count('products', 'vendor_id IN (SELECT id FROM vendors WHERE user_id = ?)', [self::userId()]), 'tone' => 'green'],
                ['label' => 'Pending Orders', 'value' => self::countVendorOrders('pending'), 'tone' => 'orange'],
                ['label' => 'Total Sales', 'value' => self::money(self::sumVendorSplits('gross_total')), 'tone' => 'black'],
                ['label' => 'Payouts', 'value' => self::money(self::sumVendorWithdrawals()), 'tone' => 'blue'],
            ],
            'affiliate' => [
                ['label' => 'Referral Links', 'value' => self::count('affiliate_links', 'affiliate_id IN (SELECT id FROM affiliates WHERE user_id = ?)', [self::userId()]), 'tone' => 'green'],
                ['label' => 'Visits', 'value' => self::affiliateField('visits_count'), 'tone' => 'orange'],
                ['label' => 'Approved Earnings', 'value' => self::money((float)self::affiliateField('total_earnings')), 'tone' => 'black'],
                ['label' => 'Unpaid Earnings', 'value' => self::money((float)self::affiliateField('unpaid_earnings')), 'tone' => 'blue'],
            ],
            default => [
                ['label' => 'Orders', 'value' => self::count('orders', 'customer_id = ?', [self::userId()]), 'tone' => 'green'],
                ['label' => 'Pending', 'value' => self::count('orders', 'customer_id = ? AND status IN ("pending", "processing", "on_hold")', [self::userId()]), 'tone' => 'orange'],
                ['label' => 'Spent', 'value' => self::money(self::sumOrders()), 'tone' => 'black'],
                ['label' => 'Wishlist', 'value' => self::count('wishlist_items', 'wishlist_id IN (SELECT id FROM wishlists WHERE user_id = ?)', [self::userId()]), 'tone' => 'blue'],
            ],
        };
    }

    public static function rows(string $portal, string $active): array
    {
        if ($portal === 'affiliate') {
            return self::affiliateRows($active);
        }

        if ($portal === 'buyer' || $portal === '') {
            return self::buyerRows($active);
        }

        if ($portal === 'vendor') {
            return self::vendorRows($active);
        }

        return [
            ['title' => self::rowTitle($portal, $active, 1), 'meta' => 'Ready for live actions', 'status' => 'Active'],
            ['title' => self::rowTitle($portal, $active, 2), 'meta' => 'Fast-loading portal surface', 'status' => 'Review'],
            ['title' => self::rowTitle($portal, $active, 3), 'meta' => 'Database-ready module shell', 'status' => 'Draft'],
        ];
    }

    private static function buyerRows(string $active): array
    {
        $userId = self::userId();

        if ($active === 'wishlist') {
            $rows = db()->fetchAll(
                "SELECT p.name, p.regular_price, p.sale_price, p.currency, p.stock_status, wi.created_at
                 FROM wishlist_items wi
                 INNER JOIN wishlists w ON w.id = wi.wishlist_id
                 INNER JOIN products p ON p.id = wi.product_id
                 WHERE w.user_id = ?
                 ORDER BY wi.created_at DESC
                 LIMIT 20",
                [$userId]
            );

            if ($rows === []) {
                return [['title' => 'No items saved yet', 'meta' => 'Products you save will appear here', 'status' => 'Empty']];
            }

            return array_map(static function (array $row): array {
                $price = (float)($row['sale_price'] ?: $row['regular_price']);
                return [
                    'title' => (string)$row['name'],
                    'meta' => self::money($price),
                    'status' => $row['stock_status'] === 'out_of_stock' ? 'Out of stock' : 'In stock',
                ];
            }, $rows);
        }

        if ($active === 'addresses') {
            $rows = db()->fetchAll(
                'SELECT type, first_name, last_name, city, country_code, is_default
                 FROM addresses
                 WHERE user_id = ?
                 ORDER BY is_default DESC, created_at DESC
                 LIMIT 20',
                [$userId]
            );

            if ($rows === []) {
                return [['title' => 'No saved addresses yet', 'meta' => 'Add an address at checkout to see it here', 'status' => 'Empty']];
            }

            return array_map(static function (array $row): array {
                $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                $location = trim((string)($row['city'] ?? '') . ', ' . (string)($row['country_code'] ?? ''), ', ');
                return [
                    'title' => $name !== '' ? $name : ucfirst((string)$row['type']) . ' address',
                    'meta' => $location !== '' ? $location : ucfirst((string)$row['type']),
                    'status' => ((int)$row['is_default'] === 1) ? 'Default' : ucfirst((string)$row['type']),
                ];
            }, $rows);
        }

        if ($active === 'chats') {
            $rows = ChatService::conversationsForBuyer($userId);

            if ($rows === []) {
                return [['title' => 'No chats yet', 'meta' => 'Start a chat from a product or vendor page', 'status' => 'Empty']];
            }

            return array_map(static function (array $row): array {
                $latest = ChatService::messageText($row['last_sender'] ?? null, $row['last_message_key'] ?? null);
                $product = trim((string)($row['product_name'] ?? ''));
                return [
                    'title' => 'Chat with ' . (string)($row['store_name'] ?: 'Seller'),
                    'meta' => ($product !== '' ? $product . ' · ' : '') . ($latest !== '' ? $latest : 'Open conversation'),
                    'status' => date('M j, Y', strtotime((string)$row['updated_at'])),
                    'url' => app_url('chat?id=' . (int)$row['id']),
                ];
            }, $rows);
        }

        // Dashboard overview and Orders tab both show real order history.
        $rows = db()->fetchAll(
            'SELECT order_number, status, grand_total, currency, created_at,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = orders.id) AS item_count
             FROM orders
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$userId, $active === 'orders' ? 20 : 5]
        );

        if ($rows === []) {
            return [['title' => 'No orders yet', 'meta' => 'Your order history will show up here once you place one', 'status' => 'Empty']];
        }

        return array_map(static function (array $row): array {
            $itemCount = (int)$row['item_count'];
            $date = date('M j, Y', strtotime((string)$row['created_at']));
            return [
                'title' => 'Order #' . $row['order_number'],
                'meta' => $itemCount . ' item' . ($itemCount === 1 ? '' : 's') . ' · ' . $date . ' · ' . self::money((float)$row['grand_total']),
                'status' => ucfirst(str_replace('_', ' ', (string)$row['status'])),
            ];
        }, $rows);
    }

    private static function vendorRows(string $active): array
    {
        $userId = self::userId();
        $vendorSub = 'SELECT id FROM vendors WHERE user_id = ?';

        if ($active === 'products') {
            $rows = db()->fetchAll(
                "SELECT name, regular_price, sale_price, stock_status, status
                 FROM products
                 WHERE vendor_id IN ({$vendorSub})
                 ORDER BY created_at DESC
                 LIMIT 20",
                [$userId]
            );

            if ($rows === []) {
                return [['title' => 'No products listed yet', 'meta' => 'Add your first product to start selling', 'status' => 'Empty']];
            }

            return array_map(static function (array $row): array {
                $price = (float)($row['sale_price'] ?: $row['regular_price']);
                return [
                    'title' => (string)$row['name'],
                    'meta' => self::money($price) . ($row['stock_status'] === 'out_of_stock' ? ' · Out of stock' : ''),
                    'status' => ucfirst((string)$row['status']),
                ];
            }, $rows);
        }

        if ($active === 'payouts') {
            $rows = db()->fetchAll(
                "SELECT amount, currency, method, status, requested_at
                 FROM vendor_withdrawals
                 WHERE vendor_id IN ({$vendorSub})
                 ORDER BY requested_at DESC
                 LIMIT 20",
                [$userId]
            );

            if ($rows === []) {
                return [['title' => 'No payout requests yet', 'meta' => 'Withdrawal requests will show up here', 'status' => 'Empty']];
            }

            return array_map(static function (array $row): array {
                $date = date('M j, Y', strtotime((string)$row['requested_at']));
                return [
                    'title' => self::money((float)$row['amount']) . ' via ' . ($row['method'] ?: 'default method'),
                    'meta' => 'Requested ' . $date,
                    'status' => ucfirst((string)$row['status']),
                ];
            }, $rows);
        }

        if ($active === 'kyc') {
            $row = db()->fetch('SELECT kyc_status, status FROM vendors WHERE user_id = ?', [$userId]);
            $kycStatus = (string)($row['kyc_status'] ?? 'not_started');
            $label = match ($kycStatus) {
                'approved' => 'Your identity verification is approved',
                'pending' => 'Your documents are under review',
                'rejected' => 'Your last submission was rejected, please resubmit',
                'expired' => 'Your verification has expired, please resubmit',
                default => 'You have not started identity verification yet',
            };
            return [['title' => 'KYC Verification', 'meta' => $label, 'status' => ucwords(str_replace('_', ' ', $kycStatus))]];
        }

        // Dashboard overview and Orders tab both show real order splits for this vendor.
        $rows = db()->fetchAll(
            "SELECT ovs.status, ovs.gross_total, ovs.vendor_earning, ovs.created_at, o.order_number
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id IN ({$vendorSub})
               AND o.payment_status = 'paid'
             ORDER BY ovs.created_at DESC
             LIMIT ?",
            [$userId, $active === 'orders' ? 20 : 5]
        );

        if ($rows === []) {
            return [['title' => 'No orders yet', 'meta' => 'Orders for your products will show up here', 'status' => 'Empty']];
        }

        return array_map(static function (array $row): array {
            $date = date('M j, Y', strtotime((string)$row['created_at']));
            return [
                'title' => 'Order #' . $row['order_number'],
                'meta' => $date . ' · Earning ' . self::money((float)$row['vendor_earning']),
                'status' => ucfirst(str_replace('_', ' ', (string)$row['status'])),
            ];
        }, $rows);
    }

    private static function navigation(string $portal): array
    {
        return match ($portal) {
            'vendor' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => app_url('vendor')],
                ['key' => 'products', 'label' => 'Products', 'url' => app_url('vendor/products')],
                ['key' => 'orders', 'label' => 'Orders', 'url' => app_url('vendor/orders')],
                ['key' => 'payouts', 'label' => 'Payouts', 'url' => app_url('vendor/payouts')],
                ['key' => 'kyc', 'label' => 'KYC Documents', 'url' => app_url('vendor/kyc')],
                ['key' => 'settings', 'label' => 'Store Settings', 'url' => app_url('vendor/settings')],
            ],
            'affiliate' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => app_url('affiliate')],
                ['key' => 'referrals', 'label' => 'Referral Links', 'url' => app_url('affiliate/referrals')],
                ['key' => 'commissions', 'label' => 'Commissions', 'url' => app_url('affiliate/commissions')],
                ['key' => 'payouts', 'label' => 'Payouts', 'url' => app_url('affiliate/payouts')],
                ['key' => 'creatives', 'label' => 'Creatives', 'url' => app_url('affiliate/creatives')],
                ['key' => 'settings', 'label' => 'Settings', 'url' => app_url('affiliate/settings')],
                ['key' => 'profile', 'label' => 'Profile', 'url' => app_url('affiliate/profile')],
            ],
            default => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => app_url('buyer')],
                ['key' => 'orders', 'label' => 'Orders', 'url' => app_url('buyer/orders')],
                ['key' => 'chats', 'label' => 'Chats', 'url' => app_url('buyer/chats')],
                ['key' => 'wishlist', 'label' => 'Wishlist', 'url' => app_url('buyer/wishlist')],
                ['key' => 'addresses', 'label' => 'Addresses', 'url' => app_url('buyer/addresses')],
                ['key' => 'profile', 'label' => 'Profile', 'url' => app_url('buyer/profile')],
            ],
        };
    }

    private static function sidebar(string $portal, string $active, array $nav, array $brand, string $portalLabel): void
    {
        echo '<aside class="portal-sidebar">';
        echo '<a class="portal-brand" href="' . e(app_url($portal === 'buyer' ? 'buyer' : $portal)) . '">';
        echo '<img src="' . e((string)$brand['logo']) . '" alt="' . e((string)$brand['name']) . '"><span>' . e((string)$brand['name']) . '</span></a>';
        echo '<div class="portal-chip">' . e($portalLabel) . ' Workspace</div><nav>';
        foreach ($nav as $item) {
            $isActive = $item['key'] === $active;
            echo '<a class="portal-nav-link' . ($isActive ? ' is-active' : '') . '" href="' . e($item['url']) . '">';
            echo '<span class="portal-dot"></span><span>' . e($item['label']) . '</span></a>';
        }
        echo '</nav><div class="portal-sidebar-footer">';
        echo '<a class="portal-store-link portal-store-link-secondary" href="' . e(app_url('store')) . '">Back to Store</a>';
        echo '<a class="portal-store-link" href="' . e(app_url('logout')) . '">Sign Out</a></div></aside>';
    }

    private static function topbar(string $title, string $portalLabel, array $user): void
    {
        echo '<header class="portal-topbar"><button class="portal-menu" type="button" aria-label="Toggle menu" data-portal-menu><i></i><i></i><i></i></button>';
        echo '<div><p>' . e($portalLabel) . '</p><h1>' . e($title) . '</h1></div>';
        echo '<div class="portal-user"><span>' . e((string)($user['display_name'] ?? 'Account')) . '</span><strong>' . e(substr((string)($user['display_name'] ?? 'A'), 0, 1)) . '</strong></div></header>';
    }

    private static function hero(string $title, string $portalLabel): void
    {
        echo '<section class="portal-hero"><div><span>' . e($portalLabel) . ' Dashboard</span><h2>' . e($title) . '</h2>';
        echo '<p>A clean workspace for quick decisions, daily operations, and account activity.</p></div>';
        echo '<div style="display: flex; gap: 12px; flex-wrap: wrap;">';
        echo '<a class="portal-action" href="' . e(app_url('store')) . '">Open Storefront</a>';
        if (strtolower($portalLabel) === 'buyer') {
            if (!in_array('vendor', $_SESSION['roles'] ?? [], true)) {
                echo '<a class="portal-action" style="background: #16a34a;" href="' . e(app_url('register.php?type=vendor')) . '">Upgrade to Seller</a>';
            } else {
                echo '<a class="portal-action" style="background: #16a34a;" href="' . e(app_url('vendor')) . '">Seller Dashboard</a>';
            }
        }
        echo '</div></section>';
    }

    private static function cards(array $cards): void
    {
        echo '<section class="portal-cards">';
        foreach ($cards as $card) {
            echo '<article class="portal-card tone-' . e((string)$card['tone']) . '">';
            echo '<p>' . e((string)$card['label']) . '</p><strong>' . e((string)$card['value']) . '</strong><span>Updated now</span></article>';
        }
        echo '</section>';
    }

    private static function workspace(string $portal, string $active, array $rows): void
    {
        if ($active === 'profile') {
            self::accountWorkspace($portal, $active);
            return;
        }

        if ($portal === 'affiliate') {
            self::affiliateWorkspace($active, $rows);
            return;
        }

        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>' . e(ucfirst($portal)) . '</p><h3>' . e(ucwords(str_replace('-', ' ', $active))) . '</h3></div>';
        echo '<button type="button">Export</button></div><div class="portal-table">';
        foreach ($rows as $row) {
            $url = trim((string)($row['url'] ?? ''));
            $title = $url !== '' ? '<a href="' . e($url) . '">' . e($row['title']) . '</a>' : e($row['title']);
            $status = $url !== '' ? '<a href="' . e($url) . '">Open</a>' : e($row['status']);
            echo '<div class="portal-row"><div><strong>' . $title . '</strong><span>' . e($row['meta']) . '</span></div><em>' . $status . '</em></div>';
        }
        echo '</div></section>';
    }

    private static function affiliateWorkspace(string $active, array $rows): void
    {
        $affiliate = self::affiliate();
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>Affiliate</p><h3>' . e(ucwords(str_replace('-', ' ', $active))) . '</h3></div>';
        echo '<a class="portal-action" href="' . e(app_url('affiliate/referrals')) . '">Create Link</a></div>';

        if ($active === 'dashboard') {
            echo '<div class="portal-table">';
            echo '<div class="portal-row"><div><strong>Referral code</strong><span>' . e((string)($affiliate['referral_code'] ?? '-')) . '</span></div><em>' . e((string)($affiliate['status'] ?? 'pending')) . '</em></div>';
            echo '<div class="portal-row"><div><strong>Default store link</strong><span>' . e(AffiliateService::referralUrl(app_url('store'), (string)($affiliate['referral_code'] ?? ''))) . '</span></div><em>Ready</em></div>';
            echo '</div>';
        }

        if ($active === 'referrals') {
            self::affiliateLinkForm();
        } elseif ($active === 'payouts') {
            self::affiliatePayoutForm();
        } elseif ($active === 'settings') {
            self::affiliateSettingsForm($affiliate);
            self::accountForms($active);
        }

        echo '<div class="portal-table">';
        foreach ($rows as $row) {
            $url = trim((string)($row['url'] ?? ''));
            $title = $url !== '' ? '<a href="' . e($url) . '">' . e($row['title']) . '</a>' : e($row['title']);
            $status = $url !== '' ? '<a href="' . e($url) . '">Open</a>' : e($row['status']);
            echo '<div class="portal-row"><div><strong>' . $title . '</strong><span>' . e($row['meta']) . '</span></div><em>' . $status . '</em></div>';
        }
        if ($rows === []) {
            echo '<div class="portal-row"><div><strong>No records yet</strong><span>Your affiliate activity will appear here.</span></div><em>Empty</em></div>';
        }
        echo '</div></section>';
    }

    private static function affiliateLinkForm(): void
    {
        $products = [];
        try {
            if (table_exists('products')) {
                $products = db()->fetchAll(
                    "SELECT id, name, slug
                     FROM products
                     WHERE status = 'active' AND slug IS NOT NULL AND slug <> ''
                     ORDER BY updated_at DESC, id DESC
                     LIMIT 80"
                );
            }
        } catch (\Throwable) {
            $products = [];
        }

        echo '<form class="portal-panel-head" method="post">';
        echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '"><input type="hidden" name="intent" value="create_link">';
        echo '<select name="product_id" style="min-width:220px;padding:12px;border:1px solid #e5e7eb;border-radius:10px"><option value="0">Store or custom URL</option>';
        foreach ($products as $product) {
            echo '<option value="' . e((int)$product['id']) . '">' . e((string)$product['name']) . '</option>';
        }
        echo '</select>';
        echo '<input name="target_url" type="url" placeholder="https://sellerafrica.com/shop or paste any product page" style="width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:10px">';
        echo '<button class="portal-action" type="submit">Generate</button></form>';
    }

    private static function affiliatePayoutForm(): void
    {
        echo '<form class="portal-panel-head" method="post">';
        echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '"><input type="hidden" name="intent" value="request_payout">';
        echo '<input name="amount" type="number" min="1" step="0.01" required placeholder="Amount" style="width:160px;padding:12px;border:1px solid #e5e7eb;border-radius:10px">';
        echo '<select name="payment_method" style="padding:12px;border:1px solid #e5e7eb;border-radius:10px"><option value="bank_transfer">Bank transfer</option><option value="paypal">PayPal</option></select>';
        echo '<button class="portal-action" type="submit">Request</button></form>';
    }

    private static function affiliateSettingsForm(array $affiliate): void
    {
        echo '<form class="portal-panel-head" method="post">';
        echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '"><input type="hidden" name="intent" value="save_settings">';
        echo '<input name="payment_email" type="email" required value="' . e((string)($affiliate['payment_email'] ?? '')) . '" placeholder="Payment email" style="width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:10px">';
        echo '<button class="portal-action" type="submit">Save</button></form>';
    }

    private static function buyerDashboard(array $sessionUser): void
    {
        $user = self::accountUser() ?: $sessionUser;
        $data = self::buyerDashboardData($user);
        $firstName = trim((string)($user['first_name'] ?? '')) ?: trim((string)($user['display_name'] ?? '')) ?: 'there';
        $memberSince = !empty($user['created_at']) ? date('F Y', strtotime((string)$user['created_at'])) : 'Today';

        echo '<section class="buyer-dashboard">';
        echo '<section class="buyer-hero">';
        echo '<div><span>Welcome back</span><h2>' . e($firstName) . '</h2><p>Pick up where you left off, track active orders, and discover products selected for your next shop.</p>';
        echo '<div class="buyer-hero__meta"><strong>Member since ' . e($memberSince) . '</strong><strong>' . e($data['loyaltyLevel']) . '</strong></div></div>';
        echo '<div class="buyer-hero__actions"><a class="buyer-btn buyer-btn--gold" href="' . e(app_url('shop')) . '">Browse marketplace</a><a class="buyer-btn" href="' . e(app_url('track-order')) . '">Track order</a></div>';
        echo '</section>';

        echo '<section class="buyer-metrics" aria-label="Dashboard overview">';
        foreach ($data['metrics'] as $metric) {
            echo '<a class="buyer-metric" href="' . e((string)$metric['url']) . '"><span>' . e((string)$metric['label']) . '</span><strong>' . e((string)$metric['value']) . '</strong><small>' . e((string)$metric['hint']) . '</small></a>';
        }
        echo '</section>';

        echo '<section class="buyer-grid buyer-grid--main">';
        self::buyerPanel('My Orders', 'Active orders and recent movement.', app_url('buyer/orders'), 'View all orders', function () use ($data): void {
            echo '<div class="buyer-status-rail">';
            foreach ($data['orderSteps'] as $step) {
                echo '<span class="' . ($step['active'] ? 'is-active' : '') . '"><i></i>' . e($step['label']) . '</span>';
            }
            echo '</div>';
            foreach ($data['recentOrders'] as $order) {
                echo '<a class="buyer-list-row" href="' . e(app_url('buyer/orders')) . '"><strong>' . e((string)$order['title']) . '</strong><span>' . e((string)$order['meta']) . '</span><em>' . e((string)$order['status']) . '</em></a>';
            }
        });
        self::buyerPanel('Continue Shopping', 'Recommended based on popular SAC categories.', app_url('shop'), 'Shop now', function () use ($data): void {
            echo '<div class="buyer-category-grid">';
            foreach ($data['categories'] as $category) {
                echo '<a href="' . e((string)$category['url']) . '">' . e((string)$category['label']) . '</a>';
            }
            echo '</div>';
        });
        echo '</section>';

        echo '<section class="buyer-grid buyer-grid--cards">';
        foreach ($data['featureCards'] as $card) {
            echo '<article class="buyer-mini-card"><div><span>' . e((string)$card['kicker']) . '</span><h3>' . e((string)$card['title']) . '</h3><p>' . e((string)$card['body']) . '</p></div><strong>' . e((string)$card['value']) . '</strong><a href="' . e((string)$card['url']) . '">' . e((string)$card['cta']) . '</a></article>';
        }
        echo '</section>';

        echo '<section class="buyer-grid buyer-grid--main">';
        self::buyerPanel('Personalized Recommendations', 'Trending, seasonal, and diaspora favorites.', app_url('shop'), 'Explore products', function () use ($data): void {
            echo '<div class="buyer-product-list">';
            foreach ($data['products'] as $product) {
                echo '<a class="buyer-product" href="' . e((string)$product['url']) . '"><span>' . e((string)$product['initial']) . '</span><div><strong>' . e((string)$product['name']) . '</strong><small>' . e((string)$product['price']) . '</small></div></a>';
            }
            echo '</div>';
        });
        self::buyerPanel('Shopping Insights', 'A quick read on this year of shopping.', app_url('buyer/orders'), 'Review orders', function () use ($data): void {
            echo '<div class="buyer-insights">';
            foreach ($data['insights'] as $insight) {
                echo '<div><span>' . e((string)$insight['label']) . '</span><strong>' . e((string)$insight['value']) . '</strong></div>';
            }
            echo '</div>';
        });
        echo '</section>';

        self::buyerVendorUpgradePanel($user);

        echo '<section class="buyer-grid buyer-grid--cards">';
        foreach ($data['serviceCards'] as $card) {
            echo '<article class="buyer-mini-card buyer-mini-card--quiet"><div><span>' . e((string)$card['kicker']) . '</span><h3>' . e((string)$card['title']) . '</h3><p>' . e((string)$card['body']) . '</p></div><a href="' . e((string)$card['url']) . '">' . e((string)$card['cta']) . '</a></article>';
        }
        echo '</section>';

        echo '<section class="buyer-panel buyer-quick-panel"><div class="buyer-panel__head"><div><span>Quick actions</span><h3>Move fast</h3></div></div><div class="buyer-quick-actions">';
        foreach ($data['quickActions'] as $action) {
            echo '<a href="' . e((string)$action['url']) . '">' . e((string)$action['label']) . '</a>';
        }
        echo '</div></section>';
        echo '</section>';
    }

    private static function buyerPanel(string $title, string $body, string $url, string $cta, callable $content): void
    {
        echo '<article class="buyer-panel"><div class="buyer-panel__head"><div><span>Buyer</span><h3>' . e($title) . '</h3><p>' . e($body) . '</p></div><a href="' . e($url) . '">' . e($cta) . '</a></div>';
        $content();
        echo '</article>';
    }

    private static function buyerVendorUpgradePanel(array $user): void
    {
        $vendor = self::vendorForCurrentUser();
        echo '<section class="buyer-panel buyer-upgrade-panel"><div class="buyer-panel__head"><div><span>Vendor application</span><h3>Apply to become a vendor</h3>';
        echo '<p>Use your existing account email, then complete the full vendor application, uploads, and KYC details for review.</p></div>';
        if ($vendor) {
            echo '<a href="' . e(app_url('vendor')) . '">Seller dashboard</a>';
        } else {
            echo '<a href="' . e(app_url('vendor/register')) . '">Apply as a vendor</a>';
        }
        echo '</div>';

        if ($vendor) {
            echo '<div class="buyer-upgrade-ready"><strong>' . e((string)$vendor['store_name']) . '</strong><span>Your seller account is already set up. Status: ' . e(ucfirst((string)$vendor['status'])) . '.</span></div>';
            echo '</section>';
            return;
        }

        echo '<div class="buyer-upgrade-ready"><strong>Application required</strong><span>Every seller must submit the complete vendor application before a store can be reviewed or activated.</span><a class="buyer-btn buyer-btn--gold" href="' . e(app_url('vendor/register')) . '">Start vendor application</a></div>';
        echo '</section>';
    }

    private static function buyerDashboardData(array $user): array
    {
        $userId = self::userId();
        $totalOrders = self::count('orders', 'customer_id = ?', [$userId]);
        $savedItems = self::count('wishlist_items', 'wishlist_id IN (SELECT id FROM wishlists WHERE user_id = ?)', [$userId]);
        $activeOrders = self::count('orders', 'customer_id = ? AND status IN ("pending", "confirmed", "processing", "on_hold", "paid", "partially_shipped", "shipped")', [$userId]);
        $referrals = self::count('affiliate_visits', 'affiliate_id IN (SELECT id FROM affiliates WHERE user_id = ?)', [$userId]);
        $coupons = self::activeCouponCount();
        $spent = self::sumOrders();
        $saved = self::sumOrderDiscounts();
        $rewardPoints = (int)max(0, floor($spent * 10) + ($totalOrders * 50) + ($savedItems * 5));
        $reviewCount = self::count('reviews', 'user_id = ?', [$userId]);
        $addressCount = self::count('addresses', 'user_id = ?', [$userId]);
        $paymentCount = self::count('payment_tokens', 'user_id = ?', [$userId]);
        $chatCount = self::count('chat_conversations', 'buyer_id = ?', [$userId]);
        $unreadMessages = self::buyerUnreadMessages();
        $loyaltyLevel = match (true) {
            $rewardPoints >= 2500 || $totalOrders >= 20 => 'Platinum Member',
            $rewardPoints >= 1000 || $totalOrders >= 8 => 'Gold Member',
            $rewardPoints >= 300 || $totalOrders >= 3 => 'Silver Member',
            default => 'Starter Member',
        };

        return [
            'loyaltyLevel' => $loyaltyLevel,
            'metrics' => [
                ['label' => 'Total Orders', 'value' => number_format($totalOrders), 'hint' => 'All-time purchases', 'url' => app_url('buyer/orders')],
                ['label' => 'Saved Items', 'value' => number_format($savedItems), 'hint' => 'Wishlist products', 'url' => app_url('buyer/wishlist')],
                ['label' => 'Active Orders', 'value' => number_format($activeOrders), 'hint' => 'In progress now', 'url' => app_url('track-order')],
                ['label' => 'Reward Points', 'value' => number_format($rewardPoints), 'hint' => $loyaltyLevel, 'url' => app_url('rewards-program')],
                ['label' => 'Referrals', 'value' => number_format($referrals), 'hint' => 'Invite activity', 'url' => app_url('affiliate/join')],
                ['label' => 'Coupons', 'value' => number_format($coupons), 'hint' => 'Available offers', 'url' => app_url('shop')],
            ],
            'orderSteps' => self::buyerOrderSteps($activeOrders),
            'recentOrders' => self::buyerRecentOrders(),
            'categories' => [
                ['label' => 'African Foods', 'url' => app_url('shop?category=food-groceries')],
                ['label' => 'Fashion', 'url' => app_url('shop?category=fashion')],
                ['label' => 'Beauty', 'url' => app_url('shop?category=beauty')],
                ['label' => 'Health', 'url' => app_url('shop?category=health')],
                ['label' => 'Spices', 'url' => app_url('shop?q=spices')],
                ['label' => 'Snacks', 'url' => app_url('shop?q=snacks')],
                ['label' => 'Home Essentials', 'url' => app_url('shop?q=home')],
            ],
            'featureCards' => [
                ['kicker' => 'Wishlist', 'title' => 'Saved for later', 'body' => 'Recently added items, price drops, and restock alerts live here.', 'value' => number_format($savedItems), 'cta' => 'Browse wishlist', 'url' => app_url('buyer/wishlist')],
                ['kicker' => 'Messages', 'title' => 'Seller conversations', 'body' => 'Chat with sellers, customer support, and follow order updates.', 'value' => number_format($unreadMessages) . ' unread', 'cta' => 'Open messages', 'url' => app_url('buyer/chats')],
                ['kicker' => 'Rewards', 'title' => 'Loyalty wallet', 'body' => 'Points, coupons, referral bonuses, and gift card opportunities.', 'value' => number_format($rewardPoints), 'cta' => 'Redeem rewards', 'url' => app_url('rewards-program')],
                ['kicker' => 'Addresses', 'title' => 'Delivery locations', 'body' => 'Keep home, work, and new shipping addresses ready for checkout.', 'value' => number_format($addressCount), 'cta' => 'Manage addresses', 'url' => app_url('buyer/addresses')],
                ['kicker' => 'Payments', 'title' => 'Checkout options', 'body' => 'Saved cards and supported digital payment methods for faster shopping.', 'value' => number_format($paymentCount), 'cta' => 'Manage profile', 'url' => app_url('buyer/profile')],
                ['kicker' => 'Reviews', 'title' => 'Share feedback', 'body' => 'Products waiting for reviews and your recent ratings.', 'value' => number_format($reviewCount), 'cta' => 'View orders', 'url' => app_url('buyer/orders')],
            ],
            'products' => self::buyerRecommendedProducts(),
            'insights' => [
                ['label' => 'Monthly Spending', 'value' => self::money(self::monthlySpend())],
                ['label' => 'Favorite Categories', 'value' => self::favoriteCategories()],
                ['label' => 'Orders This Year', 'value' => number_format(self::ordersThisYear())],
                ['label' => 'Coupon Savings', 'value' => self::money($saved)],
            ],
            'serviceCards' => [
                ['kicker' => 'Notifications', 'title' => 'Stay in the loop', 'body' => 'Flash sales, order updates, new products, weekly deals, and shipment alerts.', 'cta' => 'Account settings', 'url' => app_url('buyer/profile')],
                ['kicker' => 'Preorder & Restock', 'title' => 'Reserve early', 'body' => 'Watch arriving-soon products before they sell out.', 'cta' => 'Browse new arrivals', 'url' => app_url('shop?sort=newest')],
                ['kicker' => 'Refer & Earn', 'title' => 'Invite your community', 'body' => 'Share SAC Marketplace and earn rewards for successful signups and purchases.', 'cta' => 'Share referral link', 'url' => app_url('affiliate/join')],
                ['kicker' => 'Resources', 'title' => 'Shopping help', 'body' => 'Shipping information, returns, FAQs, and support when you need it.', 'cta' => 'Open help center', 'url' => app_url('knowledge-base')],
            ],
            'quickActions' => [
                ['label' => 'Browse Marketplace', 'url' => app_url('shop')],
                ['label' => 'View Cart', 'url' => app_url('cart')],
                ['label' => 'Wishlist', 'url' => app_url('buyer/wishlist')],
                ['label' => 'Track Orders', 'url' => app_url('track-order')],
                ['label' => 'Messages', 'url' => app_url('buyer/chats')],
                ['label' => 'Rewards', 'url' => app_url('rewards-program')],
                ['label' => 'Account Settings', 'url' => app_url('buyer/profile')],
                ['label' => 'Customer Support', 'url' => app_url('contact')],
                ['label' => 'Shipping Info', 'url' => app_url('shipping-delivery')],
                ['label' => 'Return Policy', 'url' => app_url('returns-refunds')],
                ['label' => 'FAQ', 'url' => app_url('faqs')],
            ],
        ];
    }

    private static function buyerOrderSteps(int $activeOrders): array
    {
        return array_map(static fn (string $label): array => ['label' => $label, 'active' => $activeOrders > 0], ['Processing', 'Packed', 'Shipped', 'Out for Delivery']);
    }

    private static function buyerRecentOrders(): array
    {
        $rows = self::buyerRows('dashboard');
        return array_slice($rows, 0, 4);
    }

    private static function activeCouponCount(): int
    {
        if (!table_exists('coupons')) {
            return 0;
        }
        return self::count('coupons', "status = 'active' AND (starts_at IS NULL OR starts_at <= NOW()) AND (expires_at IS NULL OR expires_at >= NOW())");
    }

    private static function sumOrderDiscounts(): float
    {
        try {
            $row = db()->fetch('SELECT COALESCE(SUM(discount_total), 0) total FROM orders WHERE customer_id = ?', [self::userId()]);
            return (float)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function monthlySpend(): float
    {
        try {
            $row = db()->fetch('SELECT COALESCE(SUM(grand_total), 0) total FROM orders WHERE customer_id = ? AND created_at >= DATE_FORMAT(NOW(), "%Y-%m-01")', [self::userId()]);
            return (float)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function ordersThisYear(): int
    {
        return self::count('orders', 'customer_id = ? AND YEAR(created_at) = YEAR(NOW())', [self::userId()]);
    }

    private static function favoriteCategories(): string
    {
        try {
            if (!table_exists('order_items') || !table_exists('product_categories') || !table_exists('categories')) {
                return 'Discovering';
            }
            $rows = db()->fetchAll(
                'SELECT c.name, COUNT(*) total
                 FROM orders o
                 INNER JOIN order_items oi ON oi.order_id = o.id
                 INNER JOIN product_categories pc ON pc.product_id = oi.product_id
                 INNER JOIN categories c ON c.id = pc.category_id
                 WHERE o.customer_id = ?
                 GROUP BY c.id, c.name
                 ORDER BY total DESC, c.name ASC
                 LIMIT 2',
                [self::userId()]
            );
            $names = array_map(static fn (array $row): string => (string)$row['name'], $rows);
            return $names !== [] ? implode(', ', $names) : 'Discovering';
        } catch (\Throwable) {
            return 'Discovering';
        }
    }

    private static function buyerUnreadMessages(): int
    {
        try {
            if (!table_exists('chat_messages')) {
                return 0;
            }
            $row = db()->fetch(
                'SELECT COUNT(*) c
                 FROM chat_messages cm
                 INNER JOIN chat_conversations cc ON cc.id = cm.conversation_id
                 WHERE cc.buyer_id = ? AND cm.sender_type <> "buyer"',
                [self::userId()]
            );
            return (int)($row['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function buyerRecommendedProducts(): array
    {
        try {
            $rows = db()->fetchAll(
                "SELECT p.name, p.slug, p.regular_price, p.sale_price, p.currency
                 FROM products p
                 WHERE p.status = 'active'
                   AND p.slug IS NOT NULL
                   AND p.slug <> ''
                 ORDER BY p.updated_at DESC, p.id DESC
                 LIMIT 5"
            );
        } catch (\Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            return [
                ['name' => 'African pantry essentials', 'price' => 'Browse now', 'initial' => 'A', 'url' => app_url('shop?category=food-groceries')],
                ['name' => 'Beauty and wellness picks', 'price' => 'Explore', 'initial' => 'B', 'url' => app_url('shop?category=beauty')],
                ['name' => 'Seasonal marketplace favorites', 'price' => 'See deals', 'initial' => 'S', 'url' => app_url('shop')],
            ];
        }

        return array_map(static function (array $row): array {
            $name = (string)$row['name'];
            $price = (float)($row['sale_price'] ?: $row['regular_price']);
            $currency = (string)($row['currency'] ?: 'USD');
            return [
                'name' => $name,
                'price' => self::money($price) . ' ' . $currency,
                'initial' => strtoupper(substr($name, 0, 1)) ?: 'S',
                'url' => app_url('product/' . rawurlencode((string)$row['slug'])),
            ];
        }, $rows);
    }

    private static function vendorForCurrentUser(): ?array
    {
        try {
            $row = db()->fetch('SELECT id, store_name, status, kyc_status FROM vendors WHERE user_id = ? LIMIT 1', [self::userId()]);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function handleBuyerVendorUpgrade(): void
    {
        validateCsrf();
        flash('info', 'Please complete the full vendor application so our team can review your business and KYC details.');
        redirect('vendor/register');
    }

    private static function assignRole(int $userId, string $roleCode): void
    {
        $role = db()->fetch('SELECT id FROM roles WHERE code = ? LIMIT 1', [$roleCode]);
        if (!$role) {
            throw new \RuntimeException('Required role is missing: ' . $roleCode);
        }
        $roleId = (int)$role['id'];
        $exists = db()->fetch('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1', [$userId, $roleId]);
        if (!$exists) {
            db()->query('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
        }
    }

    private static function uniqueVendorSlug(string $storeName): string
    {
        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $storeName), '-'));
        $base = substr($base !== '' ? $base : 'store-' . self::userId(), 0, 80);
        $slug = $base;
        $counter = 2;
        while (db()->fetch('SELECT id FROM vendors WHERE store_slug = ? LIMIT 1', [$slug])) {
            $suffix = '-' . $counter++;
            $slug = substr($base, 0, 80 - strlen($suffix)) . $suffix;
        }
        return $slug;
    }

    private static function accountWorkspace(string $portal, string $active): void
    {
        echo '<section class="portal-panel"><div class="portal-panel-head"><div><p>' . e(ucfirst($portal)) . '</p><h3>Profile</h3></div></div>';
        self::accountForms($active);
        echo '</section>';
    }

    private static function accountForms(string $active): void
    {
        $user = self::accountUser();
        echo '<div class="portal-account-grid">';
        echo '<form class="vendor-form" method="post">';
        echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '"><input type="hidden" name="intent" value="save_profile">';
        echo '<div class="vendor-form-row">';
        echo '<label class="vendor-field"><span>First name</span><input name="first_name" value="' . e((string)($user['first_name'] ?? '')) . '"></label>';
        echo '<label class="vendor-field"><span>Last name</span><input name="last_name" value="' . e((string)($user['last_name'] ?? '')) . '"></label>';
        echo '<label class="vendor-field"><span>Display name</span><input name="display_name" value="' . e((string)($user['display_name'] ?? '')) . '"></label>';
        echo '</div>';
        echo '<div class="vendor-form-row">';
        echo '<label class="vendor-field"><span>Username</span><input name="username" value="' . e((string)($user['username'] ?? '')) . '"></label>';
        echo '<label class="vendor-field"><span>Phone</span><input name="phone" value="' . e((string)($user['phone'] ?? '')) . '"></label>';
        echo '<label class="vendor-field"><span>Email</span><input value="' . e((string)($user['email'] ?? '')) . '" disabled></label>';
        echo '</div>';
        echo '<button class="portal-action" type="submit">Save profile</button></form>';

        echo '<form class="vendor-form" method="post">';
        echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '"><input type="hidden" name="intent" value="change_password">';
        echo '<div class="vendor-form-row">';
        echo '<label class="vendor-field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>';
        echo '<label class="vendor-field"><span>New password</span><input type="password" name="new_password" required minlength="5" autocomplete="new-password"></label>';
        echo '<label class="vendor-field"><span>Confirm password</span><input type="password" name="new_password_confirmation" required minlength="5" autocomplete="new-password"></label>';
        echo '</div>';
        echo '<button class="portal-action" type="submit">Change password</button></form>';
        echo '</div>';
    }

    private static function accountUser(): array
    {
        try {
            $row = db()->fetch(
                'SELECT id, email, username, first_name, last_name, display_name, phone, password_hash, created_at FROM users WHERE id = ? LIMIT 1',
                [self::userId()]
            );
            return $row ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function handleAccountPost(string $portal, string $active): void
    {
        validateCsrf();
        $intent = (string)($_POST['intent'] ?? '');
        $return = $portal === 'buyer' ? 'buyer/profile' : ($active === 'settings' ? $portal . '/settings' : $portal . '/profile');

        try {
            if ($intent === 'save_profile') {
                $username = trim((string)($_POST['username'] ?? ''));
                if ($username !== '') {
                    $duplicate = db()->fetch('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1', [$username, self::userId()]);
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
                        self::userId(),
                    ]
                );
                $_SESSION['display_name'] = trim((string)($_POST['display_name'] ?? '')) ?: ($_SESSION['display_name'] ?? 'Account');
                audit('profile.updated', 'users', (string)self::userId(), [], ['portal' => $portal], self::userId());
                flash('success', 'Profile saved.');
            } elseif ($intent === 'change_password') {
                $user = self::accountUser();
                $current = (string)($_POST['current_password'] ?? '');
                if (!$user || !password_verify($current, (string)($user['password_hash'] ?? ''))) {
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
                    [SecurityService::passwordHash($new), sql_now(), sql_now(), sql_now(), self::userId()]
                );
                audit('password.changed', 'users', (string)self::userId(), [], ['portal' => $portal], self::userId());
                flash('success', 'Password changed.');
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect($return);
    }

    private static function simpleError(string $title, string $message): string
    {
        if (function_exists('render_restricted_page')) {
            render_restricted_page(403, $title, $message, ['reason' => 'role_dashboard_simple_error']);
            return '';
        }

        return '<!doctype html><title>' . e($title) . '</title><div style="font-family:system-ui;padding:48px"><h1>' . e($title) . '</h1><p>' . e($message) . '</p></div>';
    }

    private static function count(string $table, string $where = '1=1', array $params = []): int
    {
        try {
            $row = db()->fetch("SELECT COUNT(*) c FROM {$table} WHERE {$where}", $params);
            return (int)($row['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function userId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    private static function money(float $amount): string
    {
        return '$' . number_format($amount, $amount > 999 ? 0 : 2);
    }

    private static function sumOrders(): float
    {
        try {
            $row = db()->fetch('SELECT COALESCE(SUM(grand_total), 0) total FROM orders WHERE customer_id = ?', [self::userId()]);
            return (float)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function countVendorOrders(string $status): int
    {
        try {
            $row = db()->fetch(
                "SELECT COUNT(*) total
                 FROM order_vendor_splits ovs
                 INNER JOIN orders o ON o.id = ovs.order_id
                 WHERE ovs.vendor_id IN (SELECT id FROM vendors WHERE user_id = ?)
                   AND ovs.status = ?
                   AND o.payment_status = 'paid'",
                [self::userId(), $status]
            );
            return (int)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function sumVendorSplits(string $field): float
    {
        try {
            $row = db()->fetch(
                "SELECT COALESCE(SUM(ovs.{$field}), 0) total
                 FROM order_vendor_splits ovs
                 INNER JOIN orders o ON o.id = ovs.order_id
                 WHERE ovs.vendor_id IN (SELECT id FROM vendors WHERE user_id = ?)
                   AND o.payment_status = 'paid'",
                [self::userId()]
            );
            return (float)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function sumVendorWithdrawals(): float
    {
        try {
            $row = db()->fetch('SELECT COALESCE(SUM(amount), 0) total FROM vendor_withdrawals WHERE vendor_id IN (SELECT id FROM vendors WHERE user_id = ?)', [self::userId()]);
            return (float)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function affiliateField(string $field): string
    {
        try {
            $row = db()->fetch("SELECT {$field} FROM affiliates WHERE user_id = ? LIMIT 1", [self::userId()]);
            return (string)($row[$field] ?? '0');
        } catch (\Throwable) {
            return '0';
        }
    }

    private static function affiliate(): array
    {
        try {
            $row = db()->fetch('SELECT * FROM affiliates WHERE user_id = ? LIMIT 1', [self::userId()]);
            return $row ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function affiliateId(): int
    {
        return (int)(self::affiliate()['id'] ?? 0);
    }

    private static function handleAffiliatePost(string $active): void
    {
        validateCsrf();
        $affiliateId = self::affiliateId();
        if ($affiliateId <= 0) {
            flash('error', 'Affiliate profile was not found.');
            redirect($active === 'dashboard' ? 'affiliate' : 'affiliate/' . $active);
        }

        $intent = (string)($_POST['intent'] ?? '');
        try {
            if ($intent === 'create_link') {
                $target = trim((string)($_POST['target_url'] ?? ''));
                $productId = max(0, (int)($_POST['product_id'] ?? 0));
                if ($productId > 0) {
                    $product = db()->fetch(
                        "SELECT slug FROM products WHERE id = ? AND status = 'active' AND slug IS NOT NULL AND slug <> '' LIMIT 1",
                        [$productId]
                    );
                    if (!$product) {
                        throw new \RuntimeException('Select an active product or enter a URL.');
                    }
                    $target = app_url('product/' . rawurlencode((string)$product['slug']));
                }
                if ($target === '') {
                    $target = app_url('store');
                }
                if (!filter_var($target, FILTER_VALIDATE_URL)) {
                    throw new \RuntimeException('Enter a valid target URL.');
                }
                $token = 'aff-' . $affiliateId . '-' . bin2hex(random_bytes(4));
                db()->query(
                    'INSERT INTO affiliate_links (affiliate_id, target_url, token, created_at) VALUES (?, ?, ?, ?)',
                    [$affiliateId, $target, $token, sql_now()]
                );
                flash('success', 'Referral link created.');
            } elseif ($intent === 'request_payout') {
                $amount = max(0, (float)($_POST['amount'] ?? 0));
                $available = (float)self::affiliateField('unpaid_earnings');
                if ($amount <= 0 || $amount > max(0, $available)) {
                    throw new \RuntimeException('Enter a payout amount within your unpaid earnings.');
                }
                db()->query(
                    'INSERT INTO affiliate_payouts (affiliate_id, amount, currency, payment_method, status, requested_at, created_at) VALUES (?, ?, "USD", ?, "pending", ?, ?)',
                    [$affiliateId, $amount, (string)($_POST['payment_method'] ?? 'bank_transfer'), sql_now(), sql_now()]
                );
                flash('success', 'Payout request submitted.');
            } elseif ($intent === 'save_settings') {
                $paymentEmail = strtolower(trim((string)($_POST['payment_email'] ?? '')));
                if (!filter_var($paymentEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Enter a valid payment email.');
                }
                db()->query('UPDATE affiliates SET payment_email = ? WHERE id = ?', [$paymentEmail, $affiliateId]);
                flash('success', 'Affiliate settings saved.');
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect($active === 'dashboard' ? 'affiliate' : 'affiliate/' . $active);
    }

    private static function affiliateRows(string $active): array
    {
        $affiliateId = self::affiliateId();
        if ($affiliateId <= 0) {
            return [];
        }

        try {
            return match ($active) {
                'referrals' => array_map(static fn (array $row): array => [
                    'title' => (string)$row['target_url'],
                    'meta' => AffiliateService::referralUrl((string)$row['target_url'], (string)$row['token']) . ' - ' . number_format((int)$row['clicks_count']) . ' clicks',
                    'status' => 'Link',
                ], db()->fetchAll('SELECT target_url, token, clicks_count, created_at FROM affiliate_links WHERE affiliate_id = ? ORDER BY id DESC LIMIT 25', [$affiliateId])),
                'commissions' => array_map(static fn (array $row): array => [
                    'title' => 'Commission #' . (int)$row['id'] . ' - ' . self::money((float)$row['amount']),
                    'meta' => (string)($row['description'] ?: $row['reference'] ?: 'Order referral'),
                    'status' => (string)$row['status'],
                ], db()->fetchAll('SELECT id, amount, status, description, reference, created_at FROM affiliate_referrals WHERE affiliate_id = ? ORDER BY id DESC LIMIT 25', [$affiliateId])),
                'payouts' => array_map(static fn (array $row): array => [
                    'title' => 'Payout #' . (int)$row['id'] . ' - ' . self::money((float)$row['amount']),
                    'meta' => (string)($row['payment_method'] ?: 'Manual payout') . ' - ' . (string)($row['requested_at'] ?: $row['created_at']),
                    'status' => (string)$row['status'],
                ], db()->fetchAll('SELECT id, amount, payment_method, status, requested_at, created_at FROM affiliate_payouts WHERE affiliate_id = ? ORDER BY id DESC LIMIT 25', [$affiliateId])),
                'creatives' => array_map(static fn (array $row): array => [
                    'title' => (string)$row['name'],
                    'meta' => (string)($row['text_content'] ?: $row['target_url'] ?: 'Creative asset'),
                    'status' => (string)$row['type'],
                ], db()->fetchAll('SELECT name, type, target_url, text_content, status FROM affiliate_creatives WHERE status = "active" ORDER BY id DESC LIMIT 25')),
                'settings' => [[
                    'title' => 'Payment email',
                    'meta' => (string)(self::affiliate()['payment_email'] ?? 'Not set'),
                    'status' => 'Editable',
                ]],
                default => array_map(static fn (array $row): array => [
                    'title' => (string)$row['target_url'],
                    'meta' => AffiliateService::referralUrl((string)$row['target_url'], (string)$row['token']),
                    'status' => number_format((int)$row['clicks_count']) . ' clicks',
                ], db()->fetchAll('SELECT target_url, token, clicks_count FROM affiliate_links WHERE affiliate_id = ? ORDER BY id DESC LIMIT 5', [$affiliateId])),
            };
        } catch (\Throwable) {
            return [];
        }
    }

    private static function toasts(): void
    {
        $toasts = consume_toasts();
        if ($toasts === []) {
            return;
        }

        echo '<div class="vendor-toasts">';
        foreach ($toasts as $toast) {
            echo '<div class="vendor-toast is-' . e((string)($toast['type'] ?? 'info')) . '">' . e((string)($toast['message'] ?? '')) . '</div>';
        }
        echo '</div>';
    }

    private static function rowTitle(string $portal, string $active, int $index): string
    {
        return ucfirst($portal) . ' ' . ucwords(str_replace('-', ' ', $active)) . ' Item ' . $index;
    }

    private static function assetVersion(string $type = 'css'): string
    {
        $file = APP_ROOT . '/public/assets/' . $type . '/role-dashboard.' . $type;
        return is_file($file) ? (string)filemtime($file) : '1';
    }
}
