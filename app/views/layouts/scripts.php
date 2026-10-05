<?php
$scripts = $scripts ?? [];
?>
<script src="<?= e(asset('js/toasts.js')) ?>"></script>
<script src="<?= e(asset('js/admin-shell.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
    <script src="<?= e((string)$script) ?>"></script>
<?php endforeach; ?>
<?php app_render_chat_widget(); ?>
