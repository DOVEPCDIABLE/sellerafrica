<?php
$brand = app_branding();
$logo = trim((string)($brand['logo'] ?? ''));
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$albums = is_array($albums ?? null) ? $albums : [];
$items = is_array($items ?? null) ? $items : [];
$asset = static fn (?string $path): string => $path ? e(str_starts_with($path, 'http') ? $path : app_url($path)) : '';
$galleryText = static function (?string $text): string {
    $text = (string)$text;
    $text = preg_replace('/\balbums\b/i', 'Galleries', $text) ?? $text;
    return preg_replace('/\balbum\b/i', 'Gallery', $text) ?? $text;
};
?>
<style>
.sa-gallery{--green:#00684f;--ink:#111827;--muted:#667276;--line:#e2e8e6;--wash:#f4fbf7;font-family:"DM Sans",system-ui,sans-serif;color:var(--ink);background:#fff}.sa-gallery *{box-sizing:border-box}.sa-gallery a{text-decoration:none;color:inherit}.sa-gallery-wrap{width:min(1440px,100% - clamp(28px,6vw,104px));margin:auto}.sa-gallery-nav{min-height:88px;display:flex;align-items:center;justify-content:space-between;gap:24px}.sa-gallery-logo img{max-width:170px;max-height:58px}.sa-gallery-links{display:flex;gap:30px;font-weight:800;color:#5f6a6d}.sa-gallery-links a.active{color:var(--green)}.sa-gallery-hero{padding:70px 0 46px}.sa-gallery-eyebrow{color:var(--green);font-size:13px;font-weight:900;text-transform:uppercase}.sa-gallery h1{max-width:900px;margin:12px 0 16px;font-size:clamp(42px,6vw,76px);line-height:1;font-weight:900;letter-spacing:0}.sa-gallery p{color:var(--muted);font-size:17px;line-height:1.65}.sa-albums{background:var(--wash);padding:54px 0}.sa-album-grid,.sa-media-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.sa-card{background:#fff;border:1px solid var(--line);border-radius:8px;overflow:hidden;box-shadow:0 14px 40px rgba(17,24,39,.06)}.sa-card img,.sa-card video{width:100%;aspect-ratio:4/3;object-fit:cover;background:#edf4f1;display:block}.sa-card-body{padding:20px}.sa-card h2,.sa-card h3{margin:0 0 8px;letter-spacing:0}.sa-card small{color:var(--green);font-weight:900}.sa-media{padding:58px 0 76px}.sa-empty{border:1px dashed var(--line);border-radius:8px;padding:34px;background:#fff}@media(max-width:960px){.sa-album-grid,.sa-media-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sa-gallery-links{display:none}}@media(max-width:640px){.sa-album-grid,.sa-media-grid{grid-template-columns:1fr}}
</style>
<div class="sa-gallery">
    <header class="sa-gallery-wrap sa-gallery-nav">
        <a class="sa-gallery-logo" href="<?= e(app_url('store')) ?>"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($brandName) ?>"><?php else: ?><strong><?= e($brandName) ?></strong><?php endif; ?></a>
        <nav class="sa-gallery-links"><a href="<?= e(app_url('store')) ?>">Home</a><a href="<?= e(app_url('shop')) ?>">Marketplace</a><a class="active" href="<?= e(app_url('gallery')) ?>">Gallery</a><a href="<?= e(app_url('blog-news')) ?>">Blog & News</a><a href="<?= e(app_url('contact')) ?>">Contact</a></nav>
    </header>
    <main>
        <section class="sa-gallery-wrap sa-gallery-hero">
            <div class="sa-gallery-eyebrow">Seller Africa Gallery</div>
            <h1> Gallery</h1>
            <p>Explore pictures and videos that show the people, products, events, and trade stories behind Seller Africa.</p>
        </section>
        <section class="sa-albums">
            <div class="sa-gallery-wrap">
                <div class="sa-gallery-eyebrow">Galleries</div>
                <div class="sa-album-grid">
                    <?php foreach ($albums as $album): ?>
                        <article class="sa-card">
                            <?php if ($asset($album['cover_media_url'] ?? '') !== ''): ?><img src="<?= $asset($album['cover_media_url']) ?>" alt="<?= e($galleryText($album['title'] ?? '')) ?>" loading="lazy"><?php endif; ?>
                            <div class="sa-card-body"><small><?= number_format((int)$album['item_count']) ?> media items</small><h2><?= e($galleryText($album['title'] ?? '')) ?></h2><p><?= e($galleryText($album['description'] ?: 'A Seller Africa gallery featuring photos and videos from our growing marketplace.')) ?></p></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($albums === []): ?><div class="sa-empty"><h2>Galleries are coming soon.</h2><p>New photo and video collections will appear here once published by the Seller Africa team.</p></div><?php endif; ?>
                </div>
            </div>
        </section>
        <section class="sa-media">
            <div class="sa-gallery-wrap">
                <div class="sa-gallery-eyebrow">Photos & Videos</div>
                <div class="sa-media-grid">
                    <?php foreach ($items as $item): ?>
                        <article class="sa-card">
                            <?php if (($item['media_type'] ?? 'image') === 'video'): ?><video controls preload="metadata" poster="<?= $asset($item['thumbnail_url'] ?? '') ?>"><source src="<?= $asset($item['media_url'] ?? '') ?>"></video><?php else: ?><img src="<?= $asset($item['media_url'] ?? '') ?>" alt="<?= e($galleryText($item['title'] ?? '')) ?>" loading="lazy"><?php endif; ?>
                            <div class="sa-card-body"><small><?= e($galleryText($item['album_title'] ?: 'Gallery')) ?></small><h3><?= e($galleryText($item['title'] ?? '')) ?></h3><?php if (!empty($item['caption'])): ?><p><?= e($galleryText($item['caption'])) ?></p><?php endif; ?></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?><div class="sa-empty"><h2>Gallery media is coming soon.</h2><p>Published images and videos will be displayed here.</p></div><?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <?php include __DIR__ . '/../partials/standard-footer.php'; ?>
</div>
