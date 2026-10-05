<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/core/bootstrap.php';

use App\PaymentService;
use App\VendorSubscriptionService;
use App\ManageStoreService;
use App\VisibilityBoostService;

$reference = trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$context = trim((string)($_GET['context'] ?? 'vendor_subscription'));

try {
    if ($context === 'retail_placement') {
        $paid = \App\DistributorPaymentService::verify($reference);
        flash($paid ? 'success' : 'warning', $paid ? 'Retail placement payment confirmed.' : 'Payment is not confirmed yet. Please refresh shortly.');
        redirect('distributor?payment=1');
    }
    if ($context === 'vendor_registration') {
        $paid = \App\VendorRegistrationPaymentService::verify($reference);
        flash($paid ? 'success' : 'warning', $paid ? 'Registration fee confirmed. Continue your vendor application.' : 'Registration payment is not confirmed yet. Please refresh your payment status shortly.');
        redirect('vendor/verification');
    }
    if ($context === 'vendor_subscription' && str_starts_with($reference, 'SA-VENDOR-PAYSTACK-')) {
        $paid = VendorSubscriptionService::applyPaystackCheckout($reference);
        flash($paid ? 'success' : 'warning', $paid ? 'Payment confirmed. Your vendor plan is now active.' : 'Paystack payment is pending or could not be confirmed yet.');
        redirect('vendor/plans' . ($paid ? '?subscribed=1' : '?payment_pending=1'));
    }

    if (($context === 'storefront_checkout' || str_starts_with($reference, 'SA-ORDER-PAYSTACK-')) && $reference !== '') {
        $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1");
        $verified = PaymentService::verifyPaystackTransaction($reference, $method ?: null);
        $paid = false;
        if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
            $paid = PaymentService::applyPaystackStatus($reference, $verified['data'], 'verification');
        }

        $payment = db()->fetch(
            "SELECT o.order_number
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             WHERE p.provider = 'paystack' AND p.provider_reference = ?
             LIMIT 1",
            [$reference]
        );
        $orderNumber = (string)($payment['order_number'] ?? '');
        if ($orderNumber !== '') {
            redirect($paid
                ? 'storefront/order-success?order=' . rawurlencode($orderNumber)
                : 'track-order?order=' . rawurlencode($orderNumber));
        }

        flash('warning', 'Paystack payment response was received, but the order could not be found. Please contact support if you were charged.');
        redirect('storefront/checkout?payment_pending=1');
    }

    if (($context === 'manage_store' || str_starts_with($reference, 'SA-MANAGED-STORE-PAYSTACK-')) && $reference !== '') {
        $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1");
        $verified = PaymentService::verifyPaystackTransaction($reference, $method ?: null);
        $paid = false;
        if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
            $paid = ManageStoreService::applyPaystackStatus($reference, $verified['data'], 'verification');
        }
        flash($paid ? 'success' : 'warning', $paid ? 'Your store service payment is confirmed. Our team will contact you.' : 'Paystack payment is pending or could not be confirmed yet.');
        redirect('manage-store' . ($paid ? '?subscribed=1' : '?payment_pending=1'));
    }

    if (($context === 'visibility_boost' || str_starts_with($reference, 'SA-VISIBILITY-PAYSTACK-')) && $reference !== '') {
        $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1");
        $verified = PaymentService::verifyPaystackTransaction($reference, $method ?: null);
        $paid = false;
        if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
            $paid = VisibilityBoostService::applyPaystackStatus($reference, $verified['data'], 'verification');
        }
        flash($paid ? 'success' : 'warning', $paid ? 'Visibility boost is active.' : 'Paystack payment is pending or could not be confirmed yet.');
        redirect('visibility-boost' . ($paid ? '?subscribed=1' : '?payment_pending=1'));
    }
} catch (\Throwable $e) {
    error_log('Paystack callback failed: ' . $e->getMessage());
    flash('error', 'Paystack payment could not be confirmed. Please contact support if you were charged.');
    if ($context === 'retail_placement') redirect('distributor?payment=1');
    if ($context === 'vendor_registration') redirect('vendor/verification');
    if ($context === 'manage_store') {
        redirect('manage-store?payment_failed=1');
    }
    if ($context === 'visibility_boost') {
        redirect('visibility-boost?payment_failed=1');
    }
    redirect($context === 'storefront_checkout' ? 'storefront/checkout?payment_failed=1' : 'vendor/plans?payment_failed=1');
}

flash('error', 'Paystack payment reference was not recognized.');
if ($context === 'manage_store') {
    redirect('manage-store?payment_failed=1');
}
if ($context === 'visibility_boost') {
    redirect('visibility-boost?payment_failed=1');
}
redirect($context === 'storefront_checkout' ? 'storefront/checkout?payment_failed=1' : 'vendor/plans?payment_failed=1');
