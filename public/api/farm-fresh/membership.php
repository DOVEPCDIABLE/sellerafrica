<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\FarmFreshMembershipService;
use App\PaymentService;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new \RuntimeException('Method not allowed.');
    }

    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        throw new \RuntimeException('Your form session expired. Please refresh the page and try again.');
    }

    FarmFreshMembershipService::ensureSchema();
    $action = (string)($_POST['action'] ?? 'stripe');
    if ($action === 'klasha') {
        echo json_encode(['ok' => true, 'klasha' => FarmFreshMembershipService::createKlashaConfig($_POST)]);
        exit;
    }
    if ($action === 'klasha_callback') {
        $reference = trim((string)($_POST['reference'] ?? $_POST['tnxRef'] ?? $_POST['txRef'] ?? ''));
        $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
        $verified = PaymentService::verifyKlashaTransaction($reference, $method);
        if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
            $paid = FarmFreshMembershipService::applyKlashaStatus($reference, $verified['data'], 'verification');
        } else {
            $paid = FarmFreshMembershipService::applyKlashaStatus($reference, $_POST, 'callback_unverified');
        }
        echo json_encode([
            'ok' => true,
            'paid' => $paid,
            'message' => $paid ? 'Klasha payment verified.' : 'Klasha response received and queued for verification.',
        ]);
        exit;
    }

    echo json_encode(['ok' => true, 'url' => FarmFreshMembershipService::createStripeCheckout($_POST)]);
} catch (\Throwable $e) {
    audit('farm_fresh_membership_payment_failed', 'farm_fresh_memberships', null, [], [
        'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'action' => (string)($_POST['action'] ?? ''),
        'error' => $e->getMessage(),
    ]);
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
