<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? app_url('chat');
    redirect('login');
}

$currentUserId = (int)$_SESSION['user_id'];

// Figure out which conversation we're looking at.
$conversationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($conversationId === 0) {
    // Starting a fresh chat from a product page: ?vendor=123&product=456
    $vendorId = (int)($_GET['vendor'] ?? 0);
    $productId = isset($_GET['product']) ? (int)$_GET['product'] : null;

    if ($vendorId <= 0) {
        redirect('shop');
    }

    $conversationId = \App\ChatService::startOrGetConversation($currentUserId, $vendorId, $productId);
}

// Confirm this conversation belongs to the current user, either as the
// buyer or as the vendor on the other end.
$conversation = db()->fetch('SELECT * FROM chat_conversations WHERE id = ?', [$conversationId]);

if (!$conversation) {
    redirect('shop');
}

$vendorRow = db()->fetch('SELECT id FROM vendors WHERE user_id = ?', [$currentUserId]);
$currentVendorId = $vendorRow ? (int)$vendorRow['id'] : 0;

$isBuyerHere = (int)$conversation['buyer_id'] === $currentUserId;
$isSellerHere = $currentVendorId > 0 && (int)$conversation['vendor_id'] === $currentVendorId;

if (!$isBuyerHere && !$isSellerHere) {
    // This conversation belongs to neither party viewing it - not allowed.
    redirect('shop');
}

// Handle sending a message.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $messageKey = (string)($_POST['message_key'] ?? '');
    $sent = $isBuyerHere
        ? \App\ChatService::sendBuyerMessage($conversationId, $messageKey)
        : \App\ChatService::sendSellerMessage($conversationId, $messageKey);

    if (!$sent) {
        flash('error', \App\ChatService::SYSTEM_MESSAGES['system.message_not_approved']);
    }

    // Redirect after POST so refreshing the page never resends.
    redirect('chat?id=' . $conversationId);
}

$toasts = consume_toasts();

$messages = \App\ChatService::history($conversationId);
$brand = app_branding();
$otherPartyName = $isBuyerHere
    ? (string)(db()->fetch('SELECT store_name FROM vendors WHERE id = ?', [$conversation['vendor_id']])['store_name'] ?? 'Seller')
    : (string)(db()->fetch('SELECT display_name FROM users WHERE id = ?', [$conversation['buyer_id']])['display_name'] ?? 'Buyer');

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat with <?= e($otherPartyName) ?> | <?= e((string)($brand['name'] ?? 'Seller Africa')) ?></title>
<style>
  * { box-sizing:border-box; }
  body { margin:0; background:#f8faf6; font-family:"Aileron","DM Sans",Arial,sans-serif; color:#202326; }
  .sa-chat-shell { max-width:640px; margin:0 auto; min-height:100vh; min-height:100dvh; display:flex; flex-direction:column; background:#fff; }
  .sa-chat-header { display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e5e5; }
  .sa-chat-header a { color:#006b52; text-decoration:none; font-weight:800; font-size:14px; }
  .sa-chat-header__back { flex-shrink:0; }
  .sa-chat-header__title { flex:1; min-width:0; }
  .sa-chat-home { min-height:36px; border:1px solid #006b52; border-radius:999px; padding:0 12px; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; }
  .sa-chat-header strong { font-size:16px; }
  .sa-chat-header p { margin:2px 0 0; font-size:12px; color:#8b9491; }
  .sa-chat-notice { margin:14px 20px 0; padding:10px 14px; border-radius:10px; background:#fff7e6; color:#8a5a00; font-size:12.5px; line-height:1.5; }
  .sa-chat-messages { flex:1; overflow-y:auto; padding:18px 20px; display:flex; flex-direction:column; gap:10px; }
  .sa-chat-bubble { max-width:78%; padding:11px 15px; border-radius:16px; font-size:14.5px; line-height:1.5; }
  .sa-chat-bubble.is-mine { align-self:flex-end; background:#006b52; color:#fff; border-bottom-right-radius:4px; }
  .sa-chat-bubble.is-theirs { align-self:flex-start; background:#f3f7f5; color:#202326; border-bottom-left-radius:4px; }
  .sa-chat-bubble.is-system { align-self:center; background:#fdecec; color:#a13a3a; font-size:13px; text-align:center; }
  .sa-chat-time { display:block; margin-top:4px; font-size:10.5px; opacity:.65; }
  .sa-chat-actions { border-top:1px solid #e5e5e5; padding:14px 16px; }
  .sa-chat-actions p { margin:0 0 10px; font-size:12px; font-weight:800; color:#8b9491; text-transform:uppercase; letter-spacing:.04em; }
  .sa-chat-actions__grid { display:flex; flex-wrap:wrap; gap:8px; max-height:220px; overflow-y:auto; }
  .sa-chat-actions__grid button { border:1px solid #e5e5e5; border-radius:999px; background:#fff; padding:9px 14px; font-size:13px; font-weight:600; color:#202326; cursor:pointer; }
  .sa-chat-actions__grid button:hover { background:#f3fbf8; border-color:#006b52; color:#006b52; }
  @media (max-width:520px) { .sa-chat-header { align-items:flex-start; flex-wrap:wrap; } .sa-chat-home { width:100%; } }
</style>
</head>
<body>
  <div class="sa-chat-shell">
    <header class="sa-chat-header">
      <a class="sa-chat-header__back" href="<?= e(app_url($isBuyerHere ? 'buyer' : 'vendor')) ?>">&larr;</a>
      <div class="sa-chat-header__title">
        <strong>Chat with <?= e($otherPartyName) ?></strong>
        <p>Guided, secure conversation - Seller Africa Marketplace</p>
      </div>
      <a class="sa-chat-home" href="<?= e(app_url('')) ?>">Back to homepage</a>
    </header>

    <div class="sa-chat-notice">
      For your safety, this chat only allows pre-approved questions and responses. Contact details and off-platform payments cannot be shared.
    </div>

    <div class="sa-chat-messages">
      <?php if ($messages === []): ?>
        <p style="text-align:center;color:#8b9491;font-size:13.5px;">No messages yet. Pick a question below to start.</p>
      <?php endif; ?>
      <?php foreach ($messages as $message): ?>
        <?php
          $mine = ($isBuyerHere && $message['sender'] === 'buyer') || ($isSellerHere && $message['sender'] === 'seller');
          $bubbleClass = $message['sender'] === 'system' ? 'is-system' : ($mine ? 'is-mine' : 'is-theirs');
        ?>
        <div class="sa-chat-bubble <?= e($bubbleClass) ?>">
          <?= e($message['text']) ?>
          <span class="sa-chat-time"><?= e(date('M j, g:i a', strtotime($message['time']))) ?></span>
        </div>
      <?php endforeach; ?>
      <?php foreach ($toasts as $toast): ?>
        <div class="sa-chat-bubble is-system"><?= e($toast['message']) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="sa-chat-actions">
      <p><?= $isBuyerHere ? 'Ask a question' : 'Send a response' ?></p>
      <div class="sa-chat-actions__grid">
        <?php $options = $isBuyerHere ? \App\ChatService::BUYER_MESSAGES : \App\ChatService::SELLER_MESSAGES; ?>
        <?php foreach ($options as $key => $label): ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="message_key" value="<?= e($key) ?>">
            <button type="submit"><?= e($label) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</body>
</html>
