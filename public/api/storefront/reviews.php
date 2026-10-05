<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\AuthService;
use App\StorefrontTemplateService;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid review request.');
    }

    validateCsrf();

    $user = class_exists(AuthService::class) ? AuthService::user() : null;
    $userId = $user ? (int)($user['id'] ?? ($_SESSION['user_id'] ?? 0)) : null;
    $productId = max(0, (int)($_POST['product_id'] ?? 0));
    $rating = (int)($_POST['rating'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $guestName = trim((string)($_POST['author_name'] ?? ''));
    $guestEmail = strtolower(trim((string)($_POST['author_email'] ?? '')));
    $honeypot = trim((string)($_POST['website'] ?? ''));

    if ($productId <= 0) {
        throw new RuntimeException('Product was not found.');
    }
    if ($rating < 1 || $rating > 5) {
        throw new RuntimeException('Choose a rating from 1 to 5 stars.');
    }
    if ($body === '') {
        throw new RuntimeException('Write your review comment.');
    }
    if ($honeypot !== '') {
        throw new RuntimeException('Review could not be submitted.');
    }

    $renderer = new StorefrontTemplateService(db());
    $renderer->ensureReviewMediaSchema();

    $product = db()->fetch("SELECT id FROM products WHERE id = ? AND status = 'active' LIMIT 1", [$productId]);
    if (!$product) {
        throw new RuntimeException('Product was not found.');
    }

    $eligibility = $renderer->reviewEligibility($productId, $userId);
    if (empty($eligibility['canReview'])) {
        throw new RuntimeException((string)($eligibility['message'] ?? 'You are not eligible to review this product.'));
    }

    if ($userId) {
        $guestName = trim((string)($user['display_name'] ?? $user['username'] ?? $guestName));
        $guestEmail = strtolower(trim((string)($user['email'] ?? $guestEmail)));
    } else {
        if ($guestName === '') {
            throw new RuntimeException('Enter your name before submitting a review.');
        }
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email before submitting a review.');
        }
    }

    $guestName = review_truncate($guestName !== '' ? $guestName : 'Customer', 190);
    $guestEmail = $guestEmail !== '' && filter_var($guestEmail, FILTER_VALIDATE_EMAIL) ? review_truncate($guestEmail, 190) : null;
    $ipHash = review_hash_identifier('ip:' . client_ip());
    $userAgentHash = review_hash_identifier('ua:' . (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $reviewerKey = $userId ? 'user:' . $userId : 'email:' . (string)$guestEmail;

    review_apply_rate_limit($productId, 'ip:' . client_ip(), 5, 3600);
    review_apply_rate_limit($productId, $reviewerKey, 2, 86400);

    $existing = $userId
        ? db()->fetch('SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1', [$productId, $userId])
        : db()->fetch('SELECT id FROM reviews WHERE product_id = ? AND guest_email = ? LIMIT 1', [$productId, $guestEmail]);
    if ($existing) {
        throw new RuntimeException('Your review for this product has already been submitted.');
    }

    db()->beginTransaction();
    db()->query(
        "INSERT INTO reviews (product_id, user_id, order_id, rating, title, body, guest_name, guest_email, ip_hash, user_agent_hash, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)",
        [
            $productId,
            $userId,
            !empty($eligibility['orderId']) ? (int)$eligibility['orderId'] : null,
            $rating,
            $title !== '' ? review_truncate($title, 190) : null,
            $body,
            $guestName,
            $guestEmail,
            $ipHash,
            $userAgentHash,
            sql_now(),
            sql_now(),
        ]
    );
    $reviewId = (int)db()->lastInsertId();

    foreach (review_uploaded_files('images') as $index => $file) {
        if ($index >= 5) {
            break;
        }
        $fileId = review_store_image($file, $userId, $reviewId);
        db()->query(
            'INSERT INTO review_media (review_id, file_id, sort_order, created_at) VALUES (?, ?, ?, ?)',
            [$reviewId, $fileId, $index, sql_now()]
        );
    }

    db()->query(
        "UPDATE products p
         SET review_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'),
             average_rating = COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'), 0),
             updated_at = ?
         WHERE p.id = ?",
        [sql_now(), $productId]
    );

    db()->commit();
    audit('product.review.submitted', 'reviews', (string)$reviewId, [], ['product_id' => $productId, 'rating' => $rating], $userId);

    echo json_encode([
        'ok' => true,
        'message' => 'Review submitted. Thank you for sharing your experience.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (db()->pdo()->inTransaction()) {
        db()->rollBack();
    }
    $databaseError = $e instanceof PDOException || str_contains($e->getMessage(), 'SQLSTATE');
    if ($databaseError) {
        error_log('Product review submission failed: ' . $e->getMessage());
        audit('product.review.failed', 'reviews', null, [], [
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'reason' => $e->getMessage(),
            'next_step' => 'Ask the customer to submit again. If the reason mentions charset, confirm the reviews table is utf8mb4.',
        ], isset($userId) && $userId ? (int)$userId : null);
    }
    http_response_code(($e instanceof RuntimeException && !$databaseError) ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'message' => ($e instanceof RuntimeException && !$databaseError)
            ? $e->getMessage()
            : 'We could not save your review right now. Please try again. If it still fails, send this page to our technical team.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function review_uploaded_files(string $field): array
{
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload)) {
        return [];
    }

    if (!is_array($upload['name'] ?? null)) {
        return [(array)$upload];
    }

    $files = [];
    foreach ($upload['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $upload['type'][$index] ?? '',
            'tmp_name' => $upload['tmp_name'][$index] ?? '',
            'error' => $upload['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $upload['size'][$index] ?? 0,
        ];
    }

    return array_values(array_filter($files, static fn (array $file): bool => (int)$file['error'] !== UPLOAD_ERR_NO_FILE));
}

function review_store_image(array $file, ?int $userId, int $reviewId): int
{
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One review image could not be uploaded.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Review images must be smaller than 5MB.');
    }

    $mime = (string)(mime_content_type($tmp) ?: '');
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => throw new RuntimeException('Review images must be JPG, PNG, or WebP.'),
    };

    $dir = APP_ROOT . '/public/uploads/reviews/' . $reviewId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Review image directory could not be created.');
    }

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Review image could not be saved.');
    }

    [$width, $height] = @getimagesize($target) ?: [null, null];
    $relative = 'uploads/reviews/' . $reviewId . '/' . $name;
    db()->query(
        "INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text, created_at)
         VALUES (?, 'public', ?, ?, ?, ?, ?, ?, ?, ?)",
        [$userId, $relative, (string)($file['name'] ?? $name), $mime, $size, $width, $height, 'Review image', sql_now()]
    );

    return (int)db()->lastInsertId();
}

