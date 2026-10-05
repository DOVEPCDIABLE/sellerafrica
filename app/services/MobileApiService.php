<?php

declare(strict_types=1);

namespace App;

final class MobileApiService
{
    private const TOKEN_TTL_DAYS = 90;

    public static function dispatch(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            self::json(['ok' => true]);
        }

        try {
            self::ensureTokenTable();
            self::ensureCheckoutSessionTable();

            $route = trim((string)($_GET['route'] ?? ''), '/');
            $path = trim(preg_replace('#^api/mobile(?:/v1)?/?#', '', $route) ?? '', '/');
            if ($path === '') {
                $path = trim((string)($_GET['endpoint'] ?? ''), '/');
            }

            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $input = self::input();

            if ($path === 'v1' || $path === '') {
                self::json(['ok' => true, 'name' => 'Seller Africa Mobile API', 'version' => 'v1']);
            }

            if ($path === 'v1/health' || $path === 'health') {
                self::json(['ok' => true, 'time' => sql_now()]);
            }

            $path = preg_replace('#^v1/#', '', $path) ?? $path;

            if ($method === 'POST' && $path === 'auth/login') {
                self::login($input);
            }

            if ($method === 'POST' && $path === 'auth/register') {
                self::register($input);
            }

            if ($method === 'POST' && $path === 'auth/forgot-password') {
                self::forgotPassword($input);
            }

            if ($method === 'POST' && $path === 'checkout/session') {
                self::createCheckoutSession($input);
            }

            self::routeCatalog($method, $path, $input);
            self::routeCart($method, $path, $input);

            $user = self::currentUser();

            if ($method === 'GET' && $path === 'auth/me') {
                self::json(['ok' => true, 'user' => $user, 'vendor' => self::vendorForUser((int)$user['id'])]);
            }

            if ($method === 'POST' && $path === 'auth/logout') {
                self::logout((int)$user['id']);
            }

            self::routeBuyer($method, $path, $input, $user);
            self::routeVendor($method, $path, $input, $user);

            self::json(['ok' => false, 'message' => 'Endpoint not found.'], 404);
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            self::json(['ok' => false, 'message' => $status === 500 ? 'Request failed.' : $e->getMessage()], $status);
        }
    }

    private static function routeCatalog(string $method, string $path, array $input): void
    {
        if ($method === 'GET' && $path === 'catalog/home') {
            self::json([
                'ok' => true,
                'banners' => self::appBanners(),
                'featured' => self::products(['featured' => true, 'limit' => 12, 'sort' => 'weekly']),
                'latest' => self::products(['limit' => 12, 'sort' => 'latest']),
                'categories' => self::categories(),
                'vendors' => self::vendors(8, 0),
            ]);
        }

        if ($method === 'GET' && $path === 'catalog/banners') {
            self::json(['ok' => true, 'banners' => self::appBanners()]);
        }

        if ($method === 'GET' && $path === 'catalog/products') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = self::limit($_GET['limit'] ?? 20, 1, 50);
            self::json([
                'ok' => true,
                'page' => $page,
                'limit' => $limit,
                'products' => self::products([
                    'query' => trim((string)($_GET['q'] ?? '')),
                    'category' => trim((string)($_GET['category'] ?? '')),
                    'vendor_id' => (int)($_GET['vendor_id'] ?? 0),
                    'limit' => $limit,
                    'offset' => ($page - 1) * $limit,
                    'sort' => trim((string)($_GET['sort'] ?? 'weekly')),
                ]),
            ]);
        }

        if ($method === 'GET' && preg_match('#^catalog/products/(\d+)$#', $path, $m)) {
            $product = self::product((int)$m[1], true);
            if (!$product) {
                self::json(['ok' => false, 'message' => 'Product not found.'], 404);
            }
            self::json(['ok' => true, 'product' => $product]);
        }

        if ($method === 'GET' && $path === 'catalog/categories') {
            self::json(['ok' => true, 'categories' => self::categories()]);
        }

        if ($method === 'GET' && $path === 'catalog/vendors') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = self::limit($_GET['limit'] ?? 20, 1, 50);
            self::json(['ok' => true, 'page' => $page, 'limit' => $limit, 'vendors' => self::vendors($limit, ($page - 1) * $limit)]);
        }

        if ($method === 'GET' && preg_match('#^catalog/vendors/(\d+)/products$#', $path, $m)) {
            self::json(['ok' => true, 'products' => self::products(['vendor_id' => (int)$m[1], 'limit' => self::limit($_GET['limit'] ?? 20, 1, 50)])]);
        }
    }

    private static function routeCart(string $method, string $path, array $input): void
    {
        if ($method === 'GET' && $path === 'cart') {
            self::json(['ok' => true, 'cart' => self::cartPayload()]);
        }

        if ($method === 'POST' && $path === 'cart/items') {
            (new CartService())->add((int)($input['product_id'] ?? 0), (int)($input['quantity'] ?? 1));
            self::json(['ok' => true, 'cart' => self::cartPayload()]);
        }

        if (in_array($method, ['PATCH', 'PUT'], true) && preg_match('#^cart/items/(\d+)$#', $path, $m)) {
            (new CartService())->set((int)$m[1], (int)($input['quantity'] ?? 0));
            self::json(['ok' => true, 'cart' => self::cartPayload()]);
        }

        if ($method === 'DELETE' && preg_match('#^cart/items/(\d+)$#', $path, $m)) {
            (new CartService())->remove((int)$m[1]);
            self::json(['ok' => true, 'cart' => self::cartPayload()]);
        }
    }

    private static function routeBuyer(string $method, string $path, array $input, array $user): void
    {
        $userId = (int)$user['id'];

        if ($method === 'GET' && $path === 'buyer/profile') {
            self::json(['ok' => true, 'profile' => self::publicUser($user)]);
        }

        if (in_array($method, ['PATCH', 'PUT'], true) && $path === 'buyer/profile') {
            self::updateProfile($userId, $input);
        }

        if ($method === 'GET' && $path === 'buyer/addresses') {
            self::json(['ok' => true, 'addresses' => self::addresses($userId)]);
        }

        if ($method === 'POST' && $path === 'buyer/addresses') {
            self::saveAddress($userId, $input);
        }

        if (in_array($method, ['PATCH', 'PUT'], true) && preg_match('#^buyer/addresses/(\d+)$#', $path, $m)) {
            self::saveAddress($userId, $input, (int)$m[1]);
        }

        if ($method === 'DELETE' && preg_match('#^buyer/addresses/(\d+)$#', $path, $m)) {
            db()->query('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int)$m[1], $userId]);
            self::json(['ok' => true]);
        }

        if ($method === 'GET' && $path === 'buyer/orders') {
            self::json(['ok' => true, 'orders' => self::buyerOrders($userId)]);
        }

        if ($method === 'GET' && preg_match('#^buyer/orders/(\d+)$#', $path, $m)) {
            $order = self::buyerOrder($userId, (int)$m[1]);
            if (!$order) {
                self::json(['ok' => false, 'message' => 'Order not found.'], 404);
            }
            self::json(['ok' => true, 'order' => $order]);
        }

        if ($method === 'GET' && $path === 'buyer/wishlist') {
            self::json(['ok' => true, 'wishlist' => self::wishlist($userId)]);
        }

        if ($method === 'POST' && preg_match('#^buyer/wishlist/(\d+)$#', $path, $m)) {
            self::wishlistAdd($userId, (int)$m[1]);
        }

        if ($method === 'DELETE' && preg_match('#^buyer/wishlist/(\d+)$#', $path, $m)) {
            $wishlistId = self::defaultWishlistId($userId);
            db()->query('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wishlistId, (int)$m[1]]);
            self::json(['ok' => true, 'wishlist' => self::wishlist($userId)]);
        }

        if ($method === 'POST' && $path === 'buyer/reviews') {
            self::createReview($userId, $input);
        }
    }

    private static function routeVendor(string $method, string $path, array $input, array $user): void
    {
        if (!str_starts_with($path, 'vendor/')) {
            return;
        }

        $vendor = self::requireVendor((int)$user['id']);
        $vendorId = (int)$vendor['id'];

        if ($method === 'GET' && $path === 'vendor/dashboard') {
            self::json(['ok' => true, 'vendor' => self::vendorPayload($vendor), 'metrics' => self::vendorMetrics($vendorId)]);
        }

        if ($method === 'GET' && $path === 'vendor/store') {
            self::json(['ok' => true, 'store' => self::vendorPayload($vendor)]);
        }

        if (in_array($method, ['PATCH', 'PUT'], true) && $path === 'vendor/store') {
            self::updateVendorStore($vendorId, (int)$user['id'], $input);
        }

        if ($method === 'GET' && $path === 'vendor/products') {
            self::json(['ok' => true, 'products' => self::vendorProducts($vendorId)]);
        }

        if ($method === 'POST' && $path === 'vendor/products') {
            self::saveVendorProduct($vendorId, (int)$user['id'], $input);
        }

        if (in_array($method, ['PATCH', 'PUT'], true) && preg_match('#^vendor/products/(\d+)$#', $path, $m)) {
            self::saveVendorProduct($vendorId, (int)$user['id'], $input, (int)$m[1]);
        }

        if ($method === 'DELETE' && preg_match('#^vendor/products/(\d+)$#', $path, $m)) {
            db()->query('UPDATE products SET status = "archived", visibility = "hidden", updated_at = ? WHERE id = ? AND vendor_id = ?', [sql_now(), (int)$m[1], $vendorId]);
            self::json(['ok' => true]);
        }

        if ($method === 'GET' && $path === 'vendor/orders') {
            self::json(['ok' => true, 'orders' => self::vendorOrders($vendorId)]);
        }

        if (in_array($method, ['PATCH', 'PUT'], true) && preg_match('#^vendor/orders/(\d+)$#', $path, $m)) {
            self::updateVendorOrder($vendorId, (int)$m[1], $input);
        }

        if ($method === 'GET' && $path === 'vendor/reviews') {
            self::json(['ok' => true, 'reviews' => self::vendorReviews($vendorId)]);
        }

        if ($method === 'GET' && $path === 'vendor/kyc') {
            self::json(['ok' => true, 'documents' => self::vendorKycDocuments($vendorId)]);
        }

        if ($method === 'POST' && $path === 'vendor/kyc') {
            self::submitVendorKyc($vendorId, (int)$user['id'], $input);
        }

        if ($method === 'GET' && $path === 'vendor/payouts') {
            self::json(['ok' => true, 'available_balance' => self::availableBalance($vendorId), 'payouts' => self::vendorPayouts($vendorId)]);
        }

        if ($method === 'POST' && $path === 'vendor/payouts') {
            self::requestPayout($vendorId, $input);
        }
    }

    private static function login(array $input): void
    {
        $email = strtolower(trim((string)($input['email'] ?? $input['username'] ?? '')));
        $password = (string)($input['password'] ?? '');
        if ($email === '' || $password === '') {
            self::json(['ok' => false, 'message' => 'Email and password are required.'], 422);
        }

        $user = db()->fetch(
            "SELECT id, email, username, password_hash, first_name, last_name, display_name, phone, avatar_url, status, email_verified_at, wp_user_id
             FROM users
             WHERE email = ? OR username = ?
             LIMIT 1",
            [$email, $email]
        );

        if (!$user || !in_array((string)$user['status'], ['active', 'pending'], true) || !self::verifyPassword($password, (string)$user['password_hash'])) {
            self::json(['ok' => false, 'message' => 'Invalid login details.'], 401);
        }

        $token = bin2hex(random_bytes(32));
        db()->query(
            'INSERT INTO mobile_api_tokens (user_id, token_hash, device_name, expires_at, created_at) VALUES (?, ?, ?, DATE_ADD(?, INTERVAL ' . self::TOKEN_TTL_DAYS . ' DAY), ?)',
            [(int)$user['id'], hash('sha256', $token), trim((string)($input['device_name'] ?? 'Flutter app')), sql_now(), sql_now()]
        );
        db()->query('UPDATE users SET last_login_at = ? WHERE id = ?', [sql_now(), (int)$user['id']]);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
        $_SESSION['roles'] = self::roleCodes((int)$user['id']);

        self::json([
            'ok' => true,
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in_days' => self::TOKEN_TTL_DAYS,
            'user' => self::publicUser($user),
            'roles' => $_SESSION['roles'],
            'vendor' => self::vendorForUser((int)$user['id']),
        ]);
    }

    private static function register(array $input): void
    {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        $displayName = trim((string)($input['display_name'] ?? trim((string)($input['first_name'] ?? '') . ' ' . (string)($input['last_name'] ?? ''))));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            self::json(['ok' => false, 'message' => 'A valid email and password of at least 8 characters are required.'], 422);
        }
        if (db()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            self::json(['ok' => false, 'message' => 'An account already exists with this email.'], 409);
        }

        $username = self::uniqueUsername($email);
        db()->query(
            'INSERT INTO users (email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, "active", ?, ?, ?)',
            [$email, $username, password_hash($password, PASSWORD_DEFAULT), trim((string)($input['first_name'] ?? '')), trim((string)($input['last_name'] ?? '')), $displayName !== '' ? $displayName : $username, trim((string)($input['phone'] ?? '')), sql_now(), sql_now(), sql_now()]
        );
        $userId = (int)db()->lastInsertId();
        self::assignRole($userId, 'customer');

        if (($input['account_type'] ?? '') === 'vendor') {
            $storeName = trim((string)($input['store_name'] ?? $displayName));
            if ($storeName !== '') {
                db()->query(
                    'INSERT INTO vendors (user_id, store_name, store_slug, store_email, store_phone, description, status, kyc_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, "pending", "not_started", ?, ?)',
                    [$userId, $storeName, self::uniqueSlug('vendors', 'store_slug', $storeName), $email, trim((string)($input['phone'] ?? '')), trim((string)($input['store_description'] ?? '')), sql_now(), sql_now()]
                );
                self::assignRole($userId, 'vendor');
            }
        }

        self::json(['ok' => true, 'message' => 'Account created. Sign in to continue.'], 201);
    }

    private static function forgotPassword(array $input): void
    {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::json(['ok' => false, 'message' => 'A valid email address is required.'], 422);
        }

        $user = db()->fetch('SELECT id, email, username, display_name FROM users WHERE email = ? AND status = "active" LIMIT 1', [$email]);
        if ($user) {
            $token = SecurityService::createPasswordResetToken((int)$user['id']);
            NotificationService::passwordReset($user, app_url('reset-password?token=' . urlencode($token)));
        }

        self::json(['ok' => true, 'message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    private static function logout(int $userId): void
    {
        $token = self::bearerToken();
        if ($token !== '') {
            db()->query('UPDATE mobile_api_tokens SET revoked_at = ? WHERE user_id = ? AND token_hash = ?', [sql_now(), $userId, hash('sha256', $token)]);
        }
        session_destroy();
        self::json(['ok' => true]);
    }

    private static function currentUser(): array
    {
        $token = self::bearerToken();
        if ($token !== '') {
            $row = db()->fetch(
                "SELECT u.*
                 FROM mobile_api_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = ? AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at > ?) AND u.status = 'active'
                 LIMIT 1",
                [hash('sha256', $token), sql_now()]
            );
            if ($row) {
                db()->query('UPDATE mobile_api_tokens SET last_used_at = ? WHERE token_hash = ?', [sql_now(), hash('sha256', $token)]);
                return array_merge(self::publicUser($row), ['roles' => self::roleCodes((int)$row['id'])]);
            }
        }

        if (isset($_SESSION['user_id'])) {
            $row = db()->fetch('SELECT * FROM users WHERE id = ? AND status = "active" LIMIT 1', [(int)$_SESSION['user_id']]);
            if ($row) {
                return array_merge(self::publicUser($row), ['roles' => self::roleCodes((int)$row['id'])]);
            }
        }

        self::json(['ok' => false, 'message' => 'Authentication required.'], 401);
    }

    private static function optionalCurrentUser(): ?array
    {
        $token = self::bearerToken();
        if ($token === '') {
            return null;
        }

        $row = db()->fetch(
            "SELECT u.*
             FROM mobile_api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at > ?) AND u.status = 'active'
             LIMIT 1",
            [hash('sha256', $token), sql_now()]
        );
        if (!$row) {
            return null;
        }

        db()->query('UPDATE mobile_api_tokens SET last_used_at = ? WHERE token_hash = ?', [sql_now(), hash('sha256', $token)]);
        return array_merge(self::publicUser($row), ['roles' => self::roleCodes((int)$row['id'])]);
    }

    private static function products(array $filters): array
    {
        $where = ["p.status = 'active'", "p.visibility <> 'hidden'"];
        $params = [];
        if (($filters['featured'] ?? false) === true) {
            $where[] = 'p.featured = 1';
        }
        if ((int)($filters['vendor_id'] ?? 0) > 0) {
            $where[] = 'p.vendor_id = ?';
            $params[] = (int)$filters['vendor_id'];
        }
        if (trim((string)($filters['query'] ?? '')) !== '') {
            $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)';
            $q = '%' . trim((string)$filters['query']) . '%';
            array_push($params, $q, $q, $q);
        }
        if (trim((string)($filters['category'] ?? '')) !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM product_categories pc INNER JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id AND (c.slug = ? OR c.name = ?))';
            $params[] = trim((string)$filters['category']);
            $params[] = trim((string)$filters['category']);
        }

        $sort = match ((string)($filters['sort'] ?? 'latest')) {
            'price_asc' => 'COALESCE(p.sale_price, p.regular_price) ASC, p.id DESC',
            'price_desc' => 'COALESCE(p.sale_price, p.regular_price) DESC, p.id DESC',
            'popular' => 'p.total_sales DESC, p.id DESC',
            'weekly' => self::weeklyProductRotationSql('p.id') . ' ASC, p.total_sales DESC, p.created_at DESC, p.id DESC',
            default => 'p.created_at DESC, p.id DESC',
        };
        $limit = self::limit($filters['limit'] ?? 20, 1, 50);
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $rows = db()->fetchAll(
            "SELECT p.*, v.store_name, v.store_slug, f.path image_path
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2 WHERE pm2.product_id = p.id ORDER BY pm2.role = 'primary' DESC, pm2.sort_order ASC, pm2.id ASC LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$sort}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return array_map([self::class, 'productPayload'], $rows);
    }

    private static function product(int $id, bool $includeDetails = false): ?array
    {
        $row = db()->fetch(
            "SELECT p.*, v.store_name, v.store_slug, f.path image_path
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2 WHERE pm2.product_id = p.id ORDER BY pm2.role = 'primary' DESC, pm2.sort_order ASC, pm2.id ASC LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE p.id = ? AND p.status = 'active'
             LIMIT 1",
            [$id]
        );
        if (!$row) {
            return null;
        }
        $payload = self::productPayload($row);
        if ($includeDetails) {
            $payload['description'] = (string)($row['description'] ?? '');
            $payload['gallery'] = self::productGallery($id);
            $payload['reviews'] = db()->fetchAll(
                "SELECT r.id, r.rating, r.title, r.body, r.created_at, COALESCE(NULLIF(u.display_name, ''), u.email, 'Customer') author
                 FROM reviews r
                 LEFT JOIN users u ON u.id = r.user_id
                 WHERE r.product_id = ? AND r.status = 'approved'
                 ORDER BY r.created_at DESC
                 LIMIT 20",
                [$id]
            );
        }
        return $payload;
    }

    private static function weeklyProductRotationSql(string $idExpression): string
    {
        $daySeed = date('Ymd') . '-marketplace-refresh-2';

        return "CRC32(CONCAT({$idExpression}, '-{$daySeed}'))";
    }

    private static function productPayload(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'vendor_id' => isset($row['vendor_id']) ? (int)$row['vendor_id'] : null,
            'vendor_name' => $row['store_name'] ?? null,
            'name' => (string)$row['name'],
            'slug' => (string)$row['slug'],
            'sku' => (string)$row['sku'],
            'short_description' => (string)($row['short_description'] ?? ''),
            'status' => (string)$row['status'],
            'currency' => (string)$row['currency'],
            'regular_price' => (float)$row['regular_price'],
            'sale_price' => $row['sale_price'] !== null ? (float)$row['sale_price'] : null,
            'price' => (float)($row['sale_price'] ?? $row['regular_price']),
            'stock_status' => (string)$row['stock_status'],
            'stock_quantity' => $row['stock_quantity'] !== null ? (int)$row['stock_quantity'] : null,
            'weight' => isset($row['weight']) ? (float)$row['weight'] : 0.0,
            'average_rating' => (float)$row['average_rating'],
            'review_count' => (int)$row['review_count'],
            'image_url' => self::fileUrl((string)($row['image_path'] ?? '')),
            'url' => app_url('product/' . (string)$row['slug']),
        ];
    }

    private static function categories(): array
    {
        $rows = db()->fetchAll(
            "SELECT c.id, c.name, c.slug, c.parent_id, cf.path image_path, COUNT(p.id) product_count
             FROM categories c
             LEFT JOIN files cf ON cf.id = c.image_file_id
             LEFT JOIN product_categories pc ON pc.category_id = c.id
             LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'active'
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC
             LIMIT 100"
        );

        return array_map(static function (array $row, int $index): array {
            $imagePath = (string)($row['image_path'] ?? '');
            return [
                'id' => (int)$row['id'],
                'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
                'slug' => (string)$row['slug'],
                'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
                'product_count' => (int)($row['product_count'] ?? 0),
                'image_url' => $imagePath !== ''
                    ? self::fileUrl($imagePath)
                    : app_url('storefront/assets/img/category/cat-1-' . (($index % 10) + 1) . '.jpg'),
                'url' => app_url('shop?category=' . urlencode((string)$row['slug'])),
            ];
        }, $rows, array_keys($rows));
    }

    private static function vendors(int $limit, int $offset): array
    {
        $rows = db()->fetchAll(
            "SELECT v.*, lf.path logo_path, bf.path banner_path,
                    (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.id AND p.status = 'active') product_count
             FROM vendors v
             LEFT JOIN files lf ON lf.id = v.logo_file_id
             LEFT JOIN files bf ON bf.id = v.banner_file_id
             WHERE v.status = 'active'
             ORDER BY v.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        return array_map([self::class, 'vendorPayload'], $rows);
    }

    private static function vendorPayload(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'store_name' => (string)$row['store_name'],
            'store_slug' => (string)$row['store_slug'],
            'store_email' => $row['store_email'] ?? null,
            'store_phone' => $row['store_phone'] ?? null,
            'description' => (string)($row['description'] ?? ''),
            'status' => (string)$row['status'],
            'kyc_status' => (string)$row['kyc_status'],
            'logo_url' => self::fileUrl((string)($row['logo_path'] ?? '')),
            'banner_url' => self::fileUrl((string)($row['banner_path'] ?? '')),
            'product_count' => (int)($row['product_count'] ?? 0),
            'url' => app_url('vendors/' . (string)$row['store_slug']),
        ];
    }

    private static function cartPayload(): array
    {
        $cart = new CartService();
        $items = [];
        $subtotal = 0.0;
        foreach ($cart->items() as $productId => $quantity) {
            $product = self::product((int)$productId);
            if (!$product) {
                continue;
            }
            $lineTotal = (float)$product['price'] * (int)$quantity;
            $subtotal += $lineTotal;
            $items[] = ['product' => $product, 'quantity' => (int)$quantity, 'line_total' => $lineTotal];
        }
        return ['items' => $items, 'count' => $cart->count(), 'subtotal' => $subtotal, 'checkout_endpoint' => app_url('api/checkout/create')];
    }

    private static function appBanners(): array
    {
        $banners = [];
        if (function_exists('table_exists') && table_exists('marketing_sliders')) {
            $rows = db()->fetchAll(
                "SELECT id, title, headline, subheadline, image_url, image_url_mobile, cta_label, cta_url
                 FROM marketing_sliders
                 WHERE status = 'active'
                   AND (starts_at IS NULL OR starts_at <= ?)
                   AND (ends_at IS NULL OR ends_at >= ?)
                 ORDER BY sort_order ASC, updated_at DESC
                 LIMIT 8",
                [sql_now(), sql_now()]
            );
            foreach ($rows as $row) {
                $image = (string)($row['image_url_mobile'] ?: $row['image_url'] ?: '');
                if ($image === '') {
                    continue;
                }
                $banners[] = [
                    'id' => (int)$row['id'],
                    'title' => (string)($row['headline'] ?: $row['title']),
                    'subtitle' => (string)($row['subheadline'] ?? ''),
                    'image_url' => self::fileUrl($image),
                    'cta_label' => (string)($row['cta_label'] ?: 'Shop Now'),
                    'target_url' => self::targetUrl((string)($row['cta_url'] ?? 'shop')),
                ];
            }
        }

        if ($banners !== []) {
            return $banners;
        }

        return [
            [
                'id' => 1,
                'title' => 'Fresh African and Caribbean groceries',
                'subtitle' => 'Shop trusted vendors on Seller Africa.',
                'image_url' => app_url('assets/images/sac-banner-customers.jpeg'),
                'cta_label' => 'Shop Now',
                'target_url' => app_url('shop'),
            ],
            [
                'id' => 2,
                'title' => 'Vendor deals and marketplace picks',
                'subtitle' => 'Discover products ready to ship.',
                'image_url' => app_url('assets/images/sac-banner-advert.jpeg'),
                'cta_label' => 'Explore',
                'target_url' => app_url('shop'),
            ],
        ];
    }

    private static function targetUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return app_url('shop');
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        return app_url(ltrim($value, '/'));
    }

    private static function createCheckoutSession(array $input): void
    {
        $db = db();
        CommerceSafetyService::ensureSchema();
        VendorSubscriptionService::ensureSchema();

        $rawItems = $input['items'] ?? $input['cart_items'] ?? [];
        if (!is_array($rawItems) || $rawItems === []) {
            self::json(['ok' => false, 'message' => 'Cart items are required.'], 422);
        }

        $cartItems = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = (int)($item['product_id'] ?? $item['id'] ?? 0);
            $quantity = max(1, min(99, (int)($item['quantity'] ?? 1)));
            if ($productId <= 0 || !self::product($productId)) {
                continue;
            }
            $cartItems[$productId] = ($cartItems[$productId] ?? 0) + $quantity;
        }

        if ($cartItems === []) {
            self::json(['ok' => false, 'message' => 'Add at least one available product to checkout.'], 422);
        }

        $customer = is_array($input['customer'] ?? null) ? $input['customer'] : [];
        $firstName = trim((string)($customer['first_name'] ?? ''));
        $lastName = trim((string)($customer['last_name'] ?? ''));
        $email = trim((string)($customer['email'] ?? ''));
        $phone = trim((string)($customer['phone'] ?? ''));
        $addressLine1 = trim((string)($customer['address_line1'] ?? ''));
        $addressLine2 = trim((string)($customer['address_line2'] ?? ''));
        $city = trim((string)($customer['city'] ?? ''));
        $state = trim((string)($customer['state'] ?? ''));
        $postcode = trim((string)($customer['postcode'] ?? ''));
        $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($customer['country_code'] ?? 'US')) ?: 'US', 0, 2));
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($input['currency'] ?? 'USD')) ?: 'USD', 0, 3));
        $note = trim((string)($customer['customer_note'] ?? $input['customer_note'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            self::json(['ok' => false, 'message' => 'Enter your first and last name.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::json(['ok' => false, 'message' => 'Enter a valid email address.'], 422);
        }
        if ($phone === '') {
            self::json(['ok' => false, 'message' => 'Enter your phone number.'], 422);
        }
        if ($addressLine1 === '') {
            self::json(['ok' => false, 'message' => 'Enter your delivery address.'], 422);
        }

        $requestedMethodId = (int)($input['payment_method_id'] ?? 0);
        $paymentMethod = $requestedMethodId > 0
            ? $db->fetch(
                "SELECT id, code, name, provider, settings
                 FROM payment_methods
                 WHERE id = ? AND is_active = 1 AND provider = 'stripe'
                 LIMIT 1",
                [$requestedMethodId]
            )
            : $db->fetch(
                "SELECT id, code, name, provider, settings
                 FROM payment_methods
                 WHERE is_active = 1 AND provider = 'stripe'
                 ORDER BY FIELD(code, 'stripe'), id ASC
                 LIMIT 1"
            );

        if (!$paymentMethod || !PaymentService::isStripeConfigured($paymentMethod)) {
            self::json(['ok' => false, 'message' => 'Stripe payment is not fully configured yet. Please contact support.'], 422);
        }

        $quote = (new CheckoutService($db))->quote($cartItems, $currency, [
            'country_code' => $country,
            'state' => $state,
            'postcode' => $postcode,
            'city' => $city,
            'address_line1' => $addressLine1,
            'address_line2' => $addressLine2,
            'name' => trim($firstName . ' ' . $lastName),
            'phone' => $phone,
            'email' => $email,
        ]);
        $items = $quote['items'];
        if ($items === []) {
            self::json(['ok' => false, 'message' => 'Your cart is empty.'], 422);
        }

        $subtotal = (float)$quote['subtotal'];
        $shippingTotal = (float)$quote['shipping'];
        $taxTotal = (float)$quote['tax'];
        $grandTotal = (float)$quote['total'];
        $shippingRateSnapshot = $quote['shipping_rate_snapshot'] ?? [];
        $currencies = (new StorefrontTemplateService($db))->currencies();
        $rateFor = static function (string $code) use ($currencies): float {
            foreach ($currencies as $currencyRow) {
                if (strtoupper((string)$currencyRow['code']) === strtoupper($code)) {
                    return max(0.000001, (float)($currencyRow['exchangeRate'] ?? 1));
                }
            }
            return 1.0;
        };
        $convert = static fn (float $amount, string $from, string $to): float => ($amount / $rateFor($from)) * $rateFor($to);
        $mobileUser = self::optionalCurrentUser();
        $customerId = $mobileUser ? (int)$mobileUser['id'] : null;
        if (!$customerId) {
            $existingUser = $db->fetch('SELECT id FROM users WHERE email = ? AND status = "active" LIMIT 1', [$email]);
            $customerId = $existingUser ? (int)$existingUser['id'] : null;
        }

        $orderNumber = 'SA-' . app_date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $reservedStock = [];
        $orderId = null;
        $paymentId = null;
        $providerReference = '';

        try {
            $db->beginTransaction();

            foreach ($items as $item) {
                $productId = (int)($item['id'] ?? 0);
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $locked = $db->fetch(
                    'SELECT id, name, manage_stock, stock_quantity, stock_status FROM products WHERE id = ? FOR UPDATE',
                    [$productId]
                );
                if (!$locked || (string)($locked['stock_status'] ?? '') === 'out_of_stock') {
                    throw new \RuntimeException('One of the products in your cart is no longer available.', 422);
                }
                ProductVariationService::assertPurchasable($productId, $quantity);
                if ((int)($locked['manage_stock'] ?? 0) === 1) {
                    $available = (int)($locked['stock_quantity'] ?? 0);
                    if ($available < $quantity) {
                        throw new \RuntimeException((string)$locked['name'] . ' has only ' . max(0, $available) . ' left in stock.', 422);
                    }
                    $after = $available - $quantity;
                    $db->query(
                        "UPDATE products
                         SET stock_quantity = ?, stock_status = CASE WHEN ? <= 0 THEN 'out_of_stock' ELSE stock_status END, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?",
                        [$after, $after, $productId]
                    );
                    $reservedStock[] = ['product_id' => $productId, 'quantity' => $quantity, 'after' => $after];
                }
            }

            $addressId = null;
            if ($addressLine1 !== '') {
                if ($customerId) {
                    $db->query('UPDATE addresses SET is_default = 0 WHERE user_id = ? AND type = "shipping"', [$customerId]);
                }
                $db->query(
                    "INSERT INTO addresses
                        (user_id, type, first_name, last_name, company, phone, email, address_line1, address_line2, city, state, postcode, country_code, is_default)
                     VALUES (?, 'shipping', ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $customerId,
                        $firstName,
                        $lastName,
                        $phone,
                        $email,
                        $addressLine1,
                        $addressLine2 ?: null,
                        $city ?: null,
                        $state ?: null,
                        $postcode ?: null,
                        $country,
                        $customerId ? 1 : 0,
                    ]
                );
                $addressId = (int)$db->lastInsertId();
            }

            $db->query(
                "INSERT INTO orders
                    (order_number, customer_id, guest_email, status, payment_status, fulfillment_status, currency,
                     subtotal, discount_total, shipping_total, shipping_rate_snapshot, tax_total, fee_total, grand_total,
                     billing_address_id, shipping_address_id, customer_note, ip_address, user_agent, placed_at)
                 VALUES (?, ?, ?, 'pending', 'payment_pending', 'unfulfilled', ?, ?, 0, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderNumber,
                    $customerId,
                    $customerId ? null : $email,
                    $currency,
                    $subtotal,
                    $shippingTotal,
                    json_encode($shippingRateSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $taxTotal,
                    $grandTotal,
                    $addressId,
                    $addressId,
                    $note ?: null,
                    client_ip(),
                    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                    sql_now(),
                ]
            );
            $orderId = (int)$db->lastInsertId();

            foreach ($items as $item) {
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $unitPrice = $convert((float)($item['rawPrice'] ?? 0), (string)($item['currency'] ?? 'USD'), $currency);
                $lineTotal = $unitPrice * $quantity;
                $db->query(
                    "INSERT INTO order_items
                        (order_id, product_id, vendor_id, item_type, name, sku, quantity, unit_price, subtotal, discount_total, tax_total, total, metadata)
                     SELECT ?, p.id, p.vendor_id, 'product', p.name, p.sku, ?, ?, ?, 0, 0, ?,
                            JSON_OBJECT('image', ?, 'vendor', ?, 'source', 'mobile_app')
                     FROM products p
                     WHERE p.id = ?
                     LIMIT 1",
                    [
                        $orderId,
                        $quantity,
                        $unitPrice,
                        $lineTotal,
                        $lineTotal,
                        $item['image'] ?? null,
                        $item['vendor'] ?? null,
                        (int)$item['id'],
                    ]
                );
            }

            if (table_exists('product_inventory_movements')) {
                foreach ($reservedStock as $movement) {
                    $db->query(
                        "INSERT INTO product_inventory_movements
                            (product_id, movement_type, quantity_change, quantity_after, reference_type, reference_id, note, created_by)
                         VALUES (?, 'sale', ?, ?, 'order', ?, ?, ?)",
                        [
                            (int)$movement['product_id'],
                            -1 * (int)$movement['quantity'],
                            (int)$movement['after'],
                            $orderId,
                            'Reserved from mobile checkout order creation',
                            $customerId,
                        ]
                    );
                }
            }

            $db->query(
                "INSERT INTO order_vendor_splits
                    (order_id, vendor_id, status, item_subtotal, discount_total, shipping_total, tax_total, gross_total, vendor_earning, platform_commission, created_at, updated_at)
                 SELECT
                    oi.order_id,
                    oi.vendor_id,
                    'pending',
                    COALESCE(SUM(oi.subtotal), 0),
                    COALESCE(SUM(oi.discount_total), 0),
                    0,
                    COALESCE(SUM(oi.tax_total), 0),
                    COALESCE(SUM(oi.total), 0),
                    GREATEST(COALESCE(SUM(oi.total), 0) - (COALESCE(SUM(oi.total), 0) * COALESCE(vp.transaction_fee_percent, v.commission_rate, 0) / 100), 0),
                    COALESCE(SUM(oi.total), 0) * COALESCE(vp.transaction_fee_percent, v.commission_rate, 0) / 100,
                    ?,
                    ?
                 FROM order_items oi
                 INNER JOIN vendors v ON v.id = oi.vendor_id
                 LEFT JOIN vendor_subscriptions vs ON vs.id = (
                    SELECT vs2.id
                    FROM vendor_subscriptions vs2
                    WHERE vs2.vendor_id = oi.vendor_id
                      AND (
                        vs2.status IN ('active', 'trialing')
                        OR (vs2.status IN ('past_due', 'unpaid') AND vs2.grace_ends_at IS NOT NULL AND vs2.grace_ends_at >= NOW())
                      )
                    ORDER BY FIELD(vs2.status, 'active', 'trialing', 'past_due', 'unpaid'), vs2.updated_at DESC, vs2.id DESC
                    LIMIT 1
                 )
                 LEFT JOIN vendor_packages vp ON vp.id = vs.package_id
                 WHERE oi.order_id = ? AND oi.vendor_id IS NOT NULL
                 GROUP BY oi.order_id, oi.vendor_id, v.commission_rate, vp.transaction_fee_percent
                 ON DUPLICATE KEY UPDATE
                    item_subtotal = VALUES(item_subtotal),
                    discount_total = VALUES(discount_total),
                    tax_total = VALUES(tax_total),
                    gross_total = VALUES(gross_total),
                    vendor_earning = VALUES(vendor_earning),
                    platform_commission = VALUES(platform_commission),
                    updated_at = VALUES(updated_at)",
                [sql_now(), sql_now(), $orderId]
            );

            if ($shippingTotal > 0) {
                $db->query(
                    "UPDATE order_vendor_splits
                     SET shipping_total = ROUND(item_subtotal / NULLIF(?, 0) * ?, 4),
                         gross_total = item_subtotal + shipping_total + tax_total - discount_total,
                         updated_at = ?
                     WHERE order_id = ?",
                    [$subtotal, $shippingTotal, sql_now(), $orderId]
                );
            }

            $db->query(
                "UPDATE order_items oi
                 INNER JOIN order_vendor_splits ovs ON ovs.order_id = oi.order_id AND ovs.vendor_id = oi.vendor_id
                 SET oi.vendor_split_id = ovs.id
                 WHERE oi.order_id = ?",
                [$orderId]
            );

            $providerReference = $orderNumber . '-' . (string)$paymentMethod['code'];
            $db->query(
                "INSERT INTO payments
                    (order_id, user_id, payment_method_id, provider, provider_reference, provider_status, amount, currency, status, raw_response)
                 VALUES (?, ?, ?, 'stripe', ?, 'created', ?, ?, 'pending', ?)",
                [
                    $orderId,
                    $customerId,
                    (int)$paymentMethod['id'],
                    $providerReference,
                    $grandTotal,
                    $currency,
                    json_encode([
                        'method' => $paymentMethod['code'],
                        'name' => $paymentMethod['name'],
                        'created_from' => 'mobile_app_checkout',
                        'checkout_mode' => $customerId ? 'customer' : 'guest',
                        'expected_order_total' => $grandTotal,
                        'shipping_amount' => $shippingTotal,
                        'shipping_rate_snapshot' => $shippingRateSnapshot,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            );
            $paymentId = (int)$db->lastInsertId();

            $db->query(
                "INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by)
                 VALUES (?, NULL, 'pending', ?, ?)",
                [$orderId, 'Order created from mobile app checkout.', $customerId]
            );

            AffiliateService::createReferralForOrder($orderId, $customerId, $grandTotal, $currency);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) {
                $db->rollBack();
            }
            self::json(['ok' => false, 'message' => $e->getMessage()], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422);
        }

        try {
            $redirectUrl = PaymentService::createStripeCheckoutSession(
                [
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'grand_total' => $grandTotal,
                    'currency' => $currency,
                ],
                [
                    'id' => $paymentId,
                    'amount' => $grandTotal,
                    'currency' => $currency,
                    'provider_reference' => $providerReference,
                ],
                [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ],
                $paymentMethod
            );
        } catch (\Throwable $paymentError) {
            $db->query(
                "UPDATE payments
                 SET status = 'failed', provider_status = 'checkout_start_failed', raw_response = JSON_SET(COALESCE(raw_response, JSON_OBJECT()), '$.checkout_start_error', ?)
                 WHERE id = ?",
                [$paymentError->getMessage(), $paymentId]
            );
            $db->query(
                "UPDATE orders
                 SET payment_status = 'payment_failed', status = CASE WHEN status = 'pending' THEN 'failed' ELSE status END
                 WHERE id = ? AND payment_status <> 'paid'",
                [$orderId]
            );
            self::json(['ok' => false, 'message' => 'Payment failed to start. Please try again.'], 422);
        }

        self::json([
            'ok' => true,
            'message' => 'Continue to secure payment to complete your order.',
            'checkout_url' => $redirectUrl,
            'redirect_url' => $redirectUrl,
            'order' => [
                'id' => $orderId,
                'number' => $orderNumber,
                'total' => StorefrontTemplateService::money($grandTotal, $currency),
                'status' => 'pending',
                'checkout_mode' => $customerId ? 'customer' : 'guest',
            ],
            'payment' => [
                'id' => $paymentId,
                'provider' => 'stripe',
                'method' => (string)$paymentMethod['name'],
                'status' => 'pending',
            ],
            'items' => $items,
        ], 201);
    }

    private static function updateProfile(int $userId, array $input): void
    {
        db()->query(
            'UPDATE users SET first_name = ?, last_name = ?, display_name = ?, phone = ?, updated_at = ? WHERE id = ?',
            [trim((string)($input['first_name'] ?? '')), trim((string)($input['last_name'] ?? '')), trim((string)($input['display_name'] ?? '')), trim((string)($input['phone'] ?? '')), sql_now(), $userId]
        );
        self::json(['ok' => true, 'profile' => self::publicUser(db()->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]) ?? [])]);
    }

    private static function addresses(int $userId): array
    {
        return db()->fetchAll('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, updated_at DESC, id DESC', [$userId]);
    }

    private static function saveAddress(int $userId, array $input, ?int $addressId = null): void
    {
        if (trim((string)($input['address_line1'] ?? '')) === '') {
            self::json(['ok' => false, 'message' => 'Address line 1 is required.'], 422);
        }
        if ((int)($input['is_default'] ?? 0) === 1) {
            db()->query('UPDATE addresses SET is_default = 0 WHERE user_id = ? AND type = ?', [$userId, (string)($input['type'] ?? 'shipping')]);
        }

        $params = [
            (string)($input['type'] ?? 'shipping'),
            trim((string)($input['first_name'] ?? '')),
            trim((string)($input['last_name'] ?? '')),
            trim((string)($input['company'] ?? '')),
            trim((string)($input['phone'] ?? '')),
            trim((string)($input['email'] ?? '')),
            trim((string)$input['address_line1']),
            trim((string)($input['address_line2'] ?? '')),
            trim((string)($input['city'] ?? '')),
            trim((string)($input['state'] ?? '')),
            trim((string)($input['postcode'] ?? '')),
            strtoupper(substr(trim((string)($input['country_code'] ?? 'NG')), 0, 2)),
            (int)($input['is_default'] ?? 0),
            sql_now(),
        ];

        if ($addressId) {
            db()->query(
                'UPDATE addresses SET type = ?, first_name = ?, last_name = ?, company = ?, phone = ?, email = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postcode = ?, country_code = ?, is_default = ?, updated_at = ? WHERE id = ? AND user_id = ?',
                [...$params, $addressId, $userId]
            );
        } else {
            db()->query(
                'INSERT INTO addresses (user_id, type, first_name, last_name, company, phone, email, address_line1, address_line2, city, state, postcode, country_code, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, ...$params, sql_now()]
            );
        }
        self::json(['ok' => true, 'addresses' => self::addresses($userId)]);
    }

    private static function buyerOrders(int $userId): array
    {
        return db()->fetchAll('SELECT id, order_number, status, payment_status, fulfillment_status, currency, grand_total, placed_at, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 100', [$userId]);
    }

    private static function buyerOrder(int $userId, int $orderId): ?array
    {
        $order = db()->fetch('SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1', [$orderId, $userId]);
        if (!$order) {
            return null;
        }
        $order['items'] = db()->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$orderId]);
        $order['shipments'] = db()->fetchAll('SELECT * FROM shipments WHERE order_id = ? ORDER BY id ASC', [$orderId]);
        return $order;
    }

    private static function wishlist(int $userId): array
    {
        $wishlistId = self::defaultWishlistId($userId);
        $rows = db()->fetchAll(
            "SELECT p.*, v.store_name, v.store_slug, f.path image_path
             FROM wishlist_items wi
             INNER JOIN products p ON p.id = wi.product_id
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2 WHERE pm2.product_id = p.id ORDER BY pm2.role = 'primary' DESC, pm2.sort_order ASC, pm2.id ASC LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE wi.wishlist_id = ?
             ORDER BY wi.created_at DESC",
            [$wishlistId]
        );
        return array_map([self::class, 'productPayload'], $rows);
    }

    private static function wishlistAdd(int $userId, int $productId): void
    {
        $wishlistId = self::defaultWishlistId($userId);
        db()->query('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id, created_at) VALUES (?, ?, ?)', [$wishlistId, $productId, sql_now()]);
        self::json(['ok' => true, 'wishlist' => self::wishlist($userId)]);
    }

    private static function createReview(int $userId, array $input): void
    {
        $productId = (int)($input['product_id'] ?? 0);
        $rating = max(1, min(5, (int)($input['rating'] ?? 0)));
        if (!$productId || !self::product($productId)) {
            self::json(['ok' => false, 'message' => 'Valid product is required.'], 422);
        }
        db()->query(
            'INSERT INTO reviews (product_id, user_id, rating, title, body, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "pending", ?, ?)',
            [$productId, $userId, $rating, trim((string)($input['title'] ?? '')), trim((string)($input['body'] ?? '')), sql_now(), sql_now()]
        );
        self::json(['ok' => true, 'message' => 'Review submitted for approval.'], 201);
    }

    private static function vendorProducts(int $vendorId): array
    {
        $rows = db()->fetchAll(
            "SELECT p.*, f.path image_path
             FROM products p
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2 WHERE pm2.product_id = p.id ORDER BY pm2.role = 'primary' DESC, pm2.sort_order ASC, pm2.id ASC LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE p.vendor_id = ?
             ORDER BY p.updated_at DESC
             LIMIT 100",
            [$vendorId]
        );
        return array_map([self::class, 'productPayload'], $rows);
    }

    private static function saveVendorProduct(int $vendorId, int $userId, array $input, ?int $productId = null): void
    {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $price = (float)($input['regular_price'] ?? 0);
        if ($name === '' || $description === '' || $price <= 0) {
            self::json(['ok' => false, 'message' => 'Product name, description, and price are required.'], 422);
        }

        $sku = ProductService::requireUniqueSku((string)($input['sku'] ?? ''), $productId);
        $stock = max(0, (int)($input['stock_quantity'] ?? 0));
        $params = [
            $sku,
            $name,
            trim((string)($input['short_description'] ?? '')),
            $description,
            $price,
            self::nullableFloat($input['sale_price'] ?? null),
            $stock > 0 ? 'in_stock' : 'out_of_stock',
            $stock,
            (float)($input['weight'] ?? 0),
            (float)($input['length'] ?? 0),
            (float)($input['width'] ?? 0),
            (float)($input['height'] ?? 0),
            sql_now(),
        ];

        if ($productId) {
            db()->query(
                "UPDATE products SET sku = ?, name = ?, short_description = ?, description = ?, regular_price = ?, sale_price = ?, stock_status = ?, stock_quantity = ?, weight = ?, length = ?, width = ?, height = ?, status = 'pending', updated_at = ? WHERE id = ? AND vendor_id = ?",
                [...$params, $productId, $vendorId]
            );
        } else {
            db()->query(
                "INSERT INTO products (vendor_id, type, sku, name, slug, short_description, description, status, visibility, regular_price, sale_price, currency, tax_status, stock_status, stock_quantity, manage_stock, weight, length, width, height, created_at, updated_at)
                 VALUES (?, 'simple', ?, ?, ?, ?, ?, 'pending', 'visible', ?, ?, 'USD', 'taxable', ?, ?, 1, ?, ?, ?, ?, ?, ?)",
                [$vendorId, $sku, $name, self::uniqueSlug('products', 'slug', $name), $params[2], $description, $price, $params[5], $params[6], $stock, $params[8], $params[9], $params[10], $params[11], sql_now(), sql_now()]
            );
            $productId = (int)db()->lastInsertId();
        }

        $fileId = self::optionalUpload('image', $userId, 'product');
        if ($fileId !== null) {
            db()->query('UPDATE product_media SET role = "gallery" WHERE product_id = ? AND role = "primary"', [(int)$productId]);
            db()->query('INSERT INTO product_media (product_id, file_id, role, sort_order, created_at) VALUES (?, ?, "primary", 0, ?)', [(int)$productId, $fileId, sql_now()]);
        }

        self::json(['ok' => true, 'product' => self::vendorProduct($vendorId, (int)$productId)], $productId ? 200 : 201);
    }

    private static function vendorProduct(int $vendorId, int $productId): ?array
    {
        $row = db()->fetch('SELECT * FROM products WHERE id = ? AND vendor_id = ? LIMIT 1', [$productId, $vendorId]);
        return $row ? self::productPayload($row) : null;
    }

    private static function vendorOrders(int $vendorId): array
    {
        return db()->fetchAll(
            "SELECT ovs.*, o.order_number, o.currency, o.payment_status, o.created_at, COALESCE(u.display_name, 'Guest customer') customer_name, COALESCE(u.email, o.guest_email) customer_email,
                    s.tracking_number, s.tracking_url, s.status shipment_status
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             LEFT JOIN users u ON u.id = o.customer_id
             LEFT JOIN shipments s ON s.id = (SELECT s2.id FROM shipments s2 WHERE s2.order_id = o.id AND s2.vendor_id = ovs.vendor_id ORDER BY s2.id DESC LIMIT 1)
             WHERE ovs.vendor_id = ?
               AND o.payment_status = 'paid'
             ORDER BY o.created_at DESC
             LIMIT 100",
            [$vendorId]
        );
    }

    private static function updateVendorOrder(int $vendorId, int $splitId, array $input): void
    {
        $status = (string)($input['status'] ?? '');
        if (!in_array($status, ['processing', 'shipped', 'completed', 'cancelled'], true)) {
            self::json(['ok' => false, 'message' => 'Invalid order status.'], 422);
        }
        $split = db()->fetch(
            "SELECT ovs.*
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.id = ? AND ovs.vendor_id = ? AND o.payment_status = 'paid'
             LIMIT 1",
            [$splitId, $vendorId]
        );
        if (!$split) {
            self::json(['ok' => false, 'message' => 'Order not found.'], 404);
        }
        db()->query('UPDATE order_vendor_splits SET status = ?, updated_at = ? WHERE id = ?', [$status, sql_now(), $splitId]);
        $trackingNumber = trim((string)($input['tracking_number'] ?? ''));
        $trackingUrl = trim((string)($input['tracking_url'] ?? ''));
        if ($trackingNumber !== '' || $trackingUrl !== '') {
            $shipment = db()->fetch('SELECT id FROM shipments WHERE order_id = ? AND vendor_id = ? LIMIT 1', [(int)$split['order_id'], $vendorId]);
            if ($shipment) {
                db()->query('UPDATE shipments SET tracking_number = ?, tracking_url = ?, status = ?, updated_at = ? WHERE id = ?', [$trackingNumber, $trackingUrl, $status === 'completed' ? 'delivered' : 'in_transit', sql_now(), (int)$shipment['id']]);
            } else {
                db()->query('INSERT INTO shipments (order_id, vendor_id, tracking_number, tracking_url, status, shipped_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [(int)$split['order_id'], $vendorId, $trackingNumber, $trackingUrl, $status === 'completed' ? 'delivered' : 'in_transit', sql_now(), sql_now(), sql_now()]);
            }
        }
        self::json(['ok' => true]);
    }

    private static function vendorReviews(int $vendorId): array
    {
        return db()->fetchAll(
            "SELECT r.*, p.name product_name, p.sku, COALESCE(NULLIF(u.display_name, ''), u.email) customer_name
             FROM reviews r
             INNER JOIN products p ON p.id = r.product_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE p.vendor_id = ?
             ORDER BY r.created_at DESC
             LIMIT 100",
            [$vendorId]
        );
    }

    private static function vendorKycDocuments(int $vendorId): array
    {
        return db()->fetchAll(
            "SELECT d.id, d.document_type, d.document_number, d.status, d.rejection_reason, d.submitted_at, d.reviewed_at, f.path file_path
             FROM vendor_kyc_documents d
             LEFT JOIN files f ON f.id = d.file_id
             WHERE d.vendor_id = ?
             ORDER BY d.submitted_at DESC, d.id DESC",
            [$vendorId]
        );
    }

    private static function submitVendorKyc(int $vendorId, int $userId, array $input): void
    {
        $documentType = trim((string)($input['document_type'] ?? 'identity'));
        $documentNumber = trim((string)($input['document_number'] ?? ''));
        if ($documentType === '') {
            self::json(['ok' => false, 'message' => 'Document type is required.'], 422);
        }

        $fileId = self::optionalUpload('document', $userId, 'kyc');
        if ($fileId === null) {
            self::json(['ok' => false, 'message' => 'KYC document image is required.'], 422);
        }

        db()->query(
            'INSERT INTO vendor_kyc_documents (vendor_id, document_type, document_number, file_id, status, submitted_at) VALUES (?, ?, ?, ?, "pending", ?)',
            [$vendorId, $documentType, $documentNumber !== '' ? $documentNumber : null, $fileId, sql_now()]
        );
        db()->query('UPDATE vendors SET kyc_status = "pending", updated_at = ? WHERE id = ?', [sql_now(), $vendorId]);

        self::json(['ok' => true, 'documents' => self::vendorKycDocuments($vendorId), 'store' => self::vendorForVendorId($vendorId)], 201);
    }

    private static function vendorPayouts(int $vendorId): array
    {
        return db()->fetchAll('SELECT * FROM vendor_withdrawals WHERE vendor_id = ? ORDER BY requested_at DESC, id DESC LIMIT 100', [$vendorId]);
    }

    private static function requestPayout(int $vendorId, array $input): void
    {
        $amount = (float)($input['amount'] ?? 0);
        $available = self::availableBalance($vendorId);
        if ($amount <= 0 || $amount > $available) {
            self::json(['ok' => false, 'message' => 'Enter a valid payout amount within available balance.'], 422);
        }
        db()->query(
            'INSERT INTO vendor_withdrawals (vendor_id, amount, currency, method, status, note, requested_at) VALUES (?, ?, "USD", ?, "pending", ?, ?)',
            [$vendorId, $amount, (string)($input['method'] ?? 'bank_transfer'), trim((string)($input['note'] ?? '')), sql_now()]
        );
        self::json(['ok' => true, 'available_balance' => self::availableBalance($vendorId), 'payouts' => self::vendorPayouts($vendorId)], 201);
    }

    private static function updateVendorStore(int $vendorId, int $userId, array $input): void
    {
        $logoId = self::optionalUpload('logo', $userId, 'store');
        $bannerId = self::optionalUpload('banner', $userId, 'store');
        $sets = ['store_name = ?', 'store_email = ?', 'store_phone = ?', 'description = ?', 'payout_method = ?', 'payout_details = ?', 'updated_at = ?'];
        $params = [
            trim((string)($input['store_name'] ?? '')),
            trim((string)($input['store_email'] ?? '')),
            trim((string)($input['store_phone'] ?? '')),
            trim((string)($input['description'] ?? '')),
            trim((string)($input['payout_method'] ?? 'bank_transfer')),
            json_encode($input['payout_details'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            sql_now(),
        ];
        if ($logoId !== null) {
            $sets[] = 'logo_file_id = ?';
            $params[] = $logoId;
        }
        if ($bannerId !== null) {
            $sets[] = 'banner_file_id = ?';
            $params[] = $bannerId;
        }
        $params[] = $vendorId;

        db()->query(
            'UPDATE vendors SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
        self::json(['ok' => true, 'store' => self::vendorForVendorId($vendorId)]);
    }

    private static function vendorMetrics(int $vendorId): array
    {
        $sales = db()->fetch(
            "SELECT COALESCE(SUM(ovs.gross_total), 0) total, COUNT(*) orders
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ? AND o.payment_status = 'paid'",
            [$vendorId]
        ) ?: [];
        $products = db()->fetch('SELECT COUNT(*) total, COALESCE(SUM(status = "active"), 0) active FROM products WHERE vendor_id = ?', [$vendorId]) ?: [];
        $pending = db()->fetch(
            "SELECT COUNT(*) total
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ? AND ovs.status IN ('pending', 'processing') AND o.payment_status = 'paid'",
            [$vendorId]
        ) ?: [];
        return [
            'sales_total' => (float)($sales['total'] ?? 0),
            'orders_total' => (int)($sales['orders'] ?? 0),
            'products_total' => (int)($products['total'] ?? 0),
            'products_active' => (int)($products['active'] ?? 0),
            'orders_pending' => (int)($pending['total'] ?? 0),
            'available_balance' => self::availableBalance($vendorId),
        ];
    }

    private static function availableBalance(int $vendorId): float
    {
        $earned = (float)(db()->fetch(
            "SELECT COALESCE(SUM(ovs.vendor_earning), 0) total
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ? AND ovs.status IN ('completed', 'shipped', 'processing') AND o.payment_status = 'paid'",
            [$vendorId]
        )['total'] ?? 0);
        $withdrawn = (float)(db()->fetch("SELECT COALESCE(SUM(amount), 0) total FROM vendor_withdrawals WHERE vendor_id = ? AND status IN ('pending', 'approved', 'processing', 'paid')", [$vendorId])['total'] ?? 0);
        return max(0.0, $earned - $withdrawn);
    }

    private static function requireVendor(int $userId): array
    {
        $vendor = self::vendorForUser($userId);
        if (!$vendor) {
            self::json(['ok' => false, 'message' => 'Vendor account required.'], 403);
        }
        return $vendor;
    }

    private static function vendorForUser(int $userId): ?array
    {
        $row = db()->fetch(
            'SELECT v.*, lf.path logo_path, bf.path banner_path FROM vendors v LEFT JOIN files lf ON lf.id = v.logo_file_id LEFT JOIN files bf ON bf.id = v.banner_file_id WHERE v.user_id = ? LIMIT 1',
            [$userId]
        );
        return $row ?: null;
    }

    private static function vendorForVendorId(int $vendorId): ?array
    {
        $row = db()->fetch(
            'SELECT v.*, lf.path logo_path, bf.path banner_path FROM vendors v LEFT JOIN files lf ON lf.id = v.logo_file_id LEFT JOIN files bf ON bf.id = v.banner_file_id WHERE v.id = ? LIMIT 1',
            [$vendorId]
        );
        return $row ? self::vendorPayload($row) : null;
    }

    private static function publicUser(array $user): array
    {
        return [
            'id' => (int)($user['id'] ?? 0),
            'email' => (string)($user['email'] ?? ''),
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'display_name' => $user['display_name'] ?: ($user['username'] ?? $user['email'] ?? ''),
            'phone' => $user['phone'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
    }

    private static function roleCodes(int $userId): array
    {
        $rows = db()->fetchAll('SELECT r.code FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$userId]);
        $roles = array_values(array_unique(array_map('strval', array_column($rows, 'code'))));
        if (db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [$userId]) && !in_array('vendor', $roles, true)) {
            $roles[] = 'vendor';
        }
        return $roles !== [] ? $roles : ['customer'];
    }

    private static function assignRole(int $userId, string $role): void
    {
        $row = db()->fetch('SELECT id FROM roles WHERE code = ? LIMIT 1', [$role]);
        if ($row) {
            db()->query('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, ?)', [$userId, (int)$row['id'], sql_now()]);
        }
    }

    private static function defaultWishlistId(int $userId): int
    {
        $row = db()->fetch('SELECT id FROM wishlists WHERE user_id = ? AND is_default = 1 LIMIT 1', [$userId]);
        if ($row) {
            return (int)$row['id'];
        }
        db()->query('INSERT INTO wishlists (user_id, name, is_default, created_at) VALUES (?, "Wishlist", 1, ?)', [$userId, sql_now()]);
        return (int)db()->lastInsertId();
    }

    private static function productGallery(int $productId): array
    {
        $rows = db()->fetchAll(
            'SELECT f.path, pm.role, pm.sort_order FROM product_media pm INNER JOIN files f ON f.id = pm.file_id WHERE pm.product_id = ? ORDER BY pm.role = "primary" DESC, pm.sort_order ASC',
            [$productId]
        );
        return array_map(static fn (array $row): array => ['url' => self::fileUrl((string)$row['path']), 'role' => (string)$row['role']], $rows);
    }

    private static function input(): array
    {
        if ($_POST !== []) {
            return $_POST;
        }

        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        return [];
    }

    private static function bearerToken(): string
    {
        $header = (string)(
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_API_TOKEN']
            ?? $_SERVER['Authorization']
            ?? ''
        );

        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (in_array(strtolower((string)$key), ['authorization', 'x-authorization', 'x-api-token'], true)) {
                        $header = (string)$value;
                        break;
                    }
                }
            }
        }

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (in_array(strtolower((string)$key), ['authorization', 'x-authorization', 'x-api-token'], true)) {
                        $header = (string)$value;
                        break;
                    }
                }
            }
        }

        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }

        return trim($header);
    }

    private static function verifyPassword(string $password, string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }
        if (password_verify($password, $storedHash)) {
            return true;
        }
        if (str_starts_with($storedHash, '$wp$')) {
            $wordpressHash = substr($storedHash, 4);
            $preHash = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
            return password_verify($preHash, $wordpressHash);
        }
        return false;
    }

    private static function uniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]+/i', '_', strstr($email, '@', true) ?: 'user') ?: 'user';
        $username = strtolower(trim($base, '_')) ?: 'user';
        for ($i = 0; db()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]); $i++) {
            $username = strtolower(trim($base, '_')) . ($i + 1);
        }
        return $username;
    }

    private static function uniqueSlug(string $table, string $column, string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-')) ?: 'item';
        $base = $slug;
        for ($i = 2; db()->fetch("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1", [$slug]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        return (float)$value;
    }

    private static function fileUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return app_url(ltrim($path, '/'));
    }

    private static function optionalUpload(string $field, int $ownerUserId, string $folder): ?int
    {
        if (!is_array($_FILES[$field] ?? null) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$field];
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            self::json(['ok' => false, 'message' => 'Image upload failed.'], 422);
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = mime_content_type($tmp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            self::json(['ok' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.'], 422);
        }

        $dir = APP_ROOT . '/public/uploads/mobile/' . preg_replace('/[^a-z0-9_-]+/i', '-', $folder);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            self::json(['ok' => false, 'message' => 'Image could not be saved.'], 500);
        }

        $relativePath = 'uploads/mobile/' . preg_replace('/[^a-z0-9_-]+/i', '-', $folder) . '/' . $name;
        db()->query(
            'INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, created_at) VALUES (?, "public", ?, ?, ?, ?, ?)',
            [$ownerUserId, $relativePath, (string)($file['name'] ?? $name), $mime, (int)($file['size'] ?? 0), sql_now()]
        );

        return (int)db()->lastInsertId();
    }

    private static function limit(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int)$value));
    }

    private static function ensureTokenTable(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS mobile_api_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                device_name VARCHAR(190) NULL,
                last_used_at DATETIME NULL,
                expires_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mobile_api_tokens_user (user_id, revoked_at),
                INDEX idx_mobile_api_tokens_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureCheckoutSessionTable(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS mobile_checkout_sessions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                token_hash CHAR(64) NOT NULL UNIQUE,
                cart_json JSON NOT NULL,
                customer_json JSON NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mobile_checkout_sessions_expiry (expires_at, used_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
