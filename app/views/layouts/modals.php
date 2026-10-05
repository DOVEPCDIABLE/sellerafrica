<?php foreach (($modals ?? []) as $modal): ?>
    <div class="admin-modal" id="<?= e($modal['id'] ?? '') ?>" hidden>
        <?= $modal['html'] ?? '' ?>
    </div>
<?php endforeach; ?>
