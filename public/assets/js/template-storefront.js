(function () {
    const config = window.SellerAfricaTemplateStorefront || {};
    let products = Array.isArray(config.products) ? config.products : [];
    let hotProducts = Array.isArray(config.hotProducts) && config.hotProducts.length ? config.hotProducts : products;
    let popularProducts = Array.isArray(config.popularProducts) && config.popularProducts.length ? config.popularProducts : products;
    let cartItems = Array.isArray(config.cartItems) ? config.cartItems : [];
    const categories = Array.isArray(config.categories) ? config.categories : [];
    let brands = Array.isArray(config.brands) ? config.brands : [];
    let ratingFacets = Array.isArray(config.ratingFacets) ? config.ratingFacets : [];
    let shopBrandsExpanded = false;
    const blocks = config.contentBlocks || {};
    const brand = config.brand || {};
    const currencies = Array.isArray(config.currencies) && config.currencies.length
        ? config.currencies
        : [{ code: 'USD', name: 'US Dollar', symbol: '$', exchangeRate: 1, isDefault: true, decimalPlaces: 2 }];
    const languages = [
        ['en', 'English'],
        ['fr', 'French'],
        ['es', 'Spanish'],
        ['pt', 'Portuguese'],
        ['ar', 'Arabic'],
        ['yo', 'Yoruba'],
        ['ig', 'Igbo'],
        ['ha', 'Hausa'],
        ['sw', 'Swahili']
    ];
    const defaultCurrency = currencies.find((currency) => currency.isDefault) || currencies[0];
    let selectedCurrency = currencies.find((currency) => currency.code === localStorage.getItem('seller_africa_currency'))?.code || defaultCurrency.code || 'USD';
    let checkoutQuoteTimer = null;
    let latestCheckoutQuote = null;
    let reviewedCheckout = null;
    const transparentPixel = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    const toast = (type, message) => {
        if (window.SellerAfricaToast) {
            window.SellerAfricaToast({ type, message });
        }
    };

    const text = (value) => String(value ?? '').trim();
    const pageKey = text(config.page || window.location.pathname || 'home').replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '') || 'home';
    config.shopMeta = config.shopMeta || {};
    const productBrandName = (product) => product?.brand || product?.vendor || product?.unit || 'Seller Africa';
    const productAt = (index) => products[index % Math.max(products.length, 1)] || null;
    const hotProductAt = (index) => hotProducts[index % Math.max(hotProducts.length, 1)] || productAt(index);
    const popularProductAt = (index) => popularProducts[index % Math.max(popularProducts.length, 1)] || productAt(index);
    const categoryAt = (index) => categories[index % Math.max(categories.length, 1)] || null;

    function setProductCollections(nextProducts) {
        products = Array.isArray(nextProducts) ? nextProducts : [];
        hotProducts = Array.isArray(config.hotProducts) && config.hotProducts.length ? config.hotProducts : products;
        popularProducts = Array.isArray(config.popularProducts) && config.popularProducts.length ? config.popularProducts : products;
    }

    function currencyByCode(code) {
        return currencies.find((currency) => String(currency.code).toUpperCase() === String(code || '').toUpperCase()) || defaultCurrency;
    }

    function convertAmount(value, sourceCurrency) {
        const source = currencyByCode(sourceCurrency || defaultCurrency.code);
        const target = currencyByCode(selectedCurrency);
        const sourceRate = Number(source.exchangeRate || 1) || 1;
        const targetRate = Number(target.exchangeRate || 1) || 1;

        return (Number(value || 0) / sourceRate) * targetRate;
    }

    function formatMoney(value, sourceCurrency) {
        const target = currencyByCode(selectedCurrency);
        const converted = convertAmount(value, sourceCurrency);
        const decimals = Number.isInteger(target.decimalPlaces) ? target.decimalPlaces : (converted > 999 ? 0 : 2);

        return `${target.symbol || target.code} ${Number(converted || 0).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        })}`;
    }

    function setMoneyNode(node, amount, currency) {
        if (!node) return;
        node.dataset.saPrice = String(Number(amount || 0));
        node.dataset.saCurrency = currency || defaultCurrency.code || 'USD';
        node.textContent = formatMoney(amount, currency);
    }

    function refreshMoneyDisplays() {
        document.querySelectorAll('[data-sa-price]').forEach((node) => {
            setMoneyNode(node, Number(node.dataset.saPrice || 0), node.dataset.saCurrency || defaultCurrency.code);
        });
        updateCartBadges(cartItems);
        populateCheckoutSummary();
    }

    function googleLanguageCookie() {
        const match = document.cookie.match(/(?:^|;\s*)googtrans=([^;]+)/);
        if (!match) return 'en';
        const parts = decodeURIComponent(match[1]).split('/');
        return parts[2] || 'en';
    }

    function setGoogleLanguageCookie(language) {
        const value = `/en/${language || 'en'}`;
        document.cookie = `googtrans=${value};path=/;max-age=31536000`;
        if (window.location.hostname) {
            document.cookie = `googtrans=${value};domain=${window.location.hostname};path=/;max-age=31536000`;
        }
    }

    function loadGoogleTranslate() {
        if (document.getElementById('sa-google-translate-widget')) return;
        const mount = document.createElement('div');
        mount.id = 'sa-google-translate-widget';
        mount.hidden = true;
        document.body.appendChild(mount);

        window.saGoogleTranslateInit = function () {
            if (!window.google?.translate?.TranslateElement) return;
            new window.google.translate.TranslateElement({
                pageLanguage: 'en',
                autoDisplay: false
            }, 'sa-google-translate-widget');
        };

        if (!document.querySelector('script[src*="translate_a/element.js"]')) {
            const script = document.createElement('script');
            script.src = 'https://translate.google.com/translate_a/element.js?cb=saGoogleTranslateInit';
            script.async = true;
            document.head.appendChild(script);
        }
    }

    function wireGoogleTranslate() {
        document.querySelectorAll('.mtheader__top-lang select').forEach((select) => {
            select.innerHTML = languages.map(([code, label]) => `
                <option value="${escapeAttr(code)}"${code === googleLanguageCookie() ? ' selected' : ''}>${escapeHtml(label)}</option>
            `).join('');
            select.value = googleLanguageCookie();
            select.addEventListener('change', () => {
                setGoogleLanguageCookie(select.value || 'en');
                loadGoogleTranslate();
                window.setTimeout(() => window.location.reload(), 120);
            });
        });

        if (googleLanguageCookie() !== 'en') {
            loadGoogleTranslate();
        }
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function setImage(img, src, fallback) {
        if (!img) return;
        src = src || fallback || '';
        const frame = img.closest('.mtfeature__product-img, .mthot__product-img, .mt-shop-grid-img, .mtflash__product-img, .mtrecent__product-img, .mtcartmini__thumb, .mt-order-info-list-img, .mt-shop-details__tab-big-img, .mt-shop-details__tab-btn-box, .sa-vendor-product__image, .sa-vendor-card__banner');
        const original = fallback || '';
        img.loading = 'lazy';
        img.decoding = 'async';
        img.dataset.saFallback = original;
        img.dataset.saHasLiveImage = src ? '1' : '0';
        img.classList.remove('sa-image-loaded');
        if (frame) {
            frame.classList.remove('sa-image-ready');
            frame.classList.add('sa-image-skeleton');
        }

        const finish = () => {
            if (img.dataset.saHasLiveImage !== '1') return;
            img.classList.add('sa-image-loaded');
            if (frame) {
                frame.classList.remove('sa-image-skeleton');
                frame.classList.add('sa-image-ready');
            }
        };

        img.addEventListener('load', finish, { once: true });
        img.addEventListener('error', () => {
            const fallbackSrc = img.dataset.saFallback || fallback || '';
            if (fallbackSrc && img.src !== fallbackSrc) {
                img.dataset.saHasLiveImage = '1';
                img.src = fallbackSrc;
                return;
            }
            img.dataset.saHasLiveImage = '0';
            img.src = transparentPixel;
        }, { once: true });

        if (src) {
            img.src = src;
            if (img.complete && img.naturalWidth > 0) {
                finish();
            }
        } else {
            img.src = transparentPixel;
            if (frame) frame.classList.add('sa-image-skeleton');
        }
    }

    function addBuyNowAction(container, productId) {
        if (!container || !productId) return;
        if (container.querySelector('[data-buy-now]')) return;

        const link = document.createElement('a');
        link.href = '#';
        link.className = 'sa-buy-now-link';
        link.dataset.buyNow = String(productId);
        link.textContent = 'Buy Now';
        container.appendChild(link);
    }

    function attachBuyNowActions(card, product) {
        if (!card || !product) return;
        const productId = String(product.id || '');
        const selectors = '.mtfeature__product-cart, .mtflash__product-cart, .mt-shop-details__btn, .mt-shop-list-btn';
        const wrappers = [card].filter((node) => node && node.matches?.(selectors)).concat(Array.from(card.querySelectorAll(selectors)));
        wrappers.forEach((wrapper) => addBuyNowAction(wrapper, productId));
    }

    function setProductCard(card, product, compact) {
        if (!card || !product) return;
        card.dataset.saLive = 'product';

        const productUrl = product.url || config.urls?.store || '#';
        const image = card.querySelector('.mtfeature__product-img img, .mthot__product-img img, .mt-shop-grid-img img');
        const imageLink = image?.closest('a');
        const titleLink = card.querySelector('.mtfeature__product-title a, .mthot__product-title a, h5 a, h6 a');
        const unit = card.querySelector('.mtfeature__product-content > span, .mthot__product-cate span:first-child');
        const secondUnit = card.querySelector('.mthot__product-cate span:nth-child(2)');
        const price = card.querySelector('.mtfeature__product-price span, .mthot__product-price span');
        const oldPrice = card.querySelector('.mtfeature__product-price del, .mthot__product-price del');
        const offer = card.querySelector('.mtfeature__product-offer span');
        const cartLinks = card.querySelectorAll('.mtfeature__product-btn a, .mthot__product-cart a');

        setImage(image, product.image || '', '');
        if (image) image.alt = product.name || 'Seller Africa product';
        if (imageLink) imageLink.href = productUrl;
        if (titleLink) {
            titleLink.href = productUrl;
            titleLink.textContent = product.name || 'Seller Africa product';
        }
        if (unit) unit.textContent = compact ? (product.vendor || productBrandName(product)) : productBrandName(product);
        if (secondUnit) secondUnit.textContent = product.stock === 'out_of_stock' ? 'Out' : 'Stock';
        setMoneyNode(price, product.rawPrice || 0, product.currency || defaultCurrency.code);
        if (oldPrice) {
            if (product.regularRawPrice) {
                setMoneyNode(oldPrice, product.regularRawPrice, product.currency || defaultCurrency.code);
            } else {
                oldPrice.textContent = product.regularPrice || '';
            }
            oldPrice.style.display = product.regularPrice ? '' : 'none';
        }
        if (offer) offer.innerHTML = product.badge ? `${product.badge}<br>` : 'NEW<br>';

        cartLinks.forEach((link) => {
            link.href = '#';
            link.dataset.addCart = String(product.id || '');
            link.dataset.saLive = 'product-cart';
        });

        attachBuyNowActions(card, product);
    }

    function populateProducts() {
        renderMiniCart(cartItems);
        renderShopResults();
        hydrateServerProductImages();
        if (!products.length) return;

        document.querySelectorAll('.mtfeature__product-item:not([data-sa-server-card])').forEach((card, index) => {
            const section = card.closest('.mthot__product-area') ? hotProductAt(index) : popularProductAt(index);
            setProductCard(card, section || productAt(index), false);
        });

        document.querySelectorAll('.mthot__product-item').forEach((card, index) => {
            setProductCard(card, hotProductAt(index), true);
        });

        document.querySelectorAll('.mtflash__product-item').forEach((card, index) => {
            setFlashProductCard(card, hotProductAt(index));
        });

        document.querySelectorAll('.mtrecent__product-item').forEach((card, index) => {
            setRecentProductCard(card, productAt(index));
        });

        refreshTemplateSwipers();
    }

    function hydrateServerProductImages() {
        document.querySelectorAll('[data-sa-server-card] .mtfeature__product-img img').forEach((img) => {
            const src = img.getAttribute('src') || '';
            if (!src || img.classList.contains('sa-image-loaded')) return;
            setImage(img, src, src);
        });
    }

    function hydrateVendorMedia() {
        document.querySelectorAll('.sa-vendor-card__banner img, .sa-vendor-card__body > img, .sa-vendor-detail-hero__inner > img').forEach((img) => {
            const src = img.getAttribute('src') || '';
            if (!src || img.classList.contains('sa-image-loaded')) return;
            setImage(img, src, src);
        });
    }

    function setFlashProductCard(card, product) {
        if (!card || !product) return;
        card.dataset.saLive = 'flash-product';

        const image = card.querySelector('.mtflash__product-img img');
        const imageLink = image?.closest('a');
        const titleLink = card.querySelector('.mtflash__product-title a');
        const price = card.querySelector('.mtflash__product-price span');
        const oldPrice = card.querySelector('.mtflash__product-price del');
        const rating = card.querySelector('.mtflash__product-review-number');
        const unit = card.querySelector('.mtflash__product-cate span:first-child');
        const stock = card.querySelector('.mtflash__product-cate span:nth-child(2)');
        const cart = card.querySelector('.mtflash__product-cart a');

        setImage(image, product.image || '', '');
        if (image) image.alt = product.name || 'Seller Africa product';
        if (imageLink) imageLink.href = product.url || '#';
        if (titleLink) {
            titleLink.href = product.url || '#';
            titleLink.textContent = product.name || 'Seller Africa product';
        }
        setMoneyNode(price, product.rawPrice || 0, product.currency || defaultCurrency.code);
        if (oldPrice) {
            if (product.regularRawPrice) {
                setMoneyNode(oldPrice, product.regularRawPrice, product.currency || defaultCurrency.code);
            } else {
                oldPrice.textContent = product.regularPrice || '';
            }
            oldPrice.style.display = product.regularPrice ? '' : 'none';
        }
        if (rating) rating.textContent = product.rating ? `(${product.rating}.0)` : '(New)';
        if (unit) unit.textContent = productBrandName(product);
        if (stock) stock.textContent = product.stock === 'out_of_stock' ? 'Out' : 'Stock';
        if (cart) {
            cart.href = '#';
            cart.dataset.addCart = String(product.id || '');
        }
    }

    function setRecentProductCard(card, product) {
        if (!card || !product) return;
        card.dataset.saLive = 'recent-product';

        const image = card.querySelector('.mtrecent__product-img img');
        const imageLink = image?.closest('a');
        const titleLink = card.querySelector('.mtrecent__product-title a');
        const price = card.querySelector('.mtrecent__product-price span');
        const unit = card.querySelector('.mtrecent__product-price i');
        const cart = card.querySelector('.mtrecent__product-wishlist a');
        const quantity = card.querySelector('.mt-shop-details__quantity input');

        setImage(image, product.image || '', '');
        if (image) image.alt = product.name || 'Seller Africa product';
        if (imageLink) imageLink.href = product.url || '#';
        if (titleLink) {
            titleLink.href = product.url || '#';
            titleLink.textContent = product.name || 'Seller Africa product';
        }
        setMoneyNode(price, product.rawPrice || 0, product.currency || defaultCurrency.code);
        if (unit) unit.textContent = product.stock === 'out_of_stock' ? '/Out' : '/Stock';
        if (quantity) quantity.value = '01';
        if (cart) {
            cart.href = '#';
            cart.title = 'Add to cart';
            cart.dataset.addCart = String(product.id || '');
        }

        attachBuyNowActions(card, product);
    }

    function setShopGridCard(card, product) {
        if (!card || !product) return;
        card.dataset.saLive = 'shop-product';

        const image = card.querySelector('.mt-shop-grid-img img, img');
        const imageLink = image?.closest('a');
        const titleLink = card.querySelector('.mtfeature__product-title a, h5 a');
        const price = card.querySelector('.mtfeature__product-price span');
        const oldPrice = card.querySelector('.mtfeature__product-price del');
        const rating = card.querySelector('.mtflash__product-review-number');
        const unit = card.querySelector('.mtfeature__product-cate span:first-child');
        const secondUnit = card.querySelector('.mtfeature__product-cate span:nth-child(2)');
        const cart = card.querySelector('.mtfeature__product-cart a');

        setImage(image, product.image || '', '');
        if (image) image.alt = product.name || 'Seller Africa product';
        if (imageLink) imageLink.href = product.url || '#';
        if (titleLink) {
            titleLink.href = product.url || '#';
            titleLink.textContent = product.name || 'Seller Africa product';
        }
        setMoneyNode(price, product.rawPrice || 0, product.currency || defaultCurrency.code);
        if (oldPrice) {
            if (product.regularRawPrice) {
                setMoneyNode(oldPrice, product.regularRawPrice, product.currency || defaultCurrency.code);
            } else {
                oldPrice.textContent = product.regularPrice || '';
            }
            oldPrice.style.display = product.regularPrice ? '' : 'none';
        }
        if (rating) rating.textContent = product.rating ? `(${product.rating}.0)` : '(New)';
        if (unit) unit.textContent = productBrandName(product);
        if (secondUnit) secondUnit.textContent = product.stock === 'out_of_stock' ? 'Out' : 'Stock';
        if (cart) {
            cart.href = '#';
            cart.dataset.addCart = String(product.id || '');
        }
    }

    function setShopListCard(card, product) {
        if (!card || !product) return;
        card.dataset.saLive = 'shop-list-product';

        const image = card.querySelector('.mt-shop-list-img img');
        const imageLink = image?.closest('a');
        const titleLink = card.querySelector('.mt-shop-list-title a');
        const price = card.querySelector('.mt-shop-list-price span');
        const oldPrice = card.querySelector('.mt-shop-list-price del');
        const vendor = card.querySelector('.mt-shop-list-cate span:first-child');
        const stock = card.querySelector('.mt-shop-list-cate span:nth-child(2)');
        const rating = card.querySelector('.mt-shop-list-rating span:last-child, .mtflash__product-review-number');
        const description = card.querySelector('.mt-shop-list-text, .mt-shop-list-content-wrap p');
        const actionLinks = card.querySelectorAll('.mt-shop-list-btn a');

        setImage(image, product.image || '', '');
        if (image) image.alt = product.name || 'Seller Africa product';
        if (imageLink) imageLink.href = product.url || '#';
        if (titleLink) {
            titleLink.href = product.url || '#';
            titleLink.textContent = product.name || 'Seller Africa product';
        }
        setMoneyNode(price, product.rawPrice || 0, product.currency || defaultCurrency.code);
        if (oldPrice) {
            if (product.regularRawPrice) {
                setMoneyNode(oldPrice, product.regularRawPrice, product.currency || defaultCurrency.code);
            } else {
                oldPrice.textContent = product.regularPrice || '';
            }
            oldPrice.style.display = product.regularPrice ? '' : 'none';
        }
        if (vendor) vendor.textContent = productBrandName(product);
        if (stock) stock.textContent = product.stock === 'out_of_stock' ? 'Out' : 'Stock';
        if (rating) rating.textContent = product.rating ? `(${product.rating}.0)` : '(New)';
        if (description) description.textContent = product.description || 'Product details are available from this verified marketplace listing.';
        actionLinks.forEach((link, index) => {
            if (index === 0) {
                link.href = product.url || '#';
            } else {
                link.href = '#';
                link.dataset.addCart = String(product.id || '');
            }
        });

        attachBuyNowActions(card, product);
    }

    function renderShopResults() {
        ensureShopCardSlots(products.length);
        const gridCards = Array.from(document.querySelectorAll('.mt-shop-grid-item'));
        const listCards = Array.from(document.querySelectorAll('.mt-shop-list-item'));
        const visibleCount = products.length;
        const page = Math.max(1, Number(config.shopMeta?.page || 1));
        const perPage = Math.max(1, Number(config.shopMeta?.perPage || visibleCount || 16));
        const total = Math.max(0, Number(config.shopMeta?.total ?? visibleCount));
        const start = total > 0 ? ((page - 1) * perPage) + 1 : 0;
        const end = total > 0 ? Math.min(start + visibleCount - 1, total) : 0;

        gridCards.forEach((card, index) => {
            const holder = card.closest('[class*="col-"]') || card;
            const product = products[index] || null;
            holder.style.display = product ? '' : 'none';
            if (product) setShopGridCard(card, product);
        });

        document.querySelectorAll('[data-shop-category-heading]').forEach((node) => node.remove());
        let lastCategory = '';
        gridCards.forEach((card, index) => {
            const product = products[index] || null;
            if (!product) return;
            const holder = card.closest('[class*="col-"]') || card;
            const categoryName = text(product.primaryCategory || 'Uncategorized') || 'Uncategorized';
            if (categoryName === lastCategory) return;
            const heading = document.createElement('div');
            heading.className = 'col-12 sa-shop-category-heading';
            heading.dataset.shopCategoryHeading = '1';
            heading.innerHTML = `<h2>${escapeHtml(categoryName)}</h2>`;
            holder.parentNode?.insertBefore(heading, holder);
            lastCategory = categoryName;
        });

        listCards.forEach((card, index) => {
            const product = products[index] || null;
            card.style.display = product ? '' : 'none';
            if (product) setShopListCard(card, product);
        });

        const title = document.querySelector('.mt-shop-header-title');
        if (title) {
            const query = text(config.filters?.q);
            title.textContent = total
                ? `Products by category${query ? ` for "${query}"` : ''}`
                : `No products found${query ? ` for "${query}"` : ''}`;
        }

        const wrapper = document.querySelector('.mt-shop-grid-wrapper');
        let empty = document.querySelector('[data-shop-empty]');
        if (!empty && wrapper) {
            wrapper.insertAdjacentHTML('beforeend', `
                <div class="sa-shop-empty" data-shop-empty hidden>
                   <h4>No products found</h4>
                   <p>Try another keyword or clear the category filter.</p>
                </div>
            `);
            empty = document.querySelector('[data-shop-empty]');
        }
        if (empty) empty.hidden = visibleCount > 0;

        renderShopPagination();
        refreshMoneyDisplays();
    }

    function ensureShopCardSlots(count) {
        const gridRow = document.querySelector('.mt-shop-grid-wrapper .row');
        if (gridRow) {
            const existing = Array.from(gridRow.querySelectorAll('.mt-shop-grid-item'));
            const templateCol = existing[existing.length - 1]?.closest('[class*="col-"]');
            if (templateCol) {
                for (let index = existing.length; index < count; index++) {
                    const clone = templateCol.cloneNode(true);
                    clone.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'));
                    gridRow.appendChild(clone);
                }
            }
        }

        const listWrapper = document.querySelector('.mt-shop-list-wrapper');
        if (listWrapper) {
            const existing = Array.from(listWrapper.querySelectorAll('.mt-shop-list-item'));
            const template = existing[existing.length - 1];
            if (template) {
                for (let index = existing.length; index < count; index++) {
                    const clone = template.cloneNode(true);
                    clone.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'));
                    listWrapper.appendChild(clone);
                }
            }
        }
    }

    function shopPageUrl(page, perPage = config.shopMeta?.perPage) {
        const url = new URL(config.urls?.shop || window.location.href, window.location.origin);
        const filters = { ...shopFilters(), page: String(page || 1), per_page: String(perPage || 16) };
        Object.entries(filters).forEach(([key, value]) => {
            const clean = text(value);
            if (!clean || (key === 'sort' && clean === 'default') || (key === 'page' && clean === '1') || (key === 'per_page' && clean === '16')) {
                url.searchParams.delete(key);
                return;
            }
            url.searchParams.set(key, clean);
        });
        return url.toString();
    }

    function shopPaginationPages(page, totalPages) {
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, (_, index) => index + 1);
        }
        const pages = [1];
        const start = Math.max(2, page - 1);
        const end = Math.min(totalPages - 1, page + 1);
        if (start > 2) pages.push('...');
        for (let item = start; item <= end; item++) pages.push(item);
        if (end < totalPages - 1) pages.push('...');
        pages.push(totalPages);
        return pages;
    }

    function renderShopPagination() {
        if (config.page !== 'shop') return;
        const page = Math.max(1, Number(config.shopMeta?.page || 1));
        const perPage = Math.max(1, Number(config.shopMeta?.perPage || 16));
        const total = Math.max(0, Number(config.shopMeta?.total || products.length));
        const totalPages = Math.max(1, Number(config.shopMeta?.totalPages || Math.ceil(total / perPage) || 1));

        document.querySelectorAll('.mt-pagination-wrap').forEach((wrap) => {
            wrap.hidden = total <= perPage && totalPages <= 1;
            const pagination = wrap.querySelector('.mt-pagination');
            const sort = wrap.querySelector('.mt-pagination-sort select');
            if (pagination) {
                const previous = Math.max(1, page - 1);
                const next = Math.min(totalPages, page + 1);
                pagination.innerHTML = `
                    <a href="${escapeAttr(shopPageUrl(previous, perPage))}" class="button${page <= 1 ? ' disabled' : ''}" data-shop-page="${previous}" aria-disabled="${page <= 1 ? 'true' : 'false'}"><i class="fa-regular fa-chevron-left"></i> Previous</a>
                    <a href="${escapeAttr(shopPageUrl(next, perPage))}" class="button${page >= totalPages ? ' disabled' : ''}" data-shop-page="${next}" aria-disabled="${page >= totalPages ? 'true' : 'false'}">Next <i class="fa-regular fa-chevron-right"></i></a>
                `;
            }
            if (sort) {
                sort.dataset.shopPerPage = '1';
                sort.innerHTML = [16, 24, 32, 48].map((value) => `<option value="${value}"${value === perPage ? ' selected' : ''}>${value}</option>`).join('');
                sort.value = String(perPage);
            }
        });
    }

    function shopFilters() {
        return {
            q: text(config.filters?.q || ''),
            category: text(config.filters?.category || ''),
            brand: text(config.filters?.brand || ''),
            rating: text(config.filters?.rating || ''),
            min_price: text(config.filters?.min_price || ''),
            max_price: text(config.filters?.max_price || ''),
            sort: text(config.filters?.sort || 'default') || 'default',
            page: text(config.shopMeta?.page || '1') || '1',
            per_page: text(config.shopMeta?.perPage || '16') || '16'
        };
    }

    function setShopFilters(next) {
        config.filters = { ...shopFilters(), ...(next || {}) };
    }

    function populateCategories() {
        if (!categories.length) return;

        document.querySelectorAll('.mtshop__category-item').forEach((card, index) => {
            const category = categoryAt(index);
            if (!category) return;
            card.dataset.saLive = 'category';
            const link = card.querySelector('a');
            const img = card.querySelector('img');
            const title = card.querySelector('.mtshop__category-title');
            const count = card.querySelector('span');
            if (link) link.href = category.url || config.urls?.store || '#';
            if (title) title.textContent = category.name || 'Category';
            if (count) count.textContent = '';
            setImage(img, category.image, img?.getAttribute('src') || '');
        });

        document.querySelectorAll('#mtheader__bottom-category-list li, #mtheader__bottom-category-offcanvas li').forEach((item, index) => {
            const category = categoryAt(index);
            if (!category) return;
            item.dataset.saLive = 'category-menu';
            const link = item.querySelector('a');
            const label = item.querySelector('span');
            if (link) link.href = category.url || config.urls?.store || '#';
            if (label) label.textContent = category.name || 'Category';
        });

        document.querySelectorAll('.mtpopular__product-tab .nav-link').forEach((button, index) => {
            const category = categoryAt(index - 1);
            if (index === 0) {
                button.textContent = 'View All';
                return;
            }
            if (category) button.textContent = category.name || 'Category';
        });

        document.querySelectorAll('.mt-shop-widget-categories li').forEach((item, index) => {
            const category = categoryAt(index);
            if (!category) return;
            const link = item.querySelector('a');
            const img = item.querySelector('img');
            if (link) {
                link.href = category.url || config.urls?.shop || '#';
                link.dataset.shopCategory = category.slug || '';
                Array.from(link.childNodes).forEach((node) => {
                    if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                        node.textContent = category.name;
                    }
                });
                item.classList.toggle('active', shopFilters().category === category.slug);
            }
            setImage(img, category.image, img?.getAttribute('src') || '');
        });
    }

    function populateShopBrands() {
        if (config.page !== 'shop') return;
        const list = document.querySelector('.mt-shop-widget-brands form');
        if (!list) return;
        const active = shopFilters().brand;
        if (!brands.length) {
            list.innerHTML = '<p class="sa-shop-facet-empty">No product brands found for this selection.</p>';
            return;
        }

        const activeIndex = brands.findIndex((brandItem) => brandItem.slug === active);
        const shouldExpand = shopBrandsExpanded || activeIndex >= 30;
        const visibleBrands = shouldExpand ? brands : brands.slice(0, 30);
        const hiddenCount = Math.max(0, brands.length - visibleBrands.length);
        list.innerHTML = `
            <div class="form-check mt-shop-widget-brands-item mb-10">
               <input class="form-check-input" type="radio" name="brand" value="" id="sa_brand_all"${active === '' ? ' checked' : ''}>
               <label class="form-check-label" for="sa_brand_all">
                  All Brands
               </label>
            </div>
        ` + visibleBrands.map((brandItem, index) => `
            <div class="form-check mt-shop-widget-brands-item mb-10">
               <input class="form-check-input" type="radio" name="brand" value="${escapeAttr(brandItem.slug || '')}" id="sa_brand_${index}"${active === brandItem.slug ? ' checked' : ''}>
               <label class="form-check-label" for="sa_brand_${index}">
                  ${escapeHtml(brandItem.name || 'Brand')}
               </label>
            </div>
        `).join('') + (brands.length > 30 ? `
            <button class="sa-shop-brand-toggle" type="button" data-shop-brands-toggle aria-expanded="${shouldExpand ? 'true' : 'false'}">
               ${shouldExpand ? 'View less' : `View more (${hiddenCount.toLocaleString()} more)`}
            </button>
        ` : '');
    }

    function populateShopRatings() {
        if (config.page !== 'shop') return;
        const list = document.querySelector('.mt-shop-widget-rating form');
        if (!list) return;
        const active = shopFilters().rating;
        const availableFacets = ratingFacets.length
            ? ratingFacets
            : [5, 4, 3, 2, 1].map((rating) => ({ rating, label: `${rating}+ stars`, product_count: 0 }));
        const stars = (rating) => [1, 2, 3, 4, 5].map((star) => (
            `<i class="${star <= rating ? 'fa-sharp fa-solid' : 'fa-light'} fa-star"></i>`
        )).join('');

        list.innerHTML = `
            <div class="form-check mt-shop-widget-rating-item mb-10">
               <input class="form-check-input" type="radio" name="rating" value="" id="sa_rating_all"${active === '' ? ' checked' : ''}>
               <label class="form-check-label" for="sa_rating_all">
                  ${stars(0)} <span>All ratings</span>
               </label>
            </div>
        ` + availableFacets.map((facet) => {
            const rating = Math.max(1, Math.min(5, Number(facet.rating || 0)));
            const count = Number(facet.product_count || 0);
            return `
                <div class="form-check mt-shop-widget-rating-item mb-10">
                   <input class="form-check-input" type="radio" name="rating" value="${rating}" id="sa_rating_${rating}"${active === String(rating) ? ' checked' : ''}${count === 0 ? ' disabled' : ''}>
                   <label class="form-check-label" for="sa_rating_${rating}">
                      ${stars(rating)} <span>${escapeHtml(facet.label || `${rating}+ stars`)}</span>
                   </label>
                </div>
            `;
        }).join('');
    }

    function normalizeMainMenu() {
        const links = [
            ['Marketplace', config.urls?.shop || '#'],
            ['Our Vendors', config.urls?.vendors || '#'],
            ['About Us', config.urls?.about || '#'],
            ['Contact Us', config.urls?.contact || '#']
        ];

	        document.querySelectorAll('.mtheader__main-menu > nav > ul, .mt-mobile-menu-active > ul, .mt-offcanvas-menu nav > ul').forEach((menu) => {
	            menu.innerHTML = links.map(([label, url]) => {
	                const slug = String(label).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
	                return `<li class="sa-menu-item sa-menu-item-${escapeAttr(slug)}"><a href="${escapeAttr(url)}">${escapeHtml(label)}</a></li>`;
	            }).join('');
	        });

        document.querySelectorAll('a[href*="/vendors"], a[href$="vendors"]').forEach((link) => {
            const label = text(link.textContent);
            if (label === '' || label === '.' || label === '•') {
                link.textContent = 'Our Vendors';
            }
            link.setAttribute('aria-label', 'Our Vendors');
        });

        document.querySelectorAll('.mtheader__bottom-right .mt-btn').forEach((button) => {
            button.href = config.urls?.register || button.href;
            button.innerHTML = 'Register';
            button.setAttribute('aria-label', 'Register');
        });

        document.querySelectorAll('.mt-offcanvas-logo a, .mtheader__logo a').forEach((link) => {
            link.href = config.urls?.store || link.href;
        });

        document.querySelectorAll('.mtheader__top-left').forEach((topMenu) => {
            if (topMenu.querySelector('a[href^="mailto:"], a[href^="tel:"]')) return;
            const accountUrl = config.page === 'affiliate-registration'
                ? (config.urls?.affiliateLogin || config.urls?.login || '#')
                : (config.urls?.login || '#');
            topMenu.innerHTML = [
                ['About Us', config.urls?.about || '#'],
                ['My Account', accountUrl],
                ['Track Order', config.urls?.trackOrder || '#']
            ].map(([label, url], index) => {
                const divider = index === 0 ? '' : '<span class="sa-top-menu-divider" aria-hidden="true"></span>';
                return `${divider}<a href="${escapeAttr(url)}">${escapeHtml(label)}</a>`;
            }).join('');
        });
    }

    function applyBranding() {
        const brandName = text(brand.name) || 'Seller Africa';
        const brandLogo = text(brand.logo);

        if (brandName) {
            document.title = document.title.replace(/Seller Africa/g, brandName);
        }

        document.querySelectorAll('.mt-offcanvas-logo img, .mtheader__logo img, .mt-footer-logo img').forEach((img) => {
            if (brandLogo) {
                img.src = brandLogo;
            }
            img.alt = brandName;
            img.loading = 'lazy';
            img.decoding = 'async';
        });

        document.querySelectorAll('.mt-offcanvas-logo a, .mtheader__logo a, .mt-footer-logo a').forEach((link) => {
            link.href = config.urls?.store || link.href;
            link.setAttribute('aria-label', brandName);
        });
    }

    function wireNavigation() {
        document.querySelectorAll('a[href$="/store"], a[href="#"]').forEach((link) => {
            const label = text(link.textContent).toLowerCase();
            const accountUrl = config.page === 'affiliate-registration'
                ? (config.urls?.affiliateLogin || config.urls?.login || link.href)
                : (config.urls?.login || link.href);
            if (label.includes('cart')) link.href = config.urls?.cart || link.href;
            if (label.includes('login') || label.includes('account')) link.href = accountUrl;
            if (label.includes('home')) link.href = config.urls?.store || link.href;
            if (label.includes('shop')) link.href = config.urls?.shop || link.href;
            if (label.includes('track')) link.href = config.urls?.trackOrder || link.href;
            if (label.includes('about')) link.href = config.urls?.about || link.href;
            if (label.includes('contact')) link.href = config.urls?.contact || link.href;
        });

        document.querySelectorAll('form').forEach((form) => {
            if (form.querySelector('input[placeholder*="Search"], input[placeholder*="search"]')) {
                form.method = 'GET';
                form.action = (config.page === 'shop' ? config.urls?.shop : config.urls?.store) || form.action;
                const input = form.querySelector('input[type="text"], input[type="search"]');
                if (input) {
                    if (!input.name) input.name = 'q';
                    if (config.filters?.q) input.value = config.filters.q;
                }
            }
        });

        document.querySelectorAll('.mtheader__top-currency select, .mtheader__top-lang select').forEach((select) => {
            if (select.closest('.mtheader__top-currency')) {
                select.innerHTML = currencies.map((currency) => `
                    <option value="${escapeAttr(currency.code)}"${currency.code === selectedCurrency ? ' selected' : ''}>${escapeHtml(currency.code)}</option>
                `).join('');
                select.value = selectedCurrency;
                select.addEventListener('change', () => {
                    selectedCurrency = currencyByCode(select.value).code;
                    localStorage.setItem('seller_africa_currency', selectedCurrency);
                    document.querySelectorAll('.mtheader__top-currency select').forEach((peer) => {
                        peer.value = selectedCurrency;
                    });
                    refreshMoneyDisplays();
                });
            }
        });
    }

    function ensureShopSearchBar() {
        if (config.page !== 'shop') return null;
        const existing = document.querySelector('[data-shop-search]');
        if (existing) {
            ensureShopSearchHiddenFields(existing);
            return existing;
        }

        const header = document.querySelector('.mt-shop-header');
        if (!header) return null;

        header.insertAdjacentHTML('beforebegin', `
            <form class="sa-shop-search" data-shop-search action="${escapeAttr(config.urls?.shop || '')}" method="GET" role="search">
               <div class="sa-shop-search__field">
                  <i class="fa-light fa-magnifying-glass" aria-hidden="true"></i>
                  <input type="search" name="q" value="${escapeAttr(config.filters?.q || '')}" placeholder="Search products, vendors, SKU..." autocomplete="off" aria-label="Search products">
                  <input type="hidden" name="category" value="${escapeAttr(config.filters?.category || '')}">
                  <input type="hidden" name="brand" value="${escapeAttr(config.filters?.brand || '')}">
                  <input type="hidden" name="rating" value="${escapeAttr(config.filters?.rating || '')}">
                  <input type="hidden" name="min_price" value="${escapeAttr(config.filters?.min_price || '')}">
                  <input type="hidden" name="max_price" value="${escapeAttr(config.filters?.max_price || '')}">
                  <input type="hidden" name="sort" value="${escapeAttr(config.filters?.sort || 'default')}">
                  <input type="hidden" name="page" value="${escapeAttr(config.shopMeta?.page || '1')}">
                  <input type="hidden" name="per_page" value="${escapeAttr(config.shopMeta?.perPage || '16')}">
                  <button type="button" class="sa-shop-search__clear" data-shop-search-clear aria-label="Clear search"${config.filters?.q ? '' : ' hidden'}><i class="fa-light fa-xmark"></i></button>
               </div>
               <button class="mt-btn" type="submit"><i class="fa-light fa-search"></i> <span>Search</span></button>
               <span class="sa-shop-search__status" data-shop-search-status aria-live="polite"></span>
            </form>
        `);

        return document.querySelector('[data-shop-search]');
    }

    function ensureShopSearchHiddenFields(form) {
        ['page', 'per_page'].forEach((name) => {
            if (form.querySelector(`[name="${name}"]`)) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = name === 'page' ? String(config.shopMeta?.page || 1) : String(config.shopMeta?.perPage || 16);
            form.querySelector('.sa-shop-search__field')?.appendChild(input);
        });
    }

    function updateShopUrl(filters) {
        if (!window.history || !config.urls?.shop) return;
        const url = new URL(config.urls.shop, window.location.origin);
        Object.entries(filters || {}).forEach(([key, value]) => {
            const clean = text(value);
            if (clean && !(key === 'sort' && clean === 'default') && !(key === 'page' && clean === '1') && !(key === 'per_page' && clean === '16')) {
                url.searchParams.set(key, clean);
            }
        });
        window.history.replaceState({}, '', url.toString());
    }

    async function runShopSearch(nextFilters, legacyCategory, statusNode) {
        const endpoint = config.endpoints?.search;
        if (!endpoint) return;
        const filters = typeof nextFilters === 'object'
            ? { ...shopFilters(), ...nextFilters }
            : { ...shopFilters(), q: text(nextFilters || ''), category: text(legacyCategory || '') };

        const url = new URL(endpoint, window.location.origin);
        Object.entries(filters).forEach(([key, value]) => {
            const clean = text(value);
            if (clean && !(key === 'sort' && clean === 'default')) url.searchParams.set(key, clean);
        });

        document.body.classList.add('sa-shop-searching');
        if (statusNode) statusNode.textContent = 'Searching...';

        try {
            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' }
            });
            const json = await response.json();
            if (!json.ok) {
                toast('error', json.message || 'Product search failed.');
                return;
            }

            config.filters = config.filters || {};
            setShopFilters(filters);
            brands = Array.isArray(json.brands) ? json.brands : brands;
            ratingFacets = Array.isArray(json.ratingFacets) ? json.ratingFacets : ratingFacets;
            config.shopMeta = {
                page: Number(json.page || filters.page || 1),
                perPage: Number(json.perPage || filters.per_page || 16),
                total: Number(json.total || 0),
                totalPages: Number(json.totalPages || 1)
            };
            setProductCollections(json.products || []);
            populateShopBrands();
            populateShopRatings();
            syncShopFilterControls();
            renderShopResults();
            updateShopUrl(shopFilters());
            if (statusNode) {
                statusNode.textContent = 'Products updated';
            }
        } catch (error) {
            toast('error', 'Product search request failed.');
            if (statusNode) statusNode.textContent = 'Search failed';
        } finally {
            document.body.classList.remove('sa-shop-searching');
        }
    }

    function wireShopSearch() {
        const form = ensureShopSearchBar();
        if (!form) return;

        const input = form.querySelector('input[name="q"]');
        const status = form.querySelector('[data-shop-search-status]');
        const clear = form.querySelector('[data-shop-search-clear]');
        let timer = null;

        const search = () => {
            const filters = { ...shopFilters(), q: text(input?.value || ''), page: '1' };
            if (clear) clear.hidden = filters.q === '';
            window.clearTimeout(timer);
            timer = window.setTimeout(() => runShopSearch(filters, null, status), 260);
        };

        input?.addEventListener('input', search);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(timer);
            runShopSearch({ ...shopFilters(), q: text(input?.value || ''), page: '1' }, null, status);
        });
        clear?.addEventListener('click', () => {
            if (input) input.value = '';
            clear.hidden = true;
            runShopSearch({ ...shopFilters(), q: '', page: '1' }, null, status);
            input?.focus();
        });

        if (status) {
            status.textContent = 'Products ready';
        }
    }

    function syncShopFilterControls() {
        if (config.page !== 'shop') return;
        const filters = shopFilters();
        const form = document.querySelector('[data-shop-search]');
        if (form) {
            ['q', 'category', 'brand', 'rating', 'min_price', 'max_price', 'sort', 'page', 'per_page'].forEach((name) => {
                const input = form.querySelector(`[name="${name}"]`);
                if (input) input.value = filters[name] || '';
            });
        }
        document.querySelectorAll('[data-shop-category]').forEach((link) => {
            link.closest('li')?.classList.toggle('active', filters.category === link.dataset.shopCategory);
        });
        document.querySelectorAll('.mt-shop-widget-brands input[name="brand"]').forEach((input) => {
            input.checked = filters.brand === input.value;
        });
        document.querySelectorAll('.mt-shop-widget-rating input[name="rating"]').forEach((input) => {
            input.checked = filters.rating === input.value;
        });
        const min = document.querySelector('[data-shop-min-price]');
        const max = document.querySelector('[data-shop-max-price]');
        const sort = document.querySelector('[data-shop-sort]');
        if (min) min.value = filters.min_price || '';
        if (max) max.value = filters.max_price || '';
        if (sort) sort.value = filters.sort || 'default';
    }

    function prepareShopFilters() {
        if (config.page !== 'shop') return;
        populateShopBrands();
        populateShopRatings();

        const priceBox = document.querySelector('.mt-shop-widget-filter-info');
        if (priceBox && !priceBox.querySelector('[data-shop-min-price]')) {
            priceBox.insertAdjacentHTML('afterbegin', `
                <div class="sa-shop-price-fields">
                   <input type="number" min="0" step="0.01" data-shop-min-price placeholder="Min" value="${escapeAttr(shopFilters().min_price)}">
                   <input type="number" min="0" step="0.01" data-shop-max-price placeholder="Max" value="${escapeAttr(shopFilters().max_price)}">
                </div>
            `);
        }

        const sortSelect = document.querySelector('.mt-shop-header .mt__select-box select');
        if (sortSelect) {
            sortSelect.dataset.shopSort = '1';
            sortSelect.innerHTML = `
                <option value="default">Default Sorting</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
                <option value="newest">New Added</option>
                <option value="on_sale">On Sale</option>
                <option value="top_selling">Top Selling</option>
            `;
        }

        const topFilter = document.querySelector('.mt-shop-top-filtar');
        if (topFilter) {
            topFilter.innerHTML = `
                <a href="#" data-shop-clear-filters><i class="fa-light fa-filter-circle-xmark"></i> Clear Filters</a>
                <a href="${escapeAttr(config.urls?.shop || '#')}"><i class="fa-light fa-grid-2"></i> All Products</a>
            `;
        }

        syncShopFilterControls();
    }

    function wireShopFilters() {
        if (config.page !== 'shop') return;
        const status = () => document.querySelector('[data-shop-search-status]');
        document.addEventListener('click', (event) => {
            const categoryLink = event.target.closest('[data-shop-category]');
            const clear = event.target.closest('[data-shop-clear-filters]');
            const priceButton = event.target.closest('.mt-shop-widget-filter-btn');
            const brandToggle = event.target.closest('[data-shop-brands-toggle]');
            const pageLink = event.target.closest('[data-shop-page]');
            if (categoryLink) {
                event.preventDefault();
                runShopSearch({ ...shopFilters(), category: categoryLink.dataset.shopCategory || '', page: '1' }, null, status());
            } else if (clear) {
                event.preventDefault();
                runShopSearch({ q: '', category: '', brand: '', rating: '', min_price: '', max_price: '', sort: 'default', page: '1', per_page: shopFilters().per_page }, null, status());
            } else if (priceButton) {
                event.preventDefault();
                runShopSearch({
                    ...shopFilters(),
                    min_price: text(document.querySelector('[data-shop-min-price]')?.value || ''),
                    max_price: text(document.querySelector('[data-shop-max-price]')?.value || ''),
                    page: '1'
                }, null, status());
            } else if (brandToggle) {
                event.preventDefault();
                shopBrandsExpanded = !shopBrandsExpanded;
                populateShopBrands();
                syncShopFilterControls();
            } else if (pageLink) {
                event.preventDefault();
                if (pageLink.getAttribute('aria-disabled') === 'true') return;
                runShopSearch({ ...shopFilters(), page: pageLink.dataset.shopPage || '1' }, null, status());
            }
        });

        document.addEventListener('change', (event) => {
            const brandInput = event.target.closest('.mt-shop-widget-brands input[name="brand"]');
            const ratingInput = event.target.closest('.mt-shop-widget-rating input[name="rating"]');
            const sortInput = event.target.closest('[data-shop-sort]');
            const perPageInput = event.target.closest('[data-shop-per-page]');
            if (brandInput) {
                runShopSearch({ ...shopFilters(), brand: brandInput.value, page: '1' }, null, status());
            } else if (ratingInput) {
                runShopSearch({ ...shopFilters(), rating: ratingInput.value, page: '1' }, null, status());
            } else if (sortInput) {
                runShopSearch({ ...shopFilters(), sort: sortInput.value || 'default', page: '1' }, null, status());
            } else if (perPageInput) {
                runShopSearch({ ...shopFilters(), per_page: perPageInput.value || '16', page: '1' }, null, status());
            }
        });
    }

    function cartSubtotal(items = cartItems) {
        return items.reduce((sum, item) => sum + convertAmount(Number(item.rawPrice || 0), item.currency || defaultCurrency.code) * Number(item.quantity || 1), 0);
    }

    function updateCartBadges(items = cartItems, subtotal = cartSubtotal(items)) {
        const count = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        document.querySelectorAll('.mtheader__midel-card-value p').forEach((node) => {
            node.textContent = Number(count || 0).toLocaleString();
        });
        document.querySelectorAll('.mtheader__midel-card-value h6').forEach((node) => {
            node.textContent = `${currencyByCode(selectedCurrency).symbol || selectedCurrency} ${Number(subtotal || 0).toLocaleString(undefined, {
                minimumFractionDigits: subtotal > 999 ? 0 : 2,
                maximumFractionDigits: subtotal > 999 ? 0 : 2
            })}`;
        });
        const miniSubtotalNode = document.querySelector('.mtcartmini__checkout-title span');
        if (miniSubtotalNode) {
            miniSubtotalNode.textContent = `${currencyByCode(selectedCurrency).symbol || selectedCurrency} ${Number(subtotal || 0).toLocaleString(undefined, {
                minimumFractionDigits: subtotal > 999 ? 0 : 2,
                maximumFractionDigits: subtotal > 999 ? 0 : 2
            })}`;
        }
    }

    function renderMiniCart(items = cartItems) {
        const list = document.querySelector('.mtcartmini__widget');
        if (!list) {
            updateCartBadges(items);
            return;
        }

        list.innerHTML = items.length ? items.map((item) => `
            <div class="mtcartmini__widget-item" data-sa-live="mini-cart-item" data-product-id="${escapeAttr(item.id || '')}">
               <div class="mtcartmini__thumb sa-image-skeleton">
                  <a href="${escapeAttr(item.url || '#')}">
                     <img loading="lazy" decoding="async" src="${transparentPixel}" alt="${escapeAttr(item.name || 'Product')}">
                  </a>
               </div>
               <div class="mtcartmini__content">
                  <h5 class="mtcartmini__title"><a href="${escapeAttr(item.url || '#')}">${escapeHtml(item.name || 'Seller Africa product')}</a></h5>
                  <div class="mtcartmini__prmte-wrapper">
                     <span class="mtcartmini__prmte">${escapeHtml(formatMoney(item.rawPrice || 0, item.currency || defaultCurrency.code))}</span>
                     <span class="mtcartmini__quantity">x${Number(item.quantity || 1).toLocaleString()}</span>
                  </div>
                  <div class="sa-mini-cart-controls">
                     <button type="button" data-cart-action="decrement" data-product-id="${escapeAttr(item.id || '')}" aria-label="Decrease quantity">-</button>
                     <span>${Number(item.quantity || 1).toLocaleString()}</span>
                     <button type="button" data-cart-action="increment" data-product-id="${escapeAttr(item.id || '')}" aria-label="Increase quantity">+</button>
                  </div>
               </div>
               <button type="button" class="mtcartmini__del" data-cart-action="remove" data-product-id="${escapeAttr(item.id || '')}" aria-label="Remove item"><i class="fa-regular fa-xmark"></i></button>
            </div>
        `).join('') : `
            <div class="mtcartmini__widget-item sa-mini-cart-empty" data-sa-live="mini-cart-empty">
               <div class="mtcartmini__content">
                  <h5 class="mtcartmini__title">Your cart is empty</h5>
                  <div class="mtcartmini__prmte-wrapper">
                     <span class="mtcartmini__prmte">Add products from the marketplace.</span>
                  </div>
               </div>
            </div>
        `;

        items.forEach((item) => {
            const row = list.querySelector(`[data-product-id="${cssEscape(String(item.id || ''))}"]`);
            const img = row?.querySelector('.mtcartmini__thumb img');
            setImage(img, item.image || item.fallbackImage || '', item.fallbackImage || '');
        });

        updateCartBadges(items);
    }

    function applyCartResponse(json) {
        if (!json || !json.ok) return false;
        cartItems = Array.isArray(json.items) ? json.items : [];
        renderMiniCart(cartItems);
        populateCheckoutSummary();
        return true;
    }

    async function postCart(body) {
        const response = await fetch(config.endpoints?.cart || '', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body
        });
        return response.json();
    }

    function wireCart() {
        document.addEventListener('click', async (event) => {
            const trigger = event.target.closest('[data-add-cart]');
            const buyNowTrigger = event.target.closest('[data-buy-now]');
            const actionTrigger = event.target.closest('[data-cart-action]');
            if (!trigger && !buyNowTrigger && !actionTrigger) return;
            event.preventDefault();

            const productId = trigger?.dataset.addCart || buyNowTrigger?.dataset.buyNow || actionTrigger?.dataset.productId || '';
            if (!productId) return;

            const body = new URLSearchParams();
            body.set('product_id', productId);
            body.set('quantity', '1');
            if (config.page === 'product' && !actionTrigger) {
                const quantity = Number.parseInt(document.querySelector('.mt-shop-details__quantity input')?.value || '1', 10);
                body.set('quantity', String(Math.max(1, Number.isFinite(quantity) ? quantity : 1)));
            }
            if (actionTrigger) {
                const action = actionTrigger.dataset.cartAction || 'add';
                const current = cartItems.find((item) => String(item.id) === String(productId));
                const quantity = Number(current?.quantity || 0);
                if (action === 'remove') {
                    body.set('action', 'remove');
                    body.set('quantity', '0');
                } else if (action === 'decrement') {
                    body.set('action', 'set');
                    body.set('quantity', String(Math.max(0, quantity - 1)));
                } else if (action === 'increment') {
                    body.set('action', 'set');
                    body.set('quantity', String(quantity + 1));
                }
            }

            try {
                const json = await postCart(body);
                if (json.redirect) { window.location.assign(json.redirect); return; }
                const ok = applyCartResponse(json);
                if (ok && buyNowTrigger) {
                    window.location.assign(config.urls?.checkout || '/storefront/checkout');
                    return;
                }
                toast(ok ? 'success' : 'error', ok ? (json.message || 'Cart updated.') : (json.message || 'Unable to update cart.'));
            } catch (error) {
                toast('error', 'Cart request failed.');
            }
        });
    }

    function populateProductDetails() {
        if (config.page !== 'product' || !config.product) return;
        const product = config.product;
        const images = Array.from(new Set((Array.isArray(product.media) ? product.media : []).concat(product.image || '').filter(Boolean)));
        const liveImages = images.length ? images : [transparentPixel];

        document.querySelectorAll('.mt-shop-details__tab-big-img img').forEach((img, index) => {
            if (!liveImages[index]) {
                img.closest('.tab-pane')?.remove();
                return;
            }
            setImage(img, liveImages[index], '');
            img.alt = product.name || 'Seller Africa product';
        });

        document.querySelectorAll('.mt-shop-details__tab-btn-box img').forEach((img, index) => {
            if (!liveImages[index]) {
                img.closest('button')?.remove();
                return;
            }
            setImage(img, liveImages[index], '');
            img.alt = product.name || 'Seller Africa product';
        });

        const title = document.querySelector('.mt-shop-details__title-sm');
        const desc = document.querySelector('.mt-shop-details__text p');
        const price = document.querySelector('.mt-shop-details__price span');
        const oldPrice = document.querySelector('.mt-shop-details__price del');
        const offer = document.querySelector('.mt-shop-details__offer span');
        const review = document.querySelector('.mt-shop-details__ratting .review-text');
        const addCart = document.querySelector('.mt-shop-details__btn a');
        const infoRows = document.querySelectorAll('.mt-shop-details__product-info li');
        const intro = document.querySelector('#home-1 .mt-content-tab-content p');

        if (title) title.textContent = product.name || 'Seller Africa product';
        if (desc) desc.textContent = product.description || product.vendor || 'Product details are being prepared by the vendor.';
        if (intro) intro.textContent = product.description || 'Product details are being prepared by the vendor.';
        setMoneyNode(price, product.rawPrice || 0, product.currency || defaultCurrency.code);
        if (oldPrice) {
            if (product.regularRawPrice) {
                setMoneyNode(oldPrice, product.regularRawPrice, product.currency || defaultCurrency.code);
            } else {
                oldPrice.textContent = product.regularPrice || '';
            }
            oldPrice.style.display = product.regularPrice ? '' : 'none';
        }
        if (offer) offer.textContent = product.badge || 'LIVE';
        if (review) review.textContent = `(${product.reviewCount || 0} Review)`;
        if (addCart) {
            addCart.href = '#';
            addCart.dataset.addCart = String(product.id || '');
        }

        addBuyNowAction(document.querySelector('.sa-product-action-row') || document.querySelector('.mt-shop-details__quantity-wrap'), product.id);
        if (infoRows[0]) infoRows[0].innerHTML = `Vendor: <span>${escapeHtml(product.vendor || 'Seller Africa')}</span>`;
        if (infoRows[1]) infoRows[1].innerHTML = `Brand: <span>${escapeHtml(productBrandName(product))}</span>`;
        if (infoRows[2]) infoRows[2].innerHTML = `SKU: <span>${escapeHtml(product.sku || 'N/A')}</span>`;
        if (infoRows[3]) infoRows[3].innerHTML = `Stock: <span>${escapeHtml((product.stock || 'in_stock').replace(/_/g, ' '))}</span>`;
        renderProductReviews(product);
    }

    function reviewStars(rating) {
        const value = Math.max(0, Math.min(5, Number(rating || 0)));
        return [1, 2, 3, 4, 5].map((star) => `<i class="${star <= value ? 'fa-solid' : 'fa-light'} fa-star"></i>`).join('');
    }

    function renderProductReviews(product) {
        const panel = document.querySelector('#reviews .product-details-review');
        if (!panel || !product) return;
        const reviews = Array.isArray(product.reviews) ? product.reviews : [];
        const eligibility = product.reviewEligibility || {};
        const average = Number(product.averageRating || 0) || (reviews.length
            ? reviews.reduce((sum, review) => sum + Number(review.rating || 0), 0) / reviews.length
            : Number(product.rating || 0));
        const reviewList = reviews.length ? reviews.map((review) => `
            <article class="sa-product-review">
               <div class="sa-product-review__head">
                  <strong>${escapeHtml(review.title || 'Customer review')}</strong>
                  <span>${reviewStars(review.rating)}</span>
               </div>
               <p>${escapeHtml(review.body || '')}</p>
               <small>${escapeHtml(review.author || 'Customer')} · ${escapeHtml(String(review.created_at || '').slice(0, 10))}</small>
               ${Array.isArray(review.images) && review.images.length ? `<div class="sa-product-review__images">${review.images.map((image) => `<img src="${escapeAttr(image)}" alt="Review image" loading="lazy">`).join('')}</div>` : ''}
            </article>
        `).join('') : '<div class="sa-shop-empty"><h4>No reviews yet</h4><p>Be the first to review this product.</p></div>';

        const form = eligibility.canReview ? `
            <form class="sa-review-form" data-product-review-form enctype="multipart/form-data">
               <input type="hidden" name="csrf_token" value="${escapeAttr(config.csrf || '')}">
               <input type="hidden" name="product_id" value="${escapeAttr(product.id || '')}">
               <input type="hidden" name="rating" value="5" data-review-rating-value>
               <input class="sa-review-honeypot" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
               <label><span>Your rating</span><div class="sa-review-stars" data-review-stars>${[1, 2, 3, 4, 5].map((star) => `<button type="button" data-review-star="${star}" aria-label="${star} stars"><i class="fa-solid fa-star"></i></button>`).join('')}</div></label>
               <div class="sa-review-form__grid">
                  <label><span>Your name</span><input name="author_name" maxlength="190" autocomplete="name" placeholder="Name shown with review"></label>
                  <label><span>Your email</span><input type="email" name="author_email" maxlength="190" autocomplete="email" placeholder="Used only to prevent abuse"></label>
               </div>
               <label><span>Review title</span><input name="title" maxlength="190" placeholder="What stood out?"></label>
               <label><span>Your review</span><textarea name="body" required placeholder="Tell other customers about the product."></textarea></label>
               <label><span>Review images</span><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
               <button type="submit" class="mt-btn"><span>Submit Review</span></button>
               <p data-review-status></p>
            </form>
        ` : `<div class="sa-shop-empty"><h4>Review already submitted</h4><p>${escapeHtml(eligibility.message || 'You have already reviewed this product.')}</p></div>`;

        panel.innerHTML = `
            <h3 class="mt-comments-title mb-25">Ratings and Reviews</h3>
            <div class="mt-product-details-range-wrap d-flex align-items-center mb-30">
               <div class="mt-product-details-range-rating d-flex align-items-center">
                  <h2 class="mt-product-details-range-count">${Number(average || 0).toFixed(1)}</h2>
                  <div class="mt-product-details-range-review">
                     <span>${reviewStars(Math.round(average || 0))}</span>
                     <p class="mt-6">(${Number(product.reviewCount || reviews.length).toLocaleString()} review${Number(product.reviewCount || reviews.length) === 1 ? '' : 's'})</p>
                  </div>
               </div>
            </div>
            <div class="product-details-comment pb-40">${form}</div>
            <div class="sa-product-review-list">${reviewList}</div>
        `;
    }

    function prepareCheckoutForm() {
        if (config.page !== 'checkout') return;

        const forms = document.querySelectorAll('.mt-checkout-bill-form form');
        const contactInputs = forms[0]?.querySelectorAll('input') || [];
        const addressInputs = forms[1]?.querySelectorAll('input') || [];
        const customer = config.customer || {};
        const fullName = text(customer.display_name || '');
        const nameParts = fullName.split(/\s+/).filter(Boolean);
        const names = [
            [contactInputs[0], 'first_name', 'First name'],
            [contactInputs[1], 'last_name', 'Last name'],
            [contactInputs[2], 'company', 'Company name'],
            [contactInputs[3], 'email', 'Email address'],
            [contactInputs[4], 'phone', 'Phone number'],
            [addressInputs[0], 'country_code', 'US'],
            [addressInputs[1], 'postcode', 'Postcode / ZIP'],
            [addressInputs[2], 'city', 'Town / City'],
            [addressInputs[3], 'address_line1', 'Street address'],
            [addressInputs[4], 'address_line2', 'Apartment, suite, unit, etc.'],
            [addressInputs[5], 'state', 'State / County'],
        ];

        names.forEach(([input, name, placeholder]) => {
            if (!input) return;
            input.name = name;
            input.autocomplete = name;
            input.placeholder = placeholder;
            input.required = ['first_name', 'last_name', 'email', 'phone', 'address_line1', 'country_code', 'state', 'city', 'postcode'].includes(name);
            if (input.required) {
                input.setAttribute('aria-required', 'true');
                const label = input.closest('.mt-checkout-input')?.querySelector('label');
                if (label && !label.textContent.includes('*')) label.append(' *');
            }
        });

        if (contactInputs[0] && !contactInputs[0].value && nameParts.length) contactInputs[0].value = nameParts[0] || '';
        if (contactInputs[1] && !contactInputs[1].value && nameParts.length > 1) contactInputs[1].value = nameParts.slice(1).join(' ');
        if (contactInputs[0] && customer.first_name) contactInputs[0].value = customer.first_name;
        if (contactInputs[1] && customer.last_name) contactInputs[1].value = customer.last_name;
        if (contactInputs[3] && customer.email) contactInputs[3].value = customer.email;
        if (contactInputs[4] && customer.phone) contactInputs[4].value = customer.phone;

        const address = customer.address || {};
        if (addressInputs[0] && address.country_code) addressInputs[0].value = address.country_code;
        if (addressInputs[1] && address.postcode) addressInputs[1].value = address.postcode;
        if (addressInputs[2] && address.city) addressInputs[2].value = address.city;
        if (addressInputs[3] && address.address_line1) addressInputs[3].value = address.address_line1;
        if (addressInputs[4] && address.address_line2) addressInputs[4].value = address.address_line2;
        if (addressInputs[5] && address.state) addressInputs[5].value = address.state;

        if (addressInputs[0]) {
            const input = addressInputs[0];
            const select = document.createElement('select');
            select.name = 'country_code';
            select.id = input.id;
            select.className = input.className;
            select.required = true;
            select.autocomplete = 'country';
            select.setAttribute('aria-label', 'Delivery country');
            const codes = 'US GB NG CA AF AL DZ AS AD AO AI AQ AG AR AM AW AU AT AZ BS BH BD BB BY BE BZ BJ BM BT BO BQ BA BW BV BR IO BN BG BF BI CV KH CM KY CF TD CL CN CX CC CO KM CG CD CK CR CI HR CU CW CY CZ DK DJ DM DO EC EG SV GQ ER EE SZ ET FK FO FJ FI FR GF PF TF GA GM GE DE GH GI GR GL GD GP GU GT GG GN GW GY HT HM VA HN HK HU IS IN ID IR IQ IE IM IL IT JM JP JE JO KZ KE KI KP KR KW KG LA LV LB LS LR LY LI LT LU MO MG MW MY MV ML MT MH MQ MR MU YT MX FM MD MC MN ME MS MA MZ MM NA NR NP NL NC NZ NI NE NU NF MK MP NO OM PK PW PS PA PG PY PE PH PN PL PT PR QA RE RO RU RW BL SH KN LC MF PM VC WS SM ST SA SN RS SC SL SG SX SK SI SB SO ZA GS SS ES LK SD SR SJ SE CH SY TW TJ TZ TH TL TG TK TO TT TN TR TM TC TV UG UA AE UM UY UZ VU VE VN VG VI WF EH YE ZM ZW'.split(' ');
            const names = typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames(['en'], { type: 'region' }) : null;
            select.add(new Option('Select your country', ''));
            codes.forEach(code => select.add(new Option(names ? names.of(code) : code, code)));
            const saved = (input.value || '').trim();
            select.value = codes.includes(saved.toUpperCase()) ? saved.toUpperCase() : (codes.find(code => names?.of(code).toLowerCase() === saved.toLowerCase()) || '');
            select.style.cssText = 'width:100%;min-height:52px;padding:12px;border:1px solid #dceae2;background:#fff;color:#16372a;';
            input.replaceWith(select);
        }

        const notes = forms[1]?.querySelector('textarea');
        if (notes) notes.name = 'customer_note';

        if (forms[1]) {
            forms[1].closest('.mt-checkout-bill-form').previousElementSibling?.querySelector('h3')?.replaceChildren(document.createTextNode('Shipping address'));
            const billing = document.createElement('section');
            billing.innerHTML = '<h3>Billing address</h3><label style="display:flex;gap:12px;align-items:center;margin:20px 0"><input type="checkbox" name="billing_same_as_shipping" value="1" checked style="width:20px;height:20px"> Same as shipping address</label><div data-billing-fields hidden class="row"></div>';
            const fields = billing.querySelector('[data-billing-fields]');
            fields.style.display = 'none';
            ['first_name', 'last_name', 'address_line1', 'address_line2', 'city', 'state', 'postcode', 'country_code'].forEach(name => {
                const original = document.querySelector(`.mt-checkout-bill-form [name="${name}"]`);
                if (!original) return;
                const field = original.cloneNode(true);
                field.name = `billing_${name}`;
                field.id = `sa_billing_${name}`;
                field.value = original.value;
                field.disabled = true;
                field.autocomplete = `billing ${name === 'country_code' ? 'country' : ({first_name:'given-name',last_name:'family-name',address_line1:'address-line1',address_line2:'address-line2',city:'address-level2',state:'address-level1',postcode:'postal-code'}[name])}`;
                const holder = document.createElement('div');
                holder.className = 'col-md-6 mt-checkout-input';
                const label = document.createElement('label');
                label.htmlFor = field.id;
                label.textContent = ({first_name:'First name',last_name:'Last name',address_line1:'Street address',address_line2:'Apartment / suite (optional)',city:'City',state:'State / province',postcode:'Postal / ZIP code',country_code:'Country'}[name]) + (field.required ? ' *' : '');
                holder.append(label, field);
                fields.append(holder);
            });
            billing.querySelector('input[type="checkbox"]').addEventListener('change', event => {
                fields.hidden = event.target.checked;
                fields.style.display = event.target.checked ? 'none' : '';
                fields.querySelectorAll('input, select').forEach(field => field.disabled = event.target.checked);
            });
            forms[1].append(billing);
        }

        const accountOption = document.querySelector('#create_free_account')?.closest('.mt-checkout-option-wrapper');
        if (accountOption) {
            const loggedIn = Boolean(customer.id);
            accountOption.innerHTML = `
                <div class="sa-checkout-mode" role="radiogroup" aria-label="Checkout mode">
                   <label><input type="radio" name="checkout_mode" value="guest"${loggedIn ? '' : ' checked'}> Guest checkout</label>
                   <label><input type="radio" name="checkout_mode" value="customer"${loggedIn ? ' checked' : ' disabled'}> My account</label>
                   <label><input type="radio" name="checkout_mode" value="create_account"${loggedIn ? ' disabled' : ''}> Create account</label>
                </div>
                <div class="sa-account-password" data-account-password hidden>
                   <div class="mt-checkout-input">
                      <label>Password <span>*</span></label>
                      <input type="password" name="account_password" autocomplete="new-password" placeholder="Create password">
                   </div>
                   <p>Use at least 8 characters. Your order will be linked to the new customer account.</p>
                </div>
            `;
        }

        const action = document.querySelector('.mt-checkout-btn-wrapper a');
        if (action) {
            action.href = '#';
            action.dataset.checkoutSubmit = '1';
            action.setAttribute('role', 'button');
            action.querySelector('span') ? action.querySelector('span').textContent = 'Review order' : action.textContent = 'Review order';
        }

        forms.forEach((form) => {
            form.action = '#';
            form.addEventListener('submit', (event) => event.preventDefault());
        });
    }

    function prepareCartPage() {
        if (config.page !== 'cart') return;

        document.body.classList.add('sa-cart-page');
        document.querySelector('.mt-checkout-bill-area')?.closest('[class*="col-"]')?.remove();
        document.querySelector('.mt-checkout-payment')?.remove();
        const summaryColumn = document.querySelector('.mt-checkout-place')?.closest('[class*="col-"]');
        if (summaryColumn) {
            summaryColumn.classList.remove('offset-1', 'col-lg-5');
            summaryColumn.classList.add('col-lg-8', 'mx-auto');
        }

        const title = document.querySelector('.mt-checkout-place-title');
        if (title) {
            title.firstChild.textContent = 'Cart Summary ';
        }
    }

    function populatePaymentMethods() {
        if (config.page !== 'checkout') return;
        const box = document.querySelector('.mt-checkout-payment');
        const methods = Array.isArray(config.paymentMethods) ? config.paymentMethods : [];
        if (!box) return;

        box.innerHTML = `
            <h4 class="mt-checkout-payment-title mb-15">Payment Method</h4>
            ${methods.length ? methods.map((method, index) => `
                <div class="mt-checkout-payment-item">
                   <input type="radio" id="sa_payment_${escapeAttr(method.id)}" name="payment_method_id" value="${escapeAttr(method.id)}"${index === 0 ? ' checked' : ''}>
                   <label for="sa_payment_${escapeAttr(method.id)}">${escapeHtml(method.name || method.code || 'Payment method')}</label>
                   <div class="mt-checkout-payment-desc sa-payment-desc">
                      <p>${escapeHtml(method.description || 'Your payment will be processed securely.')}</p>
                   </div>
                </div>
            `).join('') : `
                <div class="mt-checkout-payment-item">
                   <p>No active payment method is available. Please contact support.</p>
                </div>
            `}
        `;
    }

    function selectedCheckoutMode() {
        return document.querySelector('input[name="checkout_mode"]:checked')?.value || (config.customer?.id ? 'customer' : 'guest');
    }

    function updateCheckoutMode() {
        if (config.page !== 'checkout') return;
        const mode = selectedCheckoutMode();
        const passwordBox = document.querySelector('[data-account-password]');
        const passwordInput = passwordBox?.querySelector('input[name="account_password"]');
        if (passwordBox) passwordBox.hidden = mode !== 'create_account';
        if (passwordInput) passwordInput.required = mode === 'create_account';
    }

    function checkoutErrorBox() {
        let box = document.querySelector('[data-checkout-errors]');
        const place = document.querySelector('.mt-checkout-place');
        if (!box && place) {
            place.insertAdjacentHTML('afterbegin', '<div class="sa-checkout-errors" data-checkout-errors hidden></div>');
            box = document.querySelector('[data-checkout-errors]');
        }

        return box;
    }

    function showCheckoutError(message) {
        const box = checkoutErrorBox();
        if (box) {
            box.textContent = message;
            box.hidden = false;
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        toast('error', message);
    }

    function clearCheckoutError() {
        const box = checkoutErrorBox();
        if (box) {
            box.textContent = '';
            box.hidden = true;
        }
    }

    function validateCheckoutForm() {
        const requiredFields = [
            ['first_name', 'Enter your first name.'],
            ['last_name', 'Enter your last name.'],
            ['email', 'Enter a valid email address.'],
            ['phone', 'Enter your phone number.'],
            ['address_line1', 'Enter your delivery address.'],
            ['country_code', 'Select your delivery country.'],
            ['state', 'Enter your delivery state, province or region.'],
            ['city', 'Enter your delivery city.'],
            ['postcode', 'Enter your delivery postal / ZIP code.'],
        ];

        for (const [name, message] of requiredFields) {
            const field = document.querySelector(`.mt-checkout-bill-form [name="${name}"]`);
            if (!field || text(field.value) === '') {
                field?.setAttribute('aria-invalid', 'true');
                field?.setCustomValidity(message);
                field?.reportValidity();
                field?.focus();
                return message;
            }
        }

        for (const field of document.querySelectorAll('[data-billing-fields] input:enabled, [data-billing-fields] select:enabled')) {
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return 'Complete the required billing address fields.';
            }
        }

        const email = document.querySelector('.mt-checkout-bill-form [name="email"]');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text(email.value))) {
            email.focus();
            return 'Enter a valid email address.';
        }

        if (!document.querySelector('.mt-checkout-payment input[name="payment_method_id"]:checked')) {
            return 'Select a payment method.';
        }

        if (selectedCheckoutMode() === 'create_account') {
            const password = document.querySelector('[name="account_password"]');
            if (!password || String(password.value || '').length < 8) {
                password?.focus();
                return 'Enter an account password with at least 8 characters.';
            }
        }

        return '';
    }

    function populateCheckoutSummary() {
        if (!['cart', 'checkout'].includes(config.page || '')) return;
        const items = cartItems;
        const list = document.querySelector('.mt-order-info-list ul');
        if (!list) return;

        const subtotal = cartSubtotal(items);
        const rows = items.length ? items.map((item) => `
            <li class="mt-order-info-list-desc" data-sa-live="cart-item">
               <div class="d-flex align-items-center">
                  <div class="mt-order-info-list-img sa-image-skeleton">
                     <img loading="lazy" src="${escapeAttr(item.image || item.fallbackImage || transparentPixel)}" alt="${escapeAttr(item.name || 'Product')}">
                  </div>
                  <div class="mt-order-info-list-content">
                     <h4 class="mt-order-info-list-title">${escapeHtml(item.name || 'Seller Africa product')}</h4>
                     <span>${escapeHtml(item.vendor || 'Seller Africa')}</span>
                     <p>Quantity (${Number(item.quantity || 1).toLocaleString()})</p>
                     <div class="sa-cart-row-actions">
                        <button type="button" data-cart-action="decrement" data-product-id="${escapeAttr(item.id || '')}">-</button>
                        <strong>${Number(item.quantity || 1).toLocaleString()}</strong>
                        <button type="button" data-cart-action="increment" data-product-id="${escapeAttr(item.id || '')}">+</button>
                        <button type="button" data-cart-action="remove" data-product-id="${escapeAttr(item.id || '')}">Remove</button>
                     </div>
                  </div>
               </div>
               <div class="mt-order-info-list-price">
                  <span>${escapeHtml(formatMoney(Number(item.rawPrice || 0) * Number(item.quantity || 1), item.currency || defaultCurrency.code))}</span>
               </div>
            </li>
        `).join('') : `
            <li class="mt-order-info-list-desc" data-sa-live="cart-empty">
               <div class="mt-order-info-list-content">
                  <h4 class="mt-order-info-list-title">Your cart is empty</h4>
                  <p>Start shopping from the live marketplace catalog.</p>
               </div>
            </li>
        `;

        const totalLabel = config.page === 'cart' ? 'Total' : 'Subtotal';
        const checkoutOnlyTotals = config.page === 'checkout' ? `
            <li class="mt-order-info-list-shipping">
               <span>Shipping</span>
               <span class="price" data-checkout-shipping>${latestCheckoutQuote?.shipping || 'Calculated from address'}</span>
            </li>
        ` : '';

        list.innerHTML = rows + `
            <li class="mt-order-info-list-subtotal">
               <span>${totalLabel}</span>
               <span class="price" data-checkout-subtotal>${escapeHtml(`${currencyByCode(selectedCurrency).symbol || selectedCurrency} ${Number(subtotal || 0).toLocaleString(undefined, { minimumFractionDigits: subtotal > 999 ? 0 : 2, maximumFractionDigits: subtotal > 999 ? 0 : 2 })}`)}</span>
            </li>
            ${checkoutOnlyTotals}
            <li class="mt-order-info-list-total">
               <span>Total</span>
               <span class="price" data-checkout-total>${escapeHtml(`${currencyByCode(selectedCurrency).symbol || selectedCurrency} ${Number(subtotal || 0).toLocaleString(undefined, { minimumFractionDigits: subtotal > 999 ? 0 : 2, maximumFractionDigits: subtotal > 999 ? 0 : 2 })}`)}</span>
            </li>
        `;

        items.forEach((item) => {
            const row = list.querySelector(`[data-product-id="${cssEscape(String(item.id || ''))}"]`)?.closest('.mt-order-info-list-desc');
            const img = row?.querySelector('.mt-order-info-list-img img');
            setImage(img, item.image || item.fallbackImage || '', item.fallbackImage || '');
        });

        const heading = document.querySelector('.mt-breadcrumb-title');
        const section = document.querySelector('.mt-section-title');
        const action = document.querySelector('.mt-checkout-btn-wrapper a');
        if (config.page === 'cart') {
            if (heading) heading.innerHTML = 'Cart <span>Page</span>';
            if (section) section.innerHTML = 'Cart <span>Details</span>';
            if (action) {
                action.href = config.urls?.checkout || '#';
                action.querySelector('span') ? action.querySelector('span').textContent = 'Continue To Checkout' : action.textContent = 'Continue To Checkout';
                action.classList.toggle('disabled', !items.length);
            }
        } else if (action) {
            action.href = '#';
            action.dataset.checkoutSubmit = '1';
            action.querySelector('span') ? action.querySelector('span').textContent = 'Review order' : action.textContent = 'Review order';
            action.classList.toggle('disabled', !items.length);
        }
    }

    function checkoutBody() {
        const body = new URLSearchParams();
        body.set('csrf_token', config.csrf || '');
        body.set('currency', selectedCurrency);
        body.set('checkout_mode', selectedCheckoutMode());
        body.set('create_account', selectedCheckoutMode() === 'create_account' ? '1' : '0');
        document.querySelectorAll('.mt-checkout-bill-form input[name], .mt-checkout-bill-form select[name], .mt-checkout-bill-form textarea[name], .mt-checkout-payment input[name]:checked').forEach((field) => {
            if (field.disabled) return;
            if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
            body.set(field.name, field.value || '');
        });

        return body;
    }

    async function showCheckoutReview() {
        window.clearTimeout(checkoutQuoteTimer);
        const body = checkoutBody();
        const response = await fetch(config.endpoints.checkoutQuote, {method: 'POST', body});
        const quote = await response.json();
        if (!quote.ok) throw new Error(quote.message || 'Unable to calculate shipping. Please check your delivery address.');
        applyCheckoutQuote(quote);
        reviewedCheckout = body;
        body.set('reviewed_total', String(quote.raw.total));
        const address = prefix => [
            [body.get(prefix + 'first_name'), body.get(prefix + 'last_name')].filter(Boolean).join(' '),
            body.get(prefix + 'address_line1'), body.get(prefix + 'address_line2'),
            [body.get(prefix + 'city'), body.get(prefix + 'state'), body.get(prefix + 'postcode')].filter(Boolean).join(', '),
            document.querySelector(`[name="${prefix}country_code"] option:checked`)?.textContent
        ].filter(Boolean).map(escapeHtml).join('<br>');
        const method = document.querySelector('[name="payment_method_id"]:checked');
        const payment = document.querySelector(`label[for="${method.id}"]`)?.textContent || '';
        const area = document.querySelector('.mt-checkout-bill-area')?.closest('.row');
        if (!area) throw new Error('Unable to show order review. Refresh checkout and try again.');
        let review = document.querySelector('[data-checkout-review]');
        if (!review) {
            review = document.createElement('section');
            review.dataset.checkoutReview = '1';
            review.style.cssText = 'max-width:900px;margin:0 auto;padding:24px;border:1px solid #dceae2;border-radius:8px;background:white;overflow-wrap:anywhere';
            area.after(review);
        }
        review.innerHTML = `<h2 tabindex="-1">Review your order</h2><p>Check your details before continuing to payment.</p><div class="row"><div class="col-md-6"><h3>Shipping address</h3><p>${address('')}</p></div><div class="col-md-6"><h3>Billing address</h3><p>${address(body.get('billing_same_as_shipping') === '1' ? '' : 'billing_')}</p></div></div><p>${escapeHtml(body.get('email'))} · ${escapeHtml(body.get('phone'))}</p><ul style="padding:0;list-style:none">${cartItems.map(item => `<li style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid #dceae2;padding:16px 0;overflow-wrap:anywhere"><span>${escapeHtml(item.name)} × ${Number(item.quantity || 1)}</span><strong>${escapeHtml(formatMoney(Number(item.rawPrice) * Number(item.quantity || 1), item.currency))}</strong></li>`).join('')}</ul><p>Subtotal: <strong>${escapeHtml(quote.subtotal)}</strong></p><p>Shipping: <strong>${escapeHtml(quote.shipping)}</strong></p><p>Tax: <strong>${escapeHtml(quote.tax)}</strong></p><h3>Total: ${escapeHtml(quote.total)}</h3><p>Payment method: ${escapeHtml(payment)}</p><p>${escapeHtml(body.get('customer_note') || '')}</p><div role="alert" data-review-error></div><button type="button" data-checkout-edit class="mt-btn">Edit details</button> <button type="button" data-checkout-submit data-confirm-payment class="mt-btn"><span>Confirm and pay</span></button>`;
        area.hidden = true;
        area.style.display = 'none';
        review.hidden = false;
        review.querySelector('h2').focus();
        review.scrollIntoView({behavior:'smooth', block:'start'});
    }

    function applyCheckoutQuote(quote) {
        if (!quote) return;
        latestCheckoutQuote = quote;
        const subtotal = document.querySelector('[data-checkout-subtotal]');
        const shipping = document.querySelector('[data-checkout-shipping]');
        const total = document.querySelector('[data-checkout-total]');
        if (subtotal && quote.subtotal) subtotal.textContent = quote.subtotal;
        if (shipping && quote.shipping) shipping.textContent = quote.shipping;
        if (total && quote.total) total.textContent = quote.total;
    }

    async function requestCheckoutQuote() {
        if (config.page !== 'checkout' || !config.endpoints?.checkoutQuote) return;
        const body = checkoutBody();
        try {
            const response = await fetch(config.endpoints.checkoutQuote, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body
            });
            const json = await response.json();
            if (json.ok) applyCheckoutQuote(json);
            else {
                latestCheckoutQuote = null;
                const shipping = document.querySelector('[data-checkout-shipping]');
                if (shipping) shipping.textContent = json.message || 'Enter address to calculate';
            }
        } catch (error) {
            const shipping = document.querySelector('[data-checkout-shipping]');
            latestCheckoutQuote = null;
            if (shipping) shipping.textContent = 'Unable to calculate shipping. Check your connection and delivery address, then try again.';
        }
    }

    function scheduleCheckoutQuote() {
        if (config.page !== 'checkout') return;
        window.clearTimeout(checkoutQuoteTimer);
        checkoutQuoteTimer = window.setTimeout(requestCheckoutQuote, 350);
    }

    function renderOrderConfirmation(order, payment) {
        const holder = document.querySelector('.mt-checkout-place');
        if (!holder || !order) return;
        const existing = holder.querySelector('.sa-order-confirmation');
        if (existing) existing.remove();

        holder.insertAdjacentHTML('afterbegin', `
            <div class="sa-order-confirmation">
               <strong>Order ${escapeHtml(order.number || '')} created.</strong>
               <span>Total: ${escapeHtml(order.total || '')}</span>
               <span>Payment: ${escapeHtml(payment?.method || 'Selected method')} (${escapeHtml(payment?.status || 'pending')})</span>
            </div>
        `);
    }

    function wireCheckout() {
        if (config.page === 'checkout') {
            updateCheckoutMode();
            document.addEventListener('change', (event) => {
                if (event.target.closest('input[name="checkout_mode"]')) {
                    updateCheckoutMode();
                }
            });

            document.querySelectorAll('.mt-checkout-bill-form input, .mt-checkout-bill-form select, .mt-checkout-bill-form textarea, .mt-checkout-payment input').forEach((field) => {
                const clearFieldError = () => { field.setCustomValidity(''); field.removeAttribute('aria-invalid'); };
                field.addEventListener('input', clearFieldError);
                field.addEventListener('change', clearFieldError);
                field.addEventListener('input', clearCheckoutError);
                field.addEventListener('change', clearCheckoutError);
            });
            document.querySelectorAll('.mt-checkout-bill-form [name="country_code"], .mt-checkout-bill-form [name="state"], .mt-checkout-bill-form [name="postcode"], .mt-checkout-bill-form [name="city"], .mt-checkout-bill-form [name="address_line1"]').forEach((field) => {
                field.addEventListener('input', scheduleCheckoutQuote);
                field.addEventListener('change', scheduleCheckoutQuote);
            });
            scheduleCheckoutQuote();
        }

        document.addEventListener('click', async (event) => {
            if (event.target.closest('[data-checkout-edit]')) {
                reviewedCheckout = null;
                document.querySelector('[data-checkout-review]').hidden = true;
                document.querySelector('.mt-checkout-bill-area').closest('.row').hidden = false;
                document.querySelector('.mt-checkout-bill-area').closest('.row').style.display = '';
                return;
            }
            const trigger = event.target.closest('[data-checkout-submit]');
            if (!trigger) return;
            event.preventDefault();
            if (!cartItems.length || trigger.classList.contains('disabled')) {
                showCheckoutError('Your cart is empty.');
                return;
            }

            const validationMessage = validateCheckoutForm();
            if (validationMessage) {
                showCheckoutError(validationMessage);
                return;
            }

            trigger.classList.add('disabled');
            const label = trigger.querySelector('span');
            const previous = label ? label.textContent : trigger.textContent;
            if (label) label.textContent = 'Creating Order...';
            clearCheckoutError();

            try {
                if (!trigger.hasAttribute('data-confirm-payment')) {
                    if (label) label.textContent = 'Calculating shipping...';
                    await showCheckoutReview();
                    return;
                }
                if (!reviewedCheckout) throw new Error('Review your order again before paying.');
                const response = await fetch(config.endpoints?.checkout || '', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: reviewedCheckout
                });
                const raw = await response.text();
                let json = null;
                try {
                    json = JSON.parse(raw);
                } catch (parseError) {
                    json = { ok: false, message: raw || 'Checkout failed before the server returned order details.' };
                }
                if (!json.ok) {
                    const reviewError = document.querySelector('[data-review-error]');
                    if (reviewError) reviewError.textContent = json.message || 'Unable to start payment. Edit details and review your order again.';
                    showCheckoutError(json.message || 'Unable to create order.');
                    return;
                }
                cartItems = [];
                renderMiniCart(cartItems);
                populateCheckoutSummary();
                renderOrderConfirmation(json.order, json.payment);
                toast('success', json.message || 'Order created.');
                if (json.redirect_url) {
                    window.location.href = json.redirect_url;
                }
            } catch (error) {
                const message = error.message || 'Checkout request failed. Check your connection and try again.';
                const reviewError = document.querySelector('[data-review-error]');
                if (reviewError) reviewError.textContent = message;
                showCheckoutError(message);
            } finally {
                trigger.classList.remove('disabled');
                if (label) label.textContent = previous || 'Confirm Order';
            }
        });
    }

    function wireProductReviews() {
        document.addEventListener('click', (event) => {
            const star = event.target.closest('[data-review-star]');
            if (!star) return;
            event.preventDefault();
            const form = star.closest('[data-product-review-form]');
            const value = Number(star.dataset.reviewStar || 5);
            const input = form?.querySelector('[data-review-rating-value]');
            if (input) input.value = String(value);
            form?.querySelectorAll('[data-review-star]').forEach((button) => {
                const active = Number(button.dataset.reviewStar || 0) <= value;
                const icon = button.querySelector('i');
                if (icon) icon.className = active ? 'fa-solid fa-star' : 'fa-light fa-star';
            });
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-product-review-form]');
            if (!form) return;
            event.preventDefault();
            const status = form.querySelector('[data-review-status]');
            const button = form.querySelector('button[type="submit"]');
            if (!config.endpoints?.reviews) return;

            if (status) status.textContent = 'Submitting review...';
            if (button) button.disabled = true;
            try {
                const response = await fetch(config.endpoints.reviews, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form)
                });
                const json = await response.json();
                if (!json.ok) {
                    if (status) status.textContent = json.message || 'Review could not be submitted.';
                    toast('error', json.message || 'Review could not be submitted.');
                    return;
                }
                form.reset();
                if (status) status.textContent = json.message || 'Review submitted.';
                toast('success', json.message || 'Review submitted.');
                window.setTimeout(() => window.location.reload(), 900);
            } catch (error) {
                if (status) status.textContent = 'Review request failed.';
                toast('error', 'Review request failed.');
            } finally {
                if (button) button.disabled = false;
            }
        });
    }

    function refreshTemplateSwipers() {
        window.setTimeout(() => {
            const swiperNodes = document.querySelectorAll('.swiper');
            swiperNodes.forEach((node) => {
                if (node.swiper && typeof node.swiper.update === 'function') {
                    node.swiper.update();
                    if (typeof node.swiper.slideTo === 'function') node.swiper.slideTo(0, 0);
                }
            });
        }, 80);
    }

    function vendorProductCard(product) {
        const productUrl = product?.url || config.urls?.shop || '#';
        const image = product?.has_image && product?.image
            ? `<a href="${escapeAttr(productUrl)}" class="sa-vendor-product__image sa-image-skeleton"><img src="${transparentPixel}" data-src="${escapeAttr(product.image)}" alt="${escapeAttr(product.name || 'Seller Africa product')}"></a>`
            : `<a href="${escapeAttr(productUrl)}" class="sa-vendor-product__image sa-vendor-product__image--placeholder"><i class="fa-solid fa-box-open"></i></a>`;

        return `
            <article class="sa-vendor-product">
                ${image}
                <div>
                    <h3><a href="${escapeAttr(productUrl)}">${escapeHtml(product?.name || 'Seller Africa product')}</a></h3>
                    <p>${escapeHtml(product?.description || 'Product details are being prepared.')}</p>
                    <div class="sa-vendor-product__price">
                        <strong>${escapeHtml(product?.price || '')}</strong>
                        ${product?.regular_price ? `<del>${escapeHtml(product.regular_price)}</del>` : ''}
                    </div>
                </div>
            </article>
        `;
    }

    function vendorDirectoryCard(vendor) {
        const vendorUrl = vendor?.url || config.urls?.vendors || '#';
        const banner = vendor?.has_banner && vendor?.banner
            ? `<img src="${transparentPixel}" data-src="${escapeAttr(vendor.banner)}" alt="${escapeAttr(vendor.name || 'Vendor store')}">`
            : '<span><i class="fa-solid fa-store"></i></span>';
        const logo = vendor?.has_logo && vendor?.logo
            ? `<img src="${transparentPixel}" data-src="${escapeAttr(vendor.logo)}" alt="${escapeAttr(vendor.name || 'Vendor logo')}">`
            : `<span class="sa-vendor-logo-placeholder" aria-label="${escapeAttr(vendor?.name || 'Vendor')} store logo"><i class="fa-solid fa-store"></i></span>`;

        return `
            <article class="sa-vendor-card">
                <a href="${escapeAttr(vendorUrl)}" class="sa-vendor-card__banner${vendor?.has_banner ? ' sa-image-skeleton' : ' sa-vendor-card__banner--placeholder'}">
                    ${banner}
                </a>
                <div class="sa-vendor-card__body">
                    ${logo}
                    <div>
                        <h2><a href="${escapeAttr(vendorUrl)}">${escapeHtml(vendor?.name || 'Seller Africa vendor')}</a></h2>
                        <p>${escapeHtml(vendor?.description || 'This vendor is preparing their public store details.')}</p>
                        <a href="${escapeAttr(vendorUrl)}" class="sa-inline-link">View vendor details</a>
                    </div>
                </div>
            </article>
        `;
    }

    async function loadVendorProducts() {
        const container = document.querySelector('[data-vendor-products-url]');
        if (!container || !container.dataset.vendorProductsUrl) return;

        try {
            const response = await fetch(container.dataset.vendorProductsUrl, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await response.json();
            const products = Array.isArray(json.products) ? json.products.slice(0, 5) : [];

            if (!json.ok || products.length === 0) {
                container.outerHTML = '<div class="sa-shop-empty"><h4>No active products yet</h4><p>This vendor has not published products for public shopping.</p></div>';
                return;
            }

            container.innerHTML = products.map(vendorProductCard).join('');
            container.querySelectorAll('.sa-vendor-product__image img[data-src]').forEach((img) => {
                setImage(img, img.dataset.src || '', '');
            });
        } catch (error) {
            container.outerHTML = '<div class="sa-shop-empty"><h4>Products could not load</h4><p>Please refresh the page to try again.</p></div>';
        }
    }

    function hydrateVendorGridImages(grid) {
        if (!grid) return;
        grid.querySelectorAll('.sa-vendor-card__banner img[data-src], .sa-vendor-card__body > img[data-src]').forEach((img) => {
            setImage(img, img.dataset.src || img.getAttribute('src') || '', '');
        });
    }

    function wireVendorDirectory() {
        const grid = document.querySelector('[data-vendors-grid]');
        const sentinel = document.querySelector('[data-vendors-sentinel]');
        if (!grid || !sentinel || !grid.dataset.vendorsUrl) return;

        let loading = false;
        let page = Number(grid.dataset.vendorsPage || 1) || 1;
        const perPage = Number(grid.dataset.vendorsPerPage || 25) || 25;
        const total = Number(grid.dataset.vendorsTotal || 0) || 0;
        let loaded = Number(grid.dataset.vendorsLoaded || grid.children.length) || grid.children.length;

        const setStatus = (message) => {
            sentinel.innerHTML = `<span>${escapeHtml(message)}</span>`;
        };

        const loadNext = async () => {
            if (loading || loaded >= total) {
                if (loaded >= total) setStatus('All vendors are loaded.');
                return;
            }

            loading = true;
            setStatus('Loading more vendors...');
            const nextPage = page + 1;
            const url = new URL(grid.dataset.vendorsUrl, window.location.href);
            url.searchParams.set('page', String(nextPage));
            url.searchParams.set('per_page', String(perPage));

            try {
                const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const json = await response.json();
                const vendors = Array.isArray(json.vendors) ? json.vendors : [];
                if (!json.ok || vendors.length === 0) {
                    setStatus(loaded >= total ? 'All vendors are loaded.' : 'No more vendors found.');
                    return;
                }

                const wrapper = document.createElement('div');
                wrapper.innerHTML = vendors.map(vendorDirectoryCard).join('');
                const nodes = Array.from(wrapper.children);
                nodes.forEach((node) => grid.appendChild(node));
                hydrateVendorGridImages(grid);
                page = Number(json.page || nextPage) || nextPage;
                loaded += nodes.length;
                grid.dataset.vendorsPage = String(page);
                grid.dataset.vendorsLoaded = String(loaded);
                setStatus(loaded >= Number(json.total || total) ? 'All vendors are loaded.' : 'Scroll for more vendors.');
            } catch (error) {
                setStatus('Vendors could not load. Scroll again to retry.');
            } finally {
                loading = false;
            }
        };

        hydrateVendorGridImages(grid);
        if (loaded >= total) {
            setStatus('All vendors are loaded.');
            return;
        }

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) loadNext();
            }, { rootMargin: '500px 0px' });
            observer.observe(sentinel);
        } else {
            window.addEventListener('scroll', () => {
                const rect = sentinel.getBoundingClientRect();
                if (rect.top < window.innerHeight + 500) loadNext();
            }, { passive: true });
        }
    }

    function wireVendorContactForms() {
        document.querySelectorAll('[data-vendor-contact-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const endpoint = form.dataset.vendorContactUrl || '';
                const status = form.querySelector('[data-vendor-contact-status]');
                const button = form.querySelector('button[type="submit"]');
                const label = button?.querySelector('span');
                const previous = label ? label.textContent : '';
                if (!endpoint) return;

                if (status) {
                    status.className = 'sa-vendor-form-status';
                    status.textContent = 'Sending message...';
                }
                if (button) button.disabled = true;
                if (label) label.textContent = 'Sending...';

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new FormData(form)
                    });
                    const json = await response.json();
                    if (!json.ok) {
                        if (status) {
                            status.classList.add('is-error');
                            status.textContent = json.message || 'Unable to send your message.';
                        }
                        return;
                    }
                    form.reset();
                    if (status) {
                        status.classList.add('is-success');
                        status.textContent = json.message || 'Your message has been queued for this vendor.';
                    }
                    toast('success', json.message || 'Message queued.');
                } catch (error) {
                    if (status) {
                        status.classList.add('is-error');
                        status.textContent = 'Unable to send your message. Please try again.';
                    }
                } finally {
                    if (button) button.disabled = false;
                    if (label) label.textContent = previous || 'Send Message';
                }
            });
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    function editableCandidates() {
        const items = [];
        document.querySelectorAll('img').forEach((node) => {
            if (!node.closest('[data-sa-live]')) items.push({ node, type: 'image' });
        });
        document.querySelectorAll('[data-background]').forEach((node) => {
            if (!node.closest('[data-sa-live]')) items.push({ node, type: 'background' });
        });
        document.querySelectorAll('h1,h2,h3,h4,h5,h6,p,a,span').forEach((node) => {
            if (node.closest('[data-sa-live]')) return;
            if (node.children.length > 2) return;
            const value = text(node.textContent);
            if (value.length < 2 || value.length > 220) return;
            if (/^[0-9:]+$/.test(value)) return;
            items.push({ node, type: 'text' });
        });
        return items;
    }

    function valueOf(node, type) {
        if (type === 'image') return node.getAttribute('src') || '';
        if (type === 'background') return node.getAttribute('data-background') || '';
        return node.textContent || '';
    }

    function applyValue(node, type, value) {
        if (type === 'image') {
            setImage(node, value, node.dataset.saFallback || node.getAttribute('src') || '');
        } else if (type === 'background') {
            node.setAttribute('data-background', value);
            node.style.backgroundImage = `url("${value}")`;
        } else {
            node.textContent = value;
        }
    }

    function applyContentBlocks() {
        editableCandidates().forEach((item, index) => {
            const legacyKey = `${item.type}:${index}`;
            const key = `${pageKey}:${legacyKey}`;
            item.node.dataset.saEditableKey = key;
            item.node.dataset.saEditableType = item.type;
            if (blocks[key]?.value) {
                applyValue(item.node, item.type, blocks[key].value);
            } else if (pageKey === 'home' && blocks[legacyKey]?.value) {
                applyValue(item.node, item.type, blocks[legacyKey].value);
            }
        });
    }

    function setupAdminEditing() {
        if (!config.isSuperAdmin) return;

        const style = document.createElement('style');
        style.textContent = `
            .sa-edit-ring { outline: 2px dashed rgba(34,197,94,.45); outline-offset: 4px; }
            .sa-edit-button { position: absolute; z-index: 9999; border: 0; border-radius: 999px; background: #111; color: #fff; font: 600 12px/1 system-ui; padding: 8px 10px; box-shadow: 0 12px 30px rgba(0,0,0,.18); cursor: pointer; }
            .sa-edit-button:hover { background: #22c55e; color: #052e16; }
        `;
        document.head.appendChild(style);

        editableCandidates().forEach((item, index) => {
            const key = item.node.dataset.saEditableKey || `${pageKey}:${item.type}:${index}`;
            item.node.dataset.saEditableKey = key;
            item.node.dataset.saEditableType = item.type;

            item.node.addEventListener('mouseenter', () => item.node.classList.add('sa-edit-ring'));
            item.node.addEventListener('mouseleave', () => item.node.classList.remove('sa-edit-ring'));

            item.node.addEventListener('dblclick', (event) => {
                event.preventDefault();
                event.stopPropagation();
                editBlock(item.node, item.type, key);
            });
        });

        const floating = document.createElement('button');
        floating.type = 'button';
        floating.className = 'sa-edit-button';
        floating.textContent = 'Edit';
        floating.hidden = true;
        document.body.appendChild(floating);

        let active = null;
        document.addEventListener('mouseover', (event) => {
            const target = event.target.closest('[data-sa-editable-key]');
            if (!target || target.closest('[data-sa-live]')) return;
            active = target;
            const rect = target.getBoundingClientRect();
            floating.style.left = `${Math.max(8, rect.left + window.scrollX)}px`;
            floating.style.top = `${Math.max(8, rect.top + window.scrollY - 34)}px`;
            floating.hidden = false;
        });

        floating.addEventListener('click', () => {
            if (!active) return;
            editBlock(active, active.dataset.saEditableType || 'text', active.dataset.saEditableKey || '');
        });
    }

    async function editBlock(node, type, key) {
        const current = valueOf(node, type);
        const label = type === 'text' ? 'Enter new text' : 'Enter image URL or path';
        const next = window.prompt(label, current);
        if (next === null || next === current) return;

        const body = new URLSearchParams();
        body.set('csrf_token', config.csrf || '');
        body.set('key', key);
        body.set('type', type);
        body.set('value', next);

        try {
            const response = await fetch(config.endpoints?.content || '', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body
            });
            const json = await response.json();
            if (!json.ok) {
                toast('error', json.message || 'Unable to save homepage edit.');
                return;
            }
            applyValue(node, type, next);
            toast('success', 'Homepage block updated.');
        } catch (error) {
            toast('error', 'Unable to save homepage edit.');
        }
    }

    function init() {
        document.querySelectorAll('img').forEach((img) => {
            img.loading = 'lazy';
            img.decoding = 'async';
        });
        populateCategories();
        populateProducts();
        refreshMoneyDisplays();
        prepareShopFilters();
        populateProductDetails();
        prepareCartPage();
        prepareCheckoutForm();
        populatePaymentMethods();
        populateCheckoutSummary();
        normalizeMainMenu();
        applyBranding();
        hydrateVendorMedia();
        wireGoogleTranslate();
        wireNavigation();
        wireShopSearch();
        wireShopFilters();
        wireCart();
        wireCheckout();
        wireProductReviews();
        applyContentBlocks();
        setupAdminEditing();
        loadVendorProducts();
        wireVendorDirectory();
        wireVendorContactForms();
        refreshTemplateSwipers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
