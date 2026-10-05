<?php

declare(strict_types=1);

namespace App;

/**
 * Guided, closed chat system between buyers and sellers.
 * Every message sent through this system MUST match a key in the fixed
 * lists below. There is no free-text input anywhere - this is by design,
 * so contact information can never be exchanged and conversations can
 * never wander outside the approved topics, matching the client's spec.
 */
final class ChatService
{
    /** Buyer quick-action messages, grouped exactly as specified. */
    public const BUYER_MESSAGES = [
        // General Product
        'buyer.stock' => 'Is this product in stock?',
        'buyer.moq' => 'What is the minimum order quantity (MOQ)?',
        'buyer.production_date' => 'When was this product produced?',
        'buyer.expiry_date' => 'What is the expiry date?',
        'buyer.ingredients' => 'What are the ingredients?',
        'buyer.organic' => 'Is this product organic?',
        'buyer.other_sizes' => 'Is it available in other sizes?',
        'buyer.wholesale_pricing' => 'Do you have wholesale pricing?',
        'buyer.buy_in_bulk' => 'Can I buy in bulk?',

        // Packaging
        'buyer.package_sizes' => 'What package sizes are available?',
        'buyer.package_weight' => 'How much does one package weigh?',
        'buyer.vacuum_sealed' => 'Is the product vacuum sealed?',
        'buyer.export_ready' => 'Is the packaging export-ready?',
        'buyer.custom_packaging' => 'Can you provide custom packaging?',

        // Shipping & Delivery
        'buyer.ship_how_soon' => 'How soon can this be shipped?',
        'buyer.ship_to_country' => 'Do you ship to my country?',
        'buyer.us_warehouse' => 'Is this item available in the U.S. warehouse?',
        'buyer.delivery_time' => 'What is the estimated delivery time?',
        'buyer.combine_shipment' => 'Can I combine multiple products into one shipment?',
        'buyer.express_shipping' => 'Is express shipping available?',
        'buyer.shipping_cost' => 'What is the shipping cost?',

        // Quality & Certifications
        'buyer.certifications' => 'Do you have product certifications?',
        'buyer.fda_compliant' => 'Is this FDA-compliant (if applicable)?',
        'buyer.batch_numbers' => 'Do you provide batch numbers?',
        'buyer.nutritional_info' => 'Can you share the nutritional information?',
        'buyer.preservative_free' => 'Is this product preservative-free?',

        // Product Details
        'buyer.storage' => 'How should this product be stored?',
        'buyer.shelf_life' => 'How long is the shelf life?',
        'buyer.common_uses' => 'What dishes is this product commonly used for?',
        'buyer.spicy' => 'Is this product spicy?',
        'buyer.vegetarian' => 'Is it suitable for vegetarians or vegans?',
        'buyer.color' => 'What color is this product?',
        'buyer.weight' => 'What is the weight?',
        'buyer.origin' => 'Where is this product shipping from?',

        // Pricing
        'buyer.bulk_discount' => 'Do you offer discounts on larger orders?',
        'buyer.current_promos' => 'Are there current promotions?',
        'buyer.custom_quote' => 'Can I request a custom quotation?',

        // Samples
        'buyer.samples_available' => 'Are samples available?',
        'buyer.trial_quantity' => 'Can I order a trial quantity first?',

        // Seller Support
        'buyer.similar_products' => 'Can you recommend similar products?',
        'buyer.best_sellers' => 'What are your best-selling products?',
        'buyer.complementary_products' => 'Can you suggest complementary products?',

        // Order Support
        'buyer.confirm_order' => 'Can you confirm my order?',
        'buyer.modify_order' => 'Can I modify my order before shipment?',
        'buyer.cancel_order' => 'Can I cancel my order?',
        'buyer.reorder' => 'I would like to reorder.',
        'buyer.refund' => 'I would like a refund.',
    ];

    /** Seller canned responses. */
    public const SELLER_MESSAGES = [
        'seller.thanks_interest' => 'Thank you for your interest.',
        'seller.order_received' => 'Your order has been received.',
        'seller.order_preparing' => 'Your order is being prepared.',
        'seller.order_shipped' => 'Your order has shipped.',
        'seller.order_delivered' => 'Your order has been delivered.',
        'seller.out_of_stock' => 'This item is temporarily out of stock.',
        'seller.restocked' => 'We have restocked this product.',
        'seller.thanks_purchase' => 'Thank you for your purchase.',
        'seller.reorder_prompt' => 'Would you like to reorder?',
        'seller.appreciate_feedback' => 'We appreciate your feedback.',
    ];