function review_apply_rate_limit(int $productId, string $identifier, int $maxAttempts, int $windowSeconds): void
{
    $hash = review_hash_identifier($identifier);
    $threshold = date('Y-m-d H:i:s', time() - $windowSeconds);
    $now = sql_now();
    $row = db()->fetch(
        'SELECT id, attempts, window_start FROM review_rate_limits WHERE product_id = ? AND identifier_hash = ? LIMIT 1',
        [$productId, $hash]
    );

    if (!$row) {
        db()->query(
            'INSERT INTO review_rate_limits (product_id, identifier_hash, attempts, window_start, created_at, updated_at) VALUES (?, ?, 1, ?, ?, ?)',
            [$productId, $hash, $now, $now, $now]
        );
        return;
    }

    if ((string)$row['window_start'] < $threshold) {
        db()->query(
            'UPDATE review_rate_limits SET attempts = 1, window_start = ?, updated_at = ? WHERE id = ?',
            [$now, $now, (int)$row['id']]
        );
        return;
    }

    if ((int)$row['attempts'] >= $maxAttempts) {
        throw new RuntimeException('Too many review attempts. Please wait and try again later.');
    }

    db()->query(
        'UPDATE review_rate_limits SET attempts = attempts + 1, updated_at = ? WHERE id = ?',
        [$now, (int)$row['id']]
    );
}

function review_hash_identifier(string $value): string
{
    $config = require CONFIG_PATH . '/config.php';
    $salt = (string)($config['security']['jwt_secret'] ?? 'seller-africa-reviews');

    return hash_hmac('sha256', $value, $salt);
}

function review_truncate(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}
