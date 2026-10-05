<?php
declare(strict_types=1);

namespace App;

final class VendorRegistrationPaymentService
{
    public const AMOUNT = 1500;
    public const PREFIX = 'SA-REGISTRATION-';
    public const POLICY = 'mandatory_v1';

    public static function ensureSchema(): void
    {
        \db()->pdo()->exec("CREATE TABLE IF NOT EXISTS vendor_registration_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vendor_id BIGINT UNSIGNED NOT NULL,
            reference VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL,
            amount_minor INT UNSIGNED NOT NULL DEFAULT 150000,
            currency CHAR(3) NOT NULL DEFAULT 'NGN',
            authorization_url TEXT NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX vendor_paid (vendor_id, paid_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function required(array $data): bool
    {
        return !empty($data['onboarding']['registration_fee_required'])
            || ($data['onboarding']['registration_fee_policy'] ?? '') === self::POLICY;
    }

    public static function paid(int $vendorId, array $data): bool
    {
        if (!self::required($data)) return true;
        // No schema DDL here: this check is also used inside verification transactions.
        return \table_exists('vendor_registration_payments') && (bool)\db()->fetch(
            'SELECT id FROM vendor_registration_payments WHERE vendor_id=? AND amount_minor=150000 AND currency="NGN" AND paid_at IS NOT NULL LIMIT 1', [$vendorId]
        );
    }

    public static function checkout(int $vendorId): string
    {
        self::ensureSchema();
        $data = VendorOnboardingService::data(VendorOnboardingService::application($vendorId));
        if (self::paid($vendorId, $data)) return \app_url('vendor/verification');
        $vendor = \db()->fetch('SELECT u.email FROM vendors v JOIN users u ON u.id=v.user_id WHERE v.id=?', [$vendorId]);
        if (!$vendor) throw new \RuntimeException('Vendor account not found.');
        $method = \db()->fetch("SELECT * FROM payment_methods WHERE code='paystack' LIMIT 1");
        if (!$method || empty($method['is_active']) || !PaymentService::isPaystackConfigured($method)) {
            throw new \RuntimeException('Paystack registration payment is temporarily unavailable. Your account is saved; please try again later.');
        }
        $recent = \db()->fetch('SELECT * FROM vendor_registration_payments WHERE vendor_id=? AND paid_at IS NULL ORDER BY id DESC LIMIT 1', [$vendorId]);
        if ($recent && self::verify($recent['reference'])) return \app_url('vendor/verification');
        if ($recent && !empty($recent['authorization_url']) && strtotime($recent['created_at']) > time() - 3600) return $recent['authorization_url'];
        $reference = self::PREFIX . strtoupper(bin2hex(random_bytes(12)));
        \db()->query('INSERT INTO vendor_registration_payments (vendor_id,reference,email,amount_minor,currency) VALUES (?,?,?,150000,"NGN")', [$vendorId,$reference,$vendor['email']]);
        $response = PaymentService::initializePaystackTransaction(self::AMOUNT, 'NGN', $vendor['email'], $reference,
            \app_url('paystack_callback.php?context=vendor_registration'),
            ['context'=>'vendor_registration','vendor_id'=>(string)$vendorId], $method);
        $url = (string)($response['data']['authorization_url'] ?? '');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'checkout.paystack.com') {
            throw new \RuntimeException('Paystack did not return a valid checkout link. Please try again.');
        }
        \db()->query('UPDATE vendor_registration_payments SET authorization_url=? WHERE reference=?', [$url,$reference]);
        return $url;
    }

    public static function matches(array $payment, array $transaction): bool
    {
        return ($transaction['status'] ?? '') === 'success'
            && (int)($payment['amount_minor'] ?? 0) === self::AMOUNT * 100
            && ($payment['currency'] ?? '') === 'NGN'
            && ($transaction['reference'] ?? '') === $payment['reference']
            && (int)($transaction['amount'] ?? 0) === (int)$payment['amount_minor']
            && strtoupper((string)($transaction['currency'] ?? '')) === $payment['currency']
            && strcasecmp((string)($transaction['customer']['email'] ?? ''), $payment['email']) === 0;
    }

    public static function verify(string $reference): bool
    {
        if (!str_starts_with($reference, self::PREFIX)) return false;
        $payment = \db()->fetch('SELECT * FROM vendor_registration_payments WHERE reference=?', [$reference]);
        if (!$payment) return false;
        if ($payment['paid_at']) return true;
        $method = \db()->fetch("SELECT * FROM payment_methods WHERE code='paystack' LIMIT 1");
        $result = PaymentService::verifyPaystackTransaction($reference, $method ?: null);
        if (empty($result['ok']) || !self::matches($payment, (array)($result['data']['data'] ?? []))) return false;
        \db()->query('UPDATE vendor_registration_payments SET paid_at=COALESCE(paid_at,NOW()) WHERE id=?', [$payment['id']]);
        return true;
    }
}
