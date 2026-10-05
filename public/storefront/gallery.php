<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;

$brand = app_branding();
$albums = table_exists('content_albums') ? db()->fetchAll(
    "SELECT a.*, COALESCE(item_stats.item_count, 0) AS item_count
     FROM content_albums a
     LEFT JOIN (
        SELECT album_id, COUNT(*) AS item_count
        FROM content_gallery_items
        WHERE status = 'published'
        GROUP BY album_id
     ) item_stats ON item_stats.album_id = a.id
     WHERE a.status = 'published'
     ORDER BY a.sort_order ASC, a.created_at DESC"
) : [];
$items = table_exists('content_gallery_items') ? db()->fetchAll(
    "SELECT g.*, a.title AS album_title
     FROM content_gallery_items g
     LEFT JOIN content_albums a ON a.id = g.album_id
     WHERE g.status = 'published'
     ORDER BY COALESCE(a.sort_order, 9999) ASC, g.sort_order ASC, g.created_at DESC
     LIMIT 60"
) : [];

render_layout('storefront-home', 'storefront/pages/gallery.php', [
    'title' => 'Gallery | ' . ($brand['name'] ?? 'Seller Africa'),
    'metaDescription' => 'Explore Seller Africa gallery with pictures and videos from our marketplace, vendors, community, and trade events.',
    'canonical' => app_url('gallery'),
    'albums' => $albums,
    'items' => $items,
    'cartCount' => (new CartService())->count(),
]);
