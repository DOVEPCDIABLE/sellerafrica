<?php
$brand = app_branding();
$title = $title ?? $brand['name'];
$bodyClass = $bodyClass ?? '';
$adminCssPath = APP_ROOT . '/public/assets/css/admin.css';
$adminCssVersion = is_file($adminCssPath) ? (string)filemtime($adminCssPath) : '1';
$seoTitle = trim((string)$title . ' | ' . (string)$brand['name']);
$brandName = trim((string)($brand['name'] ?? 'Seller Africa')) ?: 'Seller Africa';
$brandLogo = trim((string)($brand['logo'] ?? ''));
$authCartCount = 0;
try {
    if (class_exists('\App\CartService')) {
        $authCartCount = (new \App\CartService())->count();
    }
} catch (\Throwable) {
    $authCartCount = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= \App\SeoService::tags($seoTitle, $brand['name'] . ' marketplace ERP') ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css') . '?v=' . $adminCssVersion) ?>">
    <style>
        .sa-auth-public-header {
            --green: #006b52;
            --mint: #e7fbf5;
            --ink: #202326;
            --muted: #5f6b67;
            --radius: .625rem;
            --page-max: 1536px;
            --page-gutter: clamp(32px, 6.25vw, 128px);
            font-family: "DM Sans", system-ui, -apple-system, sans-serif;
            background: #fff;
            color: var(--muted);
        }

        .sa-auth-public-header *,
        .sa-auth-public-header *::before,
        .sa-auth-public-header *::after {
            box-sizing: border-box;
        }

        .sa-auth-public-header a {
            color: inherit;
            text-decoration: none;
        }

        .sa-auth-public-header__announce {
            min-height: 38px;
            display: grid;
            place-items: center;
            padding: 6px 16px;
            background: var(--mint);
            color: #153d35;
            font-weight: 700;
            text-align: center;
        }

        .sa-auth-public-header__bar {
            position: sticky;
            top: 0;
            z-index: 60;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid rgba(231, 228, 220, .55);
            backdrop-filter: blur(14px);
        }

        .sa-auth-public-header__nav {
            width: min(var(--page-max), calc(100% - var(--page-gutter)));
            min-height: 92px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 32px;
        }

        .sa-auth-public-header__logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--green);
            font-size: 20px;
            font-weight: 900;
            line-height: 1;
        }

        .sa-auth-public-header__logo img {
            display: block;
            width: auto;
            max-width: 178px;
            max-height: 58px;
            object-fit: contain;
        }

        .sa-auth-public-header__mark {
            width: 38px;
            height: 38px;
            display: inline-block;
            border: 5px solid #ffb13d;
            border-radius: calc(var(--radius) * 1.2);
            box-shadow: -8px 8px 0 -5px #ffb13d;
        }

        .sa-auth-public-header__links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: clamp(24px, 4vw, 72px);
            font-weight: 700;
        }

        .sa-auth-public-header__links a:hover {
            color: var(--green);
        }

        .sa-auth-public-header__actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
        }

        .sa-auth-public-header__cart,
        .sa-auth-public-header__btn,
        .sa-auth-public-header__menu {
            min-height: 44px;
            border: 1px solid var(--green);
            border-radius: var(--radius);
            background: #fff;
            color: var(--green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sa-auth-public-header__cart {
            width: 46px;
            position: relative;
        }

        .sa-auth-public-header__cart svg {
            width: 22px;
            height: 22px;
        }

        .sa-auth-public-header__cart span {
            position: absolute;
            right: -1px;
            top: 0;
            min-width: 18px;
            height: 18px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: var(--green);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
        }

        .sa-auth-public-header__btn {
            padding: 0 22px;
            font-weight: 800;
        }

        .sa-auth-public-header__btn--solid,
        .sa-auth-public-header__btn--solid:visited,
        .sa-auth-public-header__btn--solid:hover,
        .sa-auth-public-header__btn--solid:focus {
            background: var(--green) !important;
            border-color: var(--green) !important;
            color: #fff !important;
        }

        .sa-auth-public-header__menu {
            display: none;
            width: 46px;
            padding: 0;
        }

        .sa-auth-public-header__menu span,
        .sa-auth-public-header__menu::before,
        .sa-auth-public-header__menu::after {
            content: "";
            display: block;
            width: 18px;
            height: 2px;
            margin: 4px auto;
            border-radius: 999px;
            background: currentColor;
        }

        @media (max-width: 1023px) {
            .sa-auth-public-header__announce {
                font-size: 12px;
            }

            .sa-auth-public-header__nav {
                width: min(100% - 28px, 1180px);
                min-height: 78px;
                grid-template-columns: auto auto;
            }

            .sa-auth-public-header__logo img {
                max-width: 148px;
            }

            .sa-auth-public-header__links {
                display: none;
                grid-column: 1 / -1;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 10px 0 18px;
            }

            .sa-auth-public-header.is-menu-open .sa-auth-public-header__links {
                display: flex;
            }

            .sa-auth-public-header__links a {
                padding: 14px 0;
                border-top: 1px solid #eef0f2;
            }

            .sa-auth-public-header__actions {
                justify-self: end;
            }

            .sa-auth-public-header__actions .sa-auth-public-header__btn {
                display: none;
            }

            .sa-auth-public-header__menu {
                display: inline-block;
            }
        }
    </style>
</head>
<body class="<?= e($bodyClass) ?>">
<div id="toast-root" class="toast-root" data-toasts='<?= e(json_encode(consume_toasts(), JSON_UNESCAPED_SLASHES)) ?>'></div>
<div class="sa-auth-public-header" id="sa-auth-public-header">
    <div class="sa-auth-public-header__announce"><strong>Free shipping on your first order from our U.S. warehouse</strong></div>
    <header class="sa-auth-public-header__bar">
        <nav class="sa-auth-public-header__nav">
            <a class="sa-auth-public-header__logo" href="<?= e(app_url('store')) ?>" aria-label="<?= e($brandName) ?> home">
                <?php if ($brandLogo !== ''): ?>
                    <img src="<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>">
                <?php else: ?>
                    <span class="sa-auth-public-header__mark" aria-hidden="true"></span><strong><?= e($brandName) ?></strong>
                <?php endif; ?>
            </a>
            <div class="sa-auth-public-header__links" id="sa-auth-public-menu">
                <a href="<?= e(app_url('store')) ?>">Home</a>
                <a href="<?= e(app_url('shop')) ?>">Marketplace</a>
                <a href="<?= e(app_url('shop?browse=1')) ?>">Shop</a>
                <a href="<?= e(app_url('vendors')) ?>">Our Vendors</a>
                <a href="<?= e(app_url('about')) ?>">About Us</a>
                <a href="<?= e(app_url('contact')) ?>">Contact Us</a>
            </div>
            <div class="sa-auth-public-header__actions">
                <a class="sa-auth-public-header__cart" href="<?= e(app_url('cart')) ?>" aria-label="Cart">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M5 8h14l-1 13H6L5 8Z"/></svg>
                    <span data-cart-count><?= number_format($authCartCount) ?></span>
                </a>
                <a class="sa-auth-public-header__btn" href="<?= e(app_url('login')) ?>">Login</a>
                <a class="sa-auth-public-header__btn sa-auth-public-header__btn--solid" href="<?= e(app_url('buyer/register')) ?>">Register</a>
                <button class="sa-auth-public-header__menu" type="button" aria-controls="sa-auth-public-menu" aria-expanded="false" aria-label="Toggle navigation menu"><span></span></button>
            </div>
        </nav>
    </header>
</div>
<script>
    (() => {
        const root = document.getElementById('sa-auth-public-header');
        const menu = root?.querySelector('.sa-auth-public-header__menu');
        if (!root || !menu) return;
        menu.addEventListener('click', () => {
            const open = root.classList.toggle('is-menu-open');
            menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
