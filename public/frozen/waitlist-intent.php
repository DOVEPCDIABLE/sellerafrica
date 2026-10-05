<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/core/bootstrap.php';

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

    $frozen = new \App\FrozenMarketService(db());
    $frozen->ensureSchema();
    if ((string)($_POST['action'] ?? '') === 'update_details') {
        $payment = $frozen->updateEarlyBirdPaymentIntentDetails((string)($_POST['payment_intent_id'] ?? ''), $_POST);
        echo json_encode(['ok' => true, 'payment' => $payment]);
        exit;
    }
    if ((string)($_POST['action'] ?? '') === 'create_klasha') {
        echo json_encode(['ok' => true, 'klasha' => $frozen->createEarlyBirdKlashaConfig($_POST)]);
        exit;
    }
    if ((string)($_POST['action'] ?? '') === 'klasha_callback') {
        $reference = trim((string)($_POST['reference'] ?? $_POST['tnxRef'] ?? $_POST['txRef'] ?? ''));
        $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
        $verified = \App\PaymentService::verifyKlashaTransaction($reference, $method);
        $paid = false;
        if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
            $paid = $frozen->applyEarlyBirdKlashaStatus($reference, $verified['data'], 'verification');
        } else {
            $paid = $frozen->applyEarlyBirdKlashaStatus($reference, $_POST, 'callback_unverified');
        }
        echo json_encode([
            'ok' => true,
            'paid' => $paid,
            'message' => $paid ? 'Klasha payment verified.' : 'Klasha response received and queued for verification.',
        ]);
        exit;
    }

    echo json_encode(['ok' => true] + $frozen->createEarlyBirdPaymentIntent($_POST));
} catch (\Throwable $e) {
    audit('frozen_early_bird_payment_intent_failed', 'frozen_waitlist_payments', null, [], [
        'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'error' => $e->getMessage(),
    ]);
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
