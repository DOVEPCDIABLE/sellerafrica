<?php $theme = $theme ?? ['mode' => 'light']; ?>
<aside class="admin-theme-panel" data-theme-panel hidden>
    <script type="application/json" id="admin-theme-config"><?= e(json_encode($theme, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></script>
</aside>