    /** Automated system responses - never chosen by a user, sent by the platform. */
    public const SYSTEM_MESSAGES = [
        'system.greeting' => 'Thank you for reaching out. We will be with you shortly.',
        'system.contact_blocked' => 'For your safety, phone numbers, email addresses, social media handles, external payment links, and website links cannot be shared. Please complete all communication and payments through Seller Africa Marketplace.',
        'system.pay_outside_blocked' => 'Seller Africa Buyer Protection only applies to orders completed through our secure checkout. Please complete payment on the platform.',
        'system.message_not_approved' => 'We are sorry, we cannot approve this message.',
    ];

    /** Quick action buttons shown to the buyer, with emoji + which message key they trigger. */
    public const QUICK_ACTIONS = [
        ['emoji' => '🛒', 'label' => 'Buy Now', 'action' => 'buy_now'],
        ['emoji' => '❤️', 'label' => 'Save Product', 'action' => 'save_product'],
        ['emoji' => '📦', 'label' => 'Ask About Stock', 'key' => 'buyer.stock'],
        ['emoji' => '🚚', 'label' => 'Shipping', 'key' => 'buyer.ship_how_soon'],
        ['emoji' => '📏', 'label' => 'Product Size', 'key' => 'buyer.package_sizes'],
        ['emoji' => '💰', 'label' => 'Wholesale Price', 'key' => 'buyer.wholesale_pricing'],
        ['emoji' => '🌍', 'label' => 'Country of Origin', 'key' => 'buyer.origin'],
        ['emoji' => '🌱', 'label' => 'Organic?', 'key' => 'buyer.organic'],
        ['emoji' => '⭐', 'label' => 'Reviews', 'action' => 'view_reviews'],
        ['emoji' => '🔁', 'label' => 'Reorder', 'key' => 'buyer.reorder'],
    ];

    private static function db(): Database
    {
        return db();
    }

    /**
     * Find or create the one conversation thread between this buyer and
     * this vendor (optionally tied to a specific product).
     */
    public static function startOrGetConversation(int $buyerId, int $vendorId, ?int $productId = null): int
    {
        self::ensureSchema();

        $existing = self::db()->fetch(
            'SELECT id FROM chat_conversations WHERE buyer_id = ? AND vendor_id = ? AND (product_id <=> ?) LIMIT 1',
            [$buyerId, $vendorId, $productId]
        );

        if ($existing) {
            return (int)$existing['id'];
        }

        self::db()->fetch(
            'INSERT INTO chat_conversations (buyer_id, vendor_id, product_id, status) VALUES (?, ?, ?, ?)',
            [$buyerId, $vendorId, $productId, 'open']
        );

        $row = self::db()->fetch('SELECT LAST_INSERT_ID() AS id');
        $conversationId = (int)$row['id'];

        return $conversationId;
    }

    /**
     * Post a message from the buyer. $messageKey MUST exist in
     * BUYER_MESSAGES - if it does not, nothing is sent and this returns
     * false. There is no way to store arbitrary text through this method.
     */
    public static function sendBuyerMessage(int $conversationId, string $messageKey): bool
    {
        self::ensureSchema();

        if (!array_key_exists($messageKey, self::BUYER_MESSAGES)) {
            self::insertMessage($conversationId, 'system', 'system.message_not_approved');

            return false;
        }

        $existingCount = self::db()->fetch(
            'SELECT COUNT(*) AS total FROM chat_messages WHERE conversation_id = ?',
            [$conversationId]
        );
        $isFirstMessage = (int)($existingCount['total'] ?? 0) === 0;

        self::insertMessage($conversationId, 'buyer', $messageKey);

        if ($isFirstMessage) {
            self::insertMessage($conversationId, 'system', 'system.greeting');
        }

        return true;
    }

    /**
     * Post a message from the seller. Same guarantee as above but
     * checked against SELLER_MESSAGES instead.
     */
    public static function sendSellerMessage(int $conversationId, string $messageKey): bool
    {
        self::ensureSchema();

        if (!array_key_exists($messageKey, self::SELLER_MESSAGES)) {
            self::insertMessage($conversationId, 'system', 'system.message_not_approved');

            return false;
        }

        self::insertMessage($conversationId, 'seller', $messageKey);

        return true;
    }

