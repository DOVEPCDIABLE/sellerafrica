<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\PublicVendorService;
use App\StorefrontTemplateService;

$vendorService = new PublicVendorService();
$cart = new CartService();
$brand = app_branding();
$slug = trim((string)($_GET['slug'] ?? ''));
$vendor = $slug !== '' ? $vendorService->vendor($slug) : null;
$products = $vendor ? $vendorService->products((int)$vendor['id'], 100) : [];
$errors = [];
$submitted = isset($_GET['submitted']);
$form = [
    'product_id' => (string)($products[0]['id'] ?? ''),
    'rating' => '5',
    'title' => '',
    'body' => '',
];

if (!$vendor) {
    http_response_code(404);
}

if ($vendor && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        validateCsrf();
        (new StorefrontTemplateService(db()))->ensureReviewMediaSchema();

        if (trim((string)($_POST['website'] ?? '')) !== '') {
            throw new RuntimeException('Review could not be submitted.');
        }

        $form = [
            'product_id' => (string)($_POST['product_id'] ?? ''),
            'rating' => (string)($_POST['rating'] ?? '5'),
            'title' => trim((string)($_POST['title'] ?? '')),
            'body' => trim((string)($_POST['body'] ?? '')),
        ];
        $productId = max(0, (int)$form['product_id']);
        $rating = max(1, min(5, (int)$form['rating']));
        $title = mb_substr($form['title'], 0, 190);
        $body = $form['body'];

        if ($productId <= 0) {
            throw new RuntimeException('Choose the product you are reviewing.');
        }
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Choose a rating from 1 to 5 stars.');
        }
        if ($body === '') {
            throw new RuntimeException('Write a short review before submitting.');
        }

        $product = db()->fetch(
            "SELECT id FROM products WHERE id = ? AND vendor_id = ? AND status = 'active' LIMIT 1",
            [$productId, (int)$vendor['id']]
        );
        if (!$product) {
            throw new RuntimeException('That product is not available for this vendor.');
        }

        db()->beginTransaction();
        db()->query(
            "INSERT INTO reviews (product_id, user_id, order_id, rating, title, body, status, created_at, updated_at)
             VALUES (?, NULL, NULL, ?, ?, ?, 'approved', ?, ?)",
            [$productId, $rating, $title !== '' ? $title : null, $body, sql_now(), sql_now()]
        );
        $reviewId = (int)db()->lastInsertId();
        db()->query(
            "UPDATE products p
             SET review_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'),
                 average_rating = COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'), 0),
                 updated_at = ?
             WHERE p.id = ?",
            [sql_now(), $productId]
        );
        db()->commit();

        audit('vendor.review.shared_submitted', 'reviews', (string)$reviewId, [], [
            'vendor_id' => (int)$vendor['id'],
            'product_id' => $productId,
            'rating' => $rating,
        ]);

        redirect('vendors/' . rawurlencode((string)$vendor['slug']) . '/review?submitted=1');
    } catch (Throwable $e) {
        if (db()->pdo()->inTransaction()) {
            db()->rollBack();
        }
        error_log('Vendor review submission failed: ' . $e->getMessage());
        audit('vendor.review.shared_failed', 'vendors', (string)($vendor['id'] ?? ''), [], [
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'reason' => $e->getMessage(),
            'next_step' => 'Ask the customer to submit again. If the reason mentions charset, confirm the reviews table is utf8mb4.',
        ]);
        $databaseError = $e instanceof PDOException || str_contains($e->getMessage(), 'SQLSTATE');
        $errors[] = $e instanceof RuntimeException && !$databaseError
            ? $e->getMessage()
            : 'We could not save your review right now. Please try again. If it still fails, send this page to our technical team.';
    }
}

render_layout('storefront-home', 'storefront/pages/vendor-review.php', [
    'page' => 'vendor-review',
    'title' => ($vendor ? 'Review ' . $vendor['name'] : 'Vendor Review') . ' | ' . $brand['name'],
    'metaDescription' => $vendor ? 'Leave a review for ' . $vendor['name'] . ' on Seller Africa.' : 'Leave a vendor review on Seller Africa.',
    'vendor' => $vendor,
    'products' => $products,
    'errors' => $errors,
    'submitted' => $submitted,
    'form' => $form,
    'cartCount' => $cart->count(),
]);
