<?php
$notifications = $notifications ?? [];
$toastPayload = consume_toasts();
?>
<div id="toast-root" class="toast-root" data-toasts='<?= e(json_encode($toastPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'></div>
<script type="application/json" id="admin-notification-data"><?= e(json_encode($notifications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></script>
