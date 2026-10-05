<?php
declare(strict_types=1);
namespace App;

final class AdminAccessService
{
    private const RESTRICTED_VIEWS = [
        'payment-links',
        'transactions', 'payment-gateways', 'failed-payments', 'idempotency-logs', 'multi-currency-settings',
        'admin-profile', 'general-settings', 'store-settings', 'currency-settings', 'tax-settings',
        'seo-settings', 'mail-settings', 'security-settings', 'role-permissions', 'audit-logs',
        'queue-worker-monitor', 'audit-hub', 'aramex-api-settings', 'data-migration',
    ];

    // POST handlers are shared across views, so authorize actions independently of the URL.
    private const ADMIN_ACTIONS = [
        'save_product', 'update_product_status', 'delete_product_review', 'save_taxonomy',
        'save_coupon', 'update_coupon_status', 'delete_coupon', 'queue_marketing_campaign',
        'save_marketing_slider', 'save_marketing_banner', 'save_marketing_pixels',
        'update_withdrawal_status', 'update_vendor_approval', 'save_vendor_admin',
        'save_vendor_kyc_document_admin', 'save_vendor_subscription_admin', 'update_vendor_product_status_admin',
        'delete_vendor_product_admin', 'update_product_media_admin', 'delete_vendor_file_admin',
        'save_vendor_package', 'save_user_admin', 'save_shipping_zone', 'save_shipping_method',
        'save_delivery_partner', 'update_shipment_tracking', 'save_content_album', 'save_gallery_item',
        'save_content_post', 'delete_content_record', 'update_affiliate_status', 'save_commission_rule',
        'update_affiliate_referral_status', 'update_affiliate_payout_status', 'update_refund_status',
        'update_return_status', 'save_vendor_dispute', 'update_vendor_dispute_status',
        'save_buyer_complaint', 'update_buyer_complaint_status',
    ];

    public static function allows(array $roles, string $view, ?string $action = null): bool
    {
        if (in_array('super_admin', $roles, true)) return true;
        if (!in_array('admin', $roles, true) || in_array($view, self::RESTRICTED_VIEWS, true)) return false;
        return $action === null || in_array($action, self::ADMIN_ACTIONS, true);
    }

    public static function filterNavigation(array $navigation, array $roles): array
    {
        foreach ($navigation as $section => $items) {
            $navigation[$section] = array_values(array_filter($items, static fn(array $item): bool => self::allows($roles, $item['key'])));
            if (!$navigation[$section]) unset($navigation[$section]);
        }
        return $navigation;
    }

    public static function enforce(string $view): void
    {
        $action = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? (string)($_POST['action'] ?? '') : null;
        if (!self::allows($_SESSION['roles'] ?? [], $view, $action)) {
            http_response_code(403);
            exit('This section or action is restricted to super administrators.');
        }
    }

    public static function canEditUser(array $actorRoles, array $targetRoles): bool
    {
        return in_array('super_admin', $actorRoles, true)
            || (in_array('admin', $actorRoles, true) && !in_array('super_admin', $targetRoles, true));
    }
}
