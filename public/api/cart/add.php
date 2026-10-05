<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\CartService;
use App\NotificationService;
use App\StorefrontTemplateService;

header('Content-Type: application/json');

try {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $action = (string)($_POST['action'] ?? 'add');
    if (in_array($action, ['add','set'], true) && $quantity > 0) {
        $parent = db()->fetch("SELECT slug FROM products WHERE id=? AND type='variable'", [$productId]);
        if ($parent && \App\ProductVariationService::rows($productId)) {
            echo json_encode(['ok'=>false,'message'=>'Choose your product variation.','redirect'=>app_url('product/'.rawurlencode($parent['slug']))]);
            exit;
        }
    }

    $cart = new CartService();
    $ok = true;
    $message = 'Cart updated.';

    if ($action === 'list') {
        $item = null;
    } elseif ($productId <= 0) {
        $ok = false;
        $message = 'Invalid product.';
        $item = null;
    } elseif ($action === 'remove') {
        $item = $cart->remove($productId);
    } elseif ($action === 'set') {
        $item = $cart->set($productId, $quantity);
    } else {
        $item = $cart->add($productId, $quantity);
        $message = 'Added to cart.';
        NotificationService::cartUpdated(class_exists('\App\AuthService') ? \App\AuthService::user() : null, $item);
    }

    $renderer = new StorefrontTemplateService(db());
    $items = $renderer->cartProducts($cart->items());
    $subtotal = array_reduce(
        $items,
        static fn (float $sum, array $row): float => $sum + (float)($row['rawPrice'] ?? 0) * (int)($row['quantity'] ?? 1),
        0.0
    );

    echo json_encode([
        'ok' => $ok,
        'message' => $message,
        'item' => $item,
        'items' => $items,
        'cart_count' => $cart->count(),
        'subtotal' => $subtotal,
        'subtotal_formatted' => StorefrontTemplateService::money($subtotal),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\DomainException $e) {
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log('Cart API failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Cart could not be updated right now. Please refresh and try again.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