    private static function insertMessage(int $conversationId, string $senderType, string $messageKey): void
    {
        self::db()->fetch(
            'INSERT INTO chat_messages (conversation_id, sender_type, message_key) VALUES (?, ?, ?)',
            [$conversationId, $senderType, $messageKey]
        );
        self::db()->fetch('UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?', [$conversationId]);
    }

    /**
     * Fetch the full conversation history, resolving each message_key
     * back to its real display text via the fixed lists above.
     */
    public static function history(int $conversationId): array
    {
        self::ensureSchema();

        $rows = self::db()->fetchAll(
            'SELECT sender_type, message_key, created_at FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC',
            [$conversationId]
        );

        return array_map(static function (array $row): array {
            $text = match ($row['sender_type']) {
                'buyer' => self::BUYER_MESSAGES[$row['message_key']] ?? '',
                'seller' => self::SELLER_MESSAGES[$row['message_key']] ?? '',
                'system' => self::SYSTEM_MESSAGES[$row['message_key']] ?? '',
                default => '',
            };

            return [
                'sender' => (string)$row['sender_type'],
                'text' => $text,
                'time' => (string)$row['created_at'],
            ];
        }, $rows);
    }

    /** All conversations for a given buyer (for their inbox view). */
    public static function conversationsForBuyer(int $buyerId): array
    {
        self::ensureSchema();

        return self::db()->fetchAll(
            'SELECT cc.id, cc.vendor_id, cc.product_id, cc.updated_at, cc.status,
                    v.store_name, p.name AS product_name,
                    lm.sender_type AS last_sender, lm.message_key AS last_message_key, lm.created_at AS last_message_at
             FROM chat_conversations cc
             LEFT JOIN vendors v ON v.id = cc.vendor_id
             LEFT JOIN products p ON p.id = cc.product_id
             LEFT JOIN chat_messages lm ON lm.id = (
                SELECT cm.id FROM chat_messages cm
                WHERE cm.conversation_id = cc.id
                ORDER BY cm.id DESC
                LIMIT 1
             )
             WHERE cc.buyer_id = ?
             ORDER BY cc.updated_at DESC',
            [$buyerId]
        );
    }

    /** All conversations for a given vendor (for their inbox view). */
    public static function conversationsForVendor(int $vendorId): array
    {
        self::ensureSchema();

        return self::db()->fetchAll(
            'SELECT cc.id, cc.buyer_id, cc.product_id, cc.updated_at, cc.status,
                    u.display_name, u.first_name, u.last_name, u.email, p.name AS product_name,
                    lm.sender_type AS last_sender, lm.message_key AS last_message_key, lm.created_at AS last_message_at
             FROM chat_conversations cc
             LEFT JOIN users u ON u.id = cc.buyer_id
             LEFT JOIN products p ON p.id = cc.product_id
             LEFT JOIN chat_messages lm ON lm.id = (
                SELECT cm.id FROM chat_messages cm
                WHERE cm.conversation_id = cc.id
                ORDER BY cm.id DESC
                LIMIT 1
             )
             WHERE cc.vendor_id = ?
             ORDER BY cc.updated_at DESC',
            [$vendorId]
        );
    }

    public static function messageText(?string $senderType, ?string $messageKey): string
    {
        return match ((string)$senderType) {
            'buyer' => self::BUYER_MESSAGES[(string)$messageKey] ?? '',
            'seller' => self::SELLER_MESSAGES[(string)$messageKey] ?? '',
            'system' => self::SYSTEM_MESSAGES[(string)$messageKey] ?? '',
            default => '',
        };
    }

    public static function ensureSchema(): void
    {
        self::db()->query(
            "CREATE TABLE IF NOT EXISTS chat_conversations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                buyer_id BIGINT UNSIGNED NOT NULL,
                vendor_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                status ENUM('open','closed') NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_chat_thread (buyer_id, vendor_id, product_id),
                INDEX idx_chat_vendor_updated (vendor_id, updated_at),
                INDEX idx_chat_buyer_updated (buyer_id, updated_at),
                INDEX idx_chat_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::db()->query(
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_type ENUM('buyer','seller','system') NOT NULL,
                message_key VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat_messages_conversation (conversation_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
