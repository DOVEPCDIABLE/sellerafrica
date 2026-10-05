<?php
$contentData = $contentData ?? [];
$rows = $contentData['rows'] ?? [];
$edit = $contentData['edit'] ?? null;
$albums = $contentData['albums'] ?? [];
$showForm = (bool)($contentData['showForm'] ?? false);
$links = ['albums' => 'Albums', 'gallery' => 'Gallery', 'blog-posts' => 'Blog & News'];
$value = static fn (string $key, mixed $default = ''): string => e($edit[$key] ?? $default);
$asset = static fn (?string $path): string => $path ? e(str_starts_with($path, 'http') ? $path : app_url($path)) : '';
?>
<section class="settings-shell">
    <aside class="settings-index panel">
        <div class="panel-header"><div><h2>Content</h2><p>Albums, gallery media, and blog/news articles.</p></div></div>
        <nav class="settings-nav" aria-label="Content pages">
            <?php foreach ($links as $key => $label): ?><a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </nav>
    </aside>
    <div class="settings-content">
        <section class="metric-grid settings-metrics">
            <div class="metric"><span>Albums</span><strong><?= number_format((int)($contentData['stats']['albums'] ?? 0)) ?></strong><small>Curated collections</small></div>
            <div class="metric"><span>Media</span><strong><?= number_format((int)($contentData['stats']['gallery'] ?? 0)) ?></strong><small>Images and videos</small></div>
            <div class="metric"><span>Posts</span><strong><?= number_format((int)($contentData['stats']['posts'] ?? 0)) ?></strong><small><?= number_format((int)($contentData['stats']['published_posts'] ?? 0)) ?> published</small></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div><h2><?= e($links[$view] ?? 'Content') ?></h2><p>Create, publish, and manage storefront content.</p></div>
                <div class="panel-actions">
                    <?php if ($view === 'blog-posts'): ?><a class="btn secondary" href="<?= e(app_url('dashboard/data-migration')) ?>">Migrate Blogs</a><?php endif; ?>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/' . $view . '?new=1')) ?>"><?= $view === 'blog-posts' ? 'Add Post' : ($view === 'gallery' ? 'Add Media' : 'Add Album') ?></a>
                </div>
            </div>

            <?php if ($showForm && $view === 'albums'): ?>
                <form class="settings-form taxonomy-form" method="post" enctype="multipart/form-data" data-ajax-content-form>
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="action" value="save_content_album"><input type="hidden" name="album_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Album Title</span><input name="title" required value="<?= $value('title') ?>"></label>
                        <label class="settings-field"><span>Slug</span><input name="slug" value="<?= $value('slug') ?>"></label>
                        <label class="settings-field"><span>Cover Image</span><input type="file" name="cover_media" accept="image/png,image/jpeg,image/webp"></label>
                        <label class="settings-field"><span>Album Media</span><input type="file" name="album_media[]" accept="image/png,image/jpeg,image/webp,video/mp4,video/webm,video/quicktime" multiple data-max-files="10"><small>Select up to 10 images or videos.</small></label>
                        <label class="settings-field"><span>Status</span><select name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></label>
                        <label class="settings-field"><span>Sort Order</span><input type="number" name="sort_order" value="<?= $value('sort_order', '0') ?>"></label>
                        <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="4"><?= $value('description') ?></textarea></label>
                    </div>
                    <button class="btn" type="submit">Save Album</button>
                    <div class="content-upload-status" data-upload-status></div>
                </form>
            <?php elseif ($showForm && $view === 'gallery'): ?>
                <form class="settings-form taxonomy-form" method="post" enctype="multipart/form-data" data-ajax-content-form>
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="action" value="save_gallery_item"><input type="hidden" name="item_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Title</span><input name="title" required value="<?= $value('title') ?>"></label>
                        <label class="settings-field"><span>Album</span><select name="album_id"><option value="">No album</option><?php foreach ($albums as $album): ?><option value="<?= e($album['id']) ?>" <?= (int)($edit['album_id'] ?? 0) === (int)$album['id'] ? 'selected' : '' ?>><?= e($album['title']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Media Type</span><select name="media_type"><option value="image" <?= ($edit['media_type'] ?? 'image') === 'image' ? 'selected' : '' ?>>Image</option><option value="video" <?= ($edit['media_type'] ?? '') === 'video' ? 'selected' : '' ?>>Video</option></select></label>
                        <label class="settings-field"><span>Images / Videos</span><input type="file" name="media_file[]" accept="image/png,image/jpeg,image/webp,video/mp4,video/webm,video/quicktime" multiple data-max-files="10"><small>Select up to 10 files. Use Image type for images and Video type for videos.</small></label>
                        <label class="settings-field"><span>Video Thumbnail</span><input type="file" name="thumbnail_file" accept="image/png,image/jpeg,image/webp"></label>
                        <label class="settings-field"><span>Status</span><select name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></label>
                        <label class="settings-field"><span>Sort Order</span><input type="number" name="sort_order" value="<?= $value('sort_order', '0') ?>"></label>
                        <label class="settings-field span-2"><span>Caption</span><textarea name="caption" rows="3"><?= $value('caption') ?></textarea></label>
                    </div>
                    <button class="btn" type="submit">Save Media</button>
                    <div class="content-upload-status" data-upload-status></div>
                </form>
            <?php elseif ($showForm && $view === 'blog-posts'): ?>
                <form class="settings-form taxonomy-form" method="post" enctype="multipart/form-data" data-ajax-content-form>
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="action" value="save_content_post"><input type="hidden" name="post_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Title</span><input name="title" required value="<?= $value('title') ?>"></label>
                        <label class="settings-field"><span>Slug</span><input name="slug" value="<?= $value('slug') ?>"></label>
                        <label class="settings-field"><span>Category</span><input name="category" value="<?= $value('category') ?>" placeholder="Marketplace News"></label>
                        <label class="settings-field"><span>Author</span><input name="author_name" value="<?= $value('author_name', 'Seller Africa') ?>"></label>
                        <label class="settings-field"><span>Status</span><select name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option><option value="archived" <?= ($edit['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option></select></label>
                        <label class="settings-field"><span>Published At</span><input type="datetime-local" name="published_at" value="<?= e(!empty($edit['published_at']) ? str_replace(' ', 'T', substr((string)$edit['published_at'], 0, 16)) : '') ?>"></label>
                        <label class="settings-field"><span>Featured Image</span><input type="file" name="featured_image" accept="image/png,image/jpeg,image/webp"></label>
                        <label class="settings-field span-2"><span>Excerpt</span><textarea name="excerpt" rows="3"><?= $value('excerpt') ?></textarea></label>
                        <label class="settings-field span-2"><span>Body</span><textarea name="body" rows="8"><?= $value('body') ?></textarea></label>
                    </div>
                    <button class="btn" type="submit">Save Post</button>
                    <div class="content-upload-status" data-upload-status></div>
                </form>
            <?php endif; ?>

            <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search content">
                <button class="btn secondary" type="submit">Search</button>
            </form>

            <div class="admin-table-wrap"><table class="admin-table"><thead><tr>
                <?php if ($view === 'blog-posts'): ?><th>Post</th><th>Category</th><th>Status</th><th>Published</th><th></th>
                <?php elseif ($view === 'gallery'): ?><th>Media</th><th>Album</th><th>Type</th><th>Status</th><th></th>
                <?php else: ?><th>Album</th><th>Cover</th><th>Items</th><th>Status</th><th></th><?php endif; ?>
            </tr></thead><tbody>
            <?php foreach ($rows as $row): ?><tr>
                <?php if ($view === 'blog-posts'): ?>
                    <td><strong><?= e($row['title']) ?></strong><small><?= e($row['slug']) ?></small></td><td><?= e($row['category'] ?: '-') ?></td><td><span class="settings-badge status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td><td><?= e($row['published_at'] ?: '-') ?></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/blog-posts?edit=' . (int)$row['id'])) ?>">Edit</a></td>
                <?php elseif ($view === 'gallery'): ?>
                    <td><strong><?= e($row['title']) ?></strong><small><?= e($row['media_url']) ?></small></td><td><?= e($row['album_title'] ?: 'No album') ?></td><td><?= e($row['media_type']) ?></td><td><span class="settings-badge status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/gallery?edit=' . (int)$row['id'])) ?>">Edit</a></td>
                <?php else: ?>
                    <td><strong><?= e($row['title']) ?></strong><small><?= e($row['slug']) ?></small></td><td><?php if ($asset($row['cover_media_url'] ?? '') !== ''): ?><img src="<?= $asset($row['cover_media_url']) ?>" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:6px"><?php else: ?>-<?php endif; ?></td><td><?= number_format((int)$row['item_count']) ?></td><td><span class="settings-badge status-<?= e($row['status']) ?>"><?= e($row['status']) ?></span></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/albums?edit=' . (int)$row['id'])) ?>">Edit</a></td>
                <?php endif; ?>
            </tr><?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="5">No content found.</td></tr><?php endif; ?>
            </tbody></table></div>

            <?php if ($edit): ?>
                <form method="post" action="<?= e(app_url('dashboard/' . $view)) ?>" onsubmit="return confirm('Delete this content record?');" style="margin-top:16px">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="action" value="delete_content_record">
                    <input type="hidden" name="content_type" value="<?= $view === 'albums' ? 'album' : ($view === 'gallery' ? 'gallery' : 'post') ?>"><input type="hidden" name="record_id" value="<?= e($edit['id']) ?>">
                    <button class="btn secondary" type="submit">Delete</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</section>
<style>
    .content-upload-status{margin-top:12px;color:#475569;font-weight:700}
    .content-upload-status progress{width:min(360px,100%);height:12px;vertical-align:middle;margin-right:10px}
</style>
<script>
document.querySelectorAll('[data-ajax-content-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const status = form.querySelector('[data-upload-status]');
        const submit = form.querySelector('button[type="submit"]');
        const fileInputs = form.querySelectorAll('input[type="file"][multiple][data-max-files]');
        for (const input of fileInputs) {
            const max = Number(input.dataset.maxFiles || 10);
            if (input.files && input.files.length > max) {
                if (status) status.textContent = `Select ${max} files or fewer.`;
                input.focus();
                return;
            }
        }

        const progress = document.createElement('progress');
        progress.max = 100;
        progress.value = 0;
        if (status) {
            status.textContent = '';
            status.append(progress, document.createTextNode(' Uploading...'));
        }
        if (submit) submit.disabled = true;

        const request = new XMLHttpRequest();
        request.open('POST', form.getAttribute('action') || window.location.href);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.addEventListener('progress', (progressEvent) => {
            if (progressEvent.lengthComputable) {
                progress.value = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            }
        });
        request.addEventListener('load', () => {
            let payload = {};
            let fallbackMessage = '';
            try {
                payload = JSON.parse(request.responseText || '{}');
            } catch (error) {
                fallbackMessage = (request.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (fallbackMessage.length > 180) fallbackMessage = fallbackMessage.slice(0, 180) + '...';
            }
            if (request.status >= 200 && request.status < 300 && payload.ok) {
                if (status) status.textContent = payload.message || 'Saved.';
                window.setTimeout(() => {
                    if (payload.redirectPath) {
                        const currentUrl = new URL(window.location.href);
                        let dashboardBase = currentUrl.pathname;
                        if (/\/dashboard\.php$/i.test(dashboardBase)) {
                            dashboardBase = dashboardBase.replace(/\/dashboard\.php$/i, '/dashboard/');
                        } else if (/\/dashboard\/[^\/]*\/?$/i.test(dashboardBase)) {
                            dashboardBase = dashboardBase.replace(/\/dashboard\/[^\/]*\/?$/i, '/dashboard/');
                        } else {
                            dashboardBase = dashboardBase.replace(/\/?$/, '/');
                        }
                        window.location.href = new URL(payload.redirectPath, currentUrl.origin + dashboardBase).toString();
                    } else if (payload.redirect) {
                        window.location.href = payload.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 700);
                return;
            }
            if (status) status.textContent = payload.message || fallbackMessage || `Upload failed with HTTP ${request.status}. Please try again.`;
            if (submit) submit.disabled = false;
        });
        request.addEventListener('error', () => {
            if (status) status.textContent = 'Upload failed. Please try again.';
            if (submit) submit.disabled = false;
        });
        request.send(new FormData(form));
    });
});
</script>
