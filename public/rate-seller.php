<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Please log in to rate this seller.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit;
}

$submittedToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if ($submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    echo json_encode(['ok' => false, 'message' => 'Your session expired, please refresh and try again.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$productId = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);

if ($productId <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['ok' => false, 'message' => 'Please choose a rating between 1 and 5 stars.']);
    exit;
}

$existing = db()->fetch(
    'SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1',
    [$productId, $userId]
);

if ($existing) {
    db()->fetch(
        'UPDATE reviews SET rating = ?, status = ?, updated_at = NOW() WHERE id = ?',
        [$rating, 'approved', (int)$existing['id']]
    );
} else {
    db()->fetch(
        'INSERT INTO reviews (product_id, user_id, rating, status) VALUES (?, ?, ?, ?)',
        [$productId, $userId, $rating, 'approved']
    );
}

echo json_encode(['ok' => true]);
