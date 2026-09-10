/**
 * Main JavaScript File
 *
 * Champion Liquor Store Ltd - Global scripts
 *
 * Features:
 *   - Quantity selector (+ / -)
 *   - Active nav link highlighting
 *   - Newsletter form (frontend only)
 *   - Add to Cart (AJAX)
 *   - Cart quantity update (AJAX)
 *   - Cart item remove (AJAX)
 *   - Clear cart (AJAX)
 *   - Cart badge update
 *   - Toast notifications
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /* ═══════════════════════════════════════════════════════════════════
       BASE HELPERS
    ═══════════════════════════════════════════════════════════════════ */

    // Base URL for AJAX requests — set in footer.php
    const BASE = window.SITE_URL || '/';

    /**
     * Format a number as RWF currency.
     * Example: 15000.50 → "15,000.50 RWF"
     */
    function formatRWF(amount) {
        return Number(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' RWF';
    }

    /**
     * Show a floating toast notification at the top-right.
     * Auto-dismisses after 3 seconds.
     *
     * @param {string} message - The text to display.
     * @param {string} type    - 'success', 'error', or 'info'.
     */
    function showCartAlert(message, type) {
        // Remove any existing alert first
        const existing = document.querySelector('.cart-alert');
        if (existing) existing.remove();

        const alert = document.createElement('div');
        alert.className = 'cart-alert cart-alert-' + type;

        // Pick an icon based on alert type
        const icon = type === 'success' ? 'check-circle'
                   : type === 'error'   ? 'exclamation-circle'
                   :                      'info-circle';

        alert.innerHTML = '<i class="bi bi-' + icon + ' me-2"></i> ' + message;
        document.body.appendChild(alert);

        // Remove after 3 seconds with a fade-out
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'opacity 0.3s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);
    }

    /**
     * Update the cart badge count in the navbar.
     *
     * @param {number|string} count - The new count to display.
     */
    function updateCartBadge(count) {
        const badge = document.getElementById('nav-cart-count');
        if (badge) {
            badge.textContent = count;
        }
    }

    /**
     * Send an AJAX POST request and return the parsed JSON response.
     *
     * @param {string} url  - The endpoint to call.
     * @param {object} data - Key-value pairs to send.
     * @returns {Promise<object>} The parsed JSON response.
     */
    async function ajaxPost(url, data) {
        const formData = new URLSearchParams();
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }

        const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        if (window.CSRF_TOKEN) {
            headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData.toString(),
        });

        return response.json();
    }


    /* ═══════════════════════════════════════════════════════════════════
       QUANTITY SELECTOR (Product Details Page)
    ═══════════════════════════════════════════════════════════════════ */

    const qtyMinus = document.querySelector('.qty-minus');
    const qtyPlus  = document.querySelector('.qty-plus');
    const qtyInput = document.getElementById('qtyInput');

    if (qtyMinus && qtyPlus && qtyInput) {
        qtyMinus.addEventListener('click', () => {
            const val = parseInt(qtyInput.value, 10);
            if (val > 1) {
                qtyInput.value = val - 1;
            }
        });

        qtyPlus.addEventListener('click', () => {
            const val = parseInt(qtyInput.value, 10);
            const max = parseInt(qtyInput.max, 10);
            if (val < max) {
                qtyInput.value = val + 1;
            }
        });
    }


    /* ═══════════════════════════════════════════════════════════════════
       ADD TO CART
       Triggered by clicking any button with the .add-to-cart-btn class.
    ═══════════════════════════════════════════════════════════════════ */

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.add-to-cart-btn');
        if (!btn) return;

        // Disable temporarily to prevent double-clicks
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const productId   = btn.dataset.productId;
        const productName = btn.dataset.productName || 'Product';

        // If there's a visible quantity input (product-details page), use its value
        const qtyEl = document.getElementById('qtyInput');
        const quantity = qtyEl ? parseInt(qtyEl.value, 10) : 1;

        try {
            const result = await ajaxPost(BASE + 'ajax/cart/add.php', {
                product_id: productId,
                quantity: quantity,
            });

            if (result.success) {
                showCartAlert(productName + ' added to cart successfully.', 'success');
                updateCartBadge(result.cart_count);
            } else {
                showCartAlert(result.message || 'Failed to add to cart.', 'error');
            }
        } catch (err) {
            showCartAlert('Network error. Please try again.', 'error');
        }

        // Restore the button
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });


    /* ═══════════════════════════════════════════════════════════════════
       CART PAGE — Quantity Update
       Handles the + and - buttons in the cart table.
    ═══════════════════════════════════════════════════════════════════ */

    /**
     * Update the subtotal for a single cart row and recalculate totals.
     *
     * @param {HTMLElement} row    - The cart item row.
     * @param {number}      newQty - The new quantity for this row.
     * @param {number}      [serverSubtotal] - Server-confirmed subtotal.
     */
    function updateCartRow(row, newQty, serverSubtotal) {
        const price    = parseFloat(row.dataset.price);
        const subtotal = serverSubtotal !== undefined ? serverSubtotal : price * newQty;

        // Update subtotal cell
        const subEl = row.querySelector('.cart-subtotal');
        if (subEl) subEl.textContent = formatRWF(subtotal);

        // Recalculate totals and statistics
        recalcCartTotal();
        updateCartStats();
    }

    /**
     * Recalculate the summary (subtotal, grand total) and statistics
     * (items count, total quantity) from every visible row.
     */
    function recalcCartTotal() {
        const rows = document.querySelectorAll('.cart-item-row');
        let subtotal = 0;
        let qtyCount = 0;
        let itemCount = 0;

        rows.forEach(row => {
            itemCount++;
            const input = row.querySelector('.cart-qty-input');
            if (input) {
                const qty = parseInt(input.value, 10);
                qtyCount += qty;
            }

            const subEl = row.querySelector('.cart-subtotal');
            if (subEl) {
                const raw = subEl.textContent.replace(/[^0-9.]/g, '');
                subtotal += parseFloat(raw) || 0;
            }
        });

        // Update summary
        const subEl = document.getElementById('summary-subtotal');
        if (subEl) subEl.textContent = formatRWF(subtotal);

        const shipping = parseFloat(window.shipping) || 0;
        const tax      = parseFloat(window.tax) || 0;
        const grand    = subtotal + shipping + tax;

        const grandEl = document.getElementById('summary-grand-total');
        if (grandEl) grandEl.textContent = formatRWF(grand);

        // Update statistics
        const statItemsEl = document.getElementById('cart-stat-items');
        if (statItemsEl) statItemsEl.textContent = itemCount;

        const statQtyEl = document.getElementById('cart-stat-qty');
        if (statQtyEl) statQtyEl.textContent = qtyCount;
    }

    /**
     * Update just the item & quantity statistics.
     */
    function updateCartStats() {
        const rows = document.querySelectorAll('.cart-item-row');
        let qtyCount = 0;
        let itemCount = 0;

        rows.forEach(row => {
            itemCount++;
            const input = row.querySelector('.cart-qty-input');
            if (input) qtyCount += parseInt(input.value, 10);
        });

        const statItemsEl = document.getElementById('cart-stat-items');
        if (statItemsEl) statItemsEl.textContent = itemCount;

        const statQtyEl = document.getElementById('cart-stat-qty');
        if (statQtyEl) statQtyEl.textContent = qtyCount;
    }

    /**
     * Handle click events on the +/- quantity buttons in the cart table.
     */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cart-qty-minus, .cart-qty-plus');
        if (!btn) return;

        const row = btn.closest('.cart-item-row');
        if (!row) return;

        const cartId    = btn.dataset.cartId;
        const input     = row.querySelector('.cart-qty-input');
        const stock     = parseInt(row.dataset.stock, 10);
        let currentQty  = parseInt(input.value, 10);

        // Compute new quantity
        let newQty;
        if (btn.classList.contains('cart-qty-minus')) {
            newQty = Math.max(1, currentQty - 1);
        } else {
            newQty = Math.min(stock, currentQty + 1);
        }

        // No change? Show a hint and exit
        if (newQty === currentQty) {
            if (currentQty >= stock) {
                showCartAlert('Maximum stock quantity reached.', 'info');
            }
            return;
        }

        // Optimistic UI update — instantly reflect the change
        input.value = newQty;
        updateCartRow(row, newQty);

        try {
            const result = await ajaxPost(BASE + 'ajax/cart/update.php', {
                cart_id: cartId,
                quantity: newQty,
            });

            if (!result.success) {
                // Server rejected the change — revert
                input.value = currentQty;
                updateCartRow(row, currentQty);
                showCartAlert(result.message || 'Failed to update quantity.', 'error');
                recalcCartTotal();
            } else {
                // Use the server-confirmed values
                const confirmedQty = result.quantity || newQty;
                input.value = confirmedQty;
                updateCartRow(row, confirmedQty, result.subtotal);
                updateCartBadge(result.cart_count);
            }
        } catch (err) {
            // Network error — revert the optimistic update
            input.value = currentQty;
            updateCartRow(row, currentQty);
            showCartAlert('Network error. Please try again.', 'error');
        }
    });


    /* ═══════════════════════════════════════════════════════════════════
       CART PAGE — Remove Single Item
       Uses a Bootstrap modal for confirmation.
    ═══════════════════════════════════════════════════════════════════ */

    let pendingRemoveId = null;

    const removeModal = document.getElementById('removeModal');
    if (removeModal) {
        removeModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (btn) pendingRemoveId = btn.dataset.cartId;
        });
    }

    const confirmRemoveBtn = document.getElementById('confirmRemoveBtn');
    if (confirmRemoveBtn) {
        confirmRemoveBtn.addEventListener('click', async () => {
            if (!pendingRemoveId) return;

            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('removeModal'));
            if (modal) modal.hide();

            const row = document.querySelector(
                '.cart-item-row[data-cart-id="' + pendingRemoveId + '"]'
            );

            try {
                const result = await ajaxPost(BASE + 'ajax/cart/remove.php', {
                    cart_id: pendingRemoveId,
                });

                if (result.success) {
                    if (row) row.remove();
                    updateCartBadge(result.cart_count);

                    // Recalculate summary and statistics after removal
                    recalcCartTotal();

                    showCartAlert('Item removed from cart.', 'success');

                    // If the cart is now empty, reload to show the empty state
                    const remaining = document.querySelectorAll('.cart-item-row');
                    if (remaining.length === 0) location.reload();
                } else {
                    showCartAlert(result.message || 'Failed to remove item.', 'error');
                }
            } catch (err) {
                showCartAlert('Network error. Please try again.', 'error');
            }

            pendingRemoveId = null;
        });
    }


    /* ═══════════════════════════════════════════════════════════════════
       CART PAGE — Clear Entire Cart
       Uses a Bootstrap modal for confirmation.
    ═══════════════════════════════════════════════════════════════════ */

    const confirmClearBtn = document.getElementById('confirmClearBtn');
    if (confirmClearBtn) {
        confirmClearBtn.addEventListener('click', async () => {
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('clearModal'));
            if (modal) modal.hide();

            try {
                const result = await ajaxPost(BASE + 'ajax/cart/clear.php', {});

                if (result.success) {
                    updateCartBadge(0);
                    showCartAlert('Cart cleared successfully.', 'success');
                    location.reload();
                } else {
                    showCartAlert(result.message || 'Failed to clear cart.', 'error');
                }
            } catch (err) {
                showCartAlert('Network error. Please try again.', 'error');
            }
        });
    }


    /**
     * Update the wishlist badge count in the navbar.
     */
    function updateWishlistBadge(count) {
        const badge = document.getElementById('nav-wishlist-count');
        if (badge) {
            badge.textContent = count;
        }
    }

    /**
     * Update the compare badge count in the navbar.
     */
    function updateCompareBadge(count) {
        const badge = document.getElementById('nav-compare-count');
        if (badge) {
            badge.textContent = count;
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       COMPARE — Toggle (Add / Remove)
    ═══════════════════════════════════════════════════════════════════ */

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.compare-toggle');
        if (!btn) return;

        e.preventDefault();

        btn.disabled = true;
        const productId   = btn.dataset.productId;
        const inCompare   = btn.dataset.inCompare === '1';
        const url = inCompare
            ? BASE + 'ajax/compare/remove.php'
            : BASE + 'ajax/compare/add.php';

        try {
            const result = await ajaxPost(url, { product_id: productId });

            if (result.success) {
                btn.dataset.inCompare = result.in_compare ? '1' : '0';
                btn.title = result.in_compare ? 'Remove from compare' : 'Add to compare';
                btn.classList.toggle('compare-active', result.in_compare);

                updateCompareBadge(result.compare_count);
                showCartAlert(result.message, 'success');
            } else {
                showCartAlert(result.message, 'error');
            }
        } catch (err) {
            showCartAlert('Network error. Please try again.', 'error');
        }

        btn.disabled = false;
    });

    /* ═══════════════════════════════════════════════════════════════════
       WISHLIST — Toggle (Add / Remove)
       Works on both shop.php and product-details.php via .wishlist-toggle
    ═══════════════════════════════════════════════════════════════════ */

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.wishlist-toggle');
        if (!btn) return;

        e.preventDefault();

        btn.disabled = true;
        const productId    = btn.dataset.productId;
        const inWishlist  = btn.dataset.inWishlist === '1';
        const url = inWishlist
            ? BASE + 'ajax/wishlist/remove.php'
            : BASE + 'ajax/wishlist/add.php';

        try {
            const result = await ajaxPost(url, { product_id: productId });

            if (result.success) {
                btn.dataset.inWishlist = result.in_wishlist ? '1' : '0';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = result.in_wishlist ? 'bi bi-heart-fill' : 'bi bi-heart';
                }
                btn.title = result.in_wishlist ? 'Remove from wishlist' : 'Add to wishlist';
                btn.classList.toggle('wishlist-active', result.in_wishlist);

                updateWishlistBadge(result.wishlist_count);
                showCartAlert(result.message, 'success');
            } else {
                showCartAlert(result.message, 'error');
            }
        } catch (err) {
            showCartAlert('Network error. Please try again.', 'error');
        }

        btn.disabled = false;
    });

    /* ═══════════════════════════════════════════════════════════════════
       WISHLIST PAGE — Move to Cart + Remove
    ═══════════════════════════════════════════════════════════════════ */

    // Move to Cart from wishlist page
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.wishlist-move-to-cart');
        if (!btn) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const productId   = btn.dataset.productId;
        const productName = btn.dataset.productName || 'Product';

        try {
            // 1. Add to cart
            const cartResult = await ajaxPost(BASE + 'ajax/cart/add.php', {
                product_id: productId,
                quantity: 1,
            });

            if (cartResult.success) {
                // 2. Remove from wishlist
                const wlResult = await ajaxPost(BASE + 'ajax/wishlist/remove.php', {
                    product_id: productId,
                });

                if (wlResult.success) {
                    // 3. Update badges
                    updateCartBadge(cartResult.cart_count);
                    updateWishlistBadge(wlResult.wishlist_count);

                    // 4. Remove card from DOM
                    const card = btn.closest('.wishlist-item');
                    if (card) {
                        card.style.transition = 'opacity 0.3s';
                        card.style.opacity = '0';
                        setTimeout(() => {
                            if (card.parentNode) card.remove();
                            // Reload if wishlist is now empty
                            const remaining = document.querySelectorAll('.wishlist-item');
                            if (remaining.length === 0) location.reload();
                        }, 300);
                    }

                    showCartAlert(productName + ' moved to cart.', 'success');
                } else {
                    showCartAlert(wlResult.message || 'Failed to update wishlist.', 'error');
                }
            } else {
                showCartAlert(cartResult.message || 'Failed to add to cart.', 'error');
            }
        } catch (err) {
            showCartAlert('Network error. Please try again.', 'error');
        }

        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });

    // Wishlist remove confirmation (modal)
    let pendingWishlistRemoveId = null;

    const wlRemoveModal = document.getElementById('wishlistRemoveModal');
    if (wlRemoveModal) {
        wlRemoveModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (btn) pendingWishlistRemoveId = btn.dataset.productId;
        });
    }

    const confirmWlRemoveBtn = document.getElementById('confirmWishlistRemoveBtn');
    if (confirmWlRemoveBtn) {
        confirmWlRemoveBtn.addEventListener('click', async () => {
            if (!pendingWishlistRemoveId) return;

            const modal = bootstrap.Modal.getInstance(document.getElementById('wishlistRemoveModal'));
            if (modal) modal.hide();

            try {
                const result = await ajaxPost(BASE + 'ajax/wishlist/remove.php', {
                    product_id: pendingWishlistRemoveId,
                });

                if (result.success) {
                    const card = document.querySelector(
                        '.wishlist-item[data-product-id="' + pendingWishlistRemoveId + '"]'
                    );
                    if (card) {
                        card.style.transition = 'opacity 0.3s';
                        card.style.opacity = '0';
                        setTimeout(() => {
                            if (card.parentNode) card.remove();
                            const remaining = document.querySelectorAll('.wishlist-item');
                            if (remaining.length === 0) location.reload();
                        }, 300);
                    }
                    updateWishlistBadge(result.wishlist_count);
                    showCartAlert('Product removed from wishlist.', 'success');
                } else {
                    showCartAlert(result.message || 'Failed to remove item.', 'error');
                }
            } catch (err) {
                showCartAlert('Network error. Please try again.', 'error');
            }

            pendingWishlistRemoveId = null;
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       COMPARE PAGE — Remove from compare table (with confirmation modal)
    ═══════════════════════════════════════════════════════════════════ */

    let pendingCompareRemoveId = null;

    const cmpRemoveModal = document.getElementById('compareRemoveModal');
    if (cmpRemoveModal) {
        cmpRemoveModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (btn) pendingCompareRemoveId = btn.dataset.productId;
        });
    }

    const confirmCmpRemoveBtn = document.getElementById('confirmCompareRemoveBtn');
    if (confirmCmpRemoveBtn) {
        confirmCmpRemoveBtn.addEventListener('click', async () => {
            if (!pendingCompareRemoveId) return;

            const modal = bootstrap.Modal.getInstance(document.getElementById('compareRemoveModal'));
            if (modal) modal.hide();

            try {
                const result = await ajaxPost(BASE + 'ajax/compare/remove.php', {
                    product_id: pendingCompareRemoveId,
                });

                if (result.success) {
                    updateCompareBadge(result.compare_count);
                    showCartAlert('Product removed from compare.', 'success');
                    // Reload the page to refresh the comparison table
                    setTimeout(() => location.reload(), 500);
                } else {
                    showCartAlert(result.message || 'Failed to remove item.', 'error');
                }
            } catch (err) {
                showCartAlert('Network error. Please try again.', 'error');
            }

            pendingCompareRemoveId = null;
        });
    }

    // Direct remove buttons on compare page (non-modal fallback)
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.compare-remove');
        if (!btn) return;
        e.preventDefault();

        btn.disabled = true;
        const productId = btn.dataset.productId;

        try {
            const result = await ajaxPost(BASE + 'ajax/compare/remove.php', {
                product_id: productId,
            });

            if (result.success) {
                updateCompareBadge(result.compare_count);
                showCartAlert('Product removed from compare.', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showCartAlert(result.message || 'Failed to remove item.', 'error');
            }
        } catch (err) {
            showCartAlert('Network error. Please try again.', 'error');
        }

        btn.disabled = false;
    });

    /* ═══════════════════════════════════════════════════════════════════
       HEADER — Sticky, Mobile Menu, Search Overlay
    ═══════════════════════════════════════════════════════════════════ */

    const siteHeader = document.getElementById('siteHeader');

    // Sticky header shadow on scroll
    if (siteHeader) {
        window.addEventListener('scroll', () => {
            siteHeader.classList.toggle('is-sticky', window.scrollY > 10);
        }, { passive: true });
    }

    // Mobile hamburger menu
    const menuToggle = document.getElementById('mobileMenuToggle');
    const slideMenu  = document.getElementById('mobileSlideMenu');
    const slideOverlay = document.getElementById('mobileSlideOverlay');
    const menuClose  = document.getElementById('mobileMenuClose');

    function openMobileMenu() {
        if (slideMenu) slideMenu.classList.add('open');
        if (slideOverlay) slideOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileMenu() {
        if (slideMenu) slideMenu.classList.remove('open');
        if (slideOverlay) slideOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (menuToggle) menuToggle.addEventListener('click', openMobileMenu);
    if (menuClose) menuClose.addEventListener('click', closeMobileMenu);
    if (slideOverlay) slideOverlay.addEventListener('click', closeMobileMenu);

    // Close mobile menu on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && slideMenu && slideMenu.classList.contains('open')) {
            closeMobileMenu();
        }
    });

    // Mobile search overlay
    const searchToggle = document.getElementById('mobileSearchToggle');
    const searchOverlay = document.getElementById('mobileSearchOverlay');
    const searchClose = document.getElementById('mobileSearchClose');

    function openSearchOverlay() {
        if (searchOverlay) {
            searchOverlay.classList.add('open');
            const input = searchOverlay.querySelector('input');
            if (input) setTimeout(() => input.focus(), 100);
        }
    }

    function closeSearchOverlay() {
        if (searchOverlay) searchOverlay.classList.remove('open');
    }

    if (searchToggle) searchToggle.addEventListener('click', openSearchOverlay);
    if (searchClose) searchClose.addEventListener('click', closeSearchOverlay);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && searchOverlay && searchOverlay.classList.contains('open')) {
            closeSearchOverlay();
        }
    });

    // Search submit from mobile overlay on enter
    if (searchOverlay) {
        searchOverlay.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const form = searchOverlay.querySelector('form');
                if (form) form.submit();
            }
        });
    }

    // Desktop search: show search button on input focus
    const searchInputs = document.querySelectorAll('.header-search .search-wrapper input');
    searchInputs.forEach(input => {
        const btn = input.closest('.search-wrapper')?.querySelector('.search-btn');
        if (btn) {
            input.addEventListener('focus', () => btn.classList.add('visible'));
            input.addEventListener('blur', () => {
                if (!input.value.trim()) btn.classList.remove('visible');
            });
        }
    });

    // Keep existing badge update functions — extend to mobile badges
    const origUpdateCartBadge = window.updateCartBadge || function() {};
    const origUpdateWishlistBadge = window.updateWishlistBadge || function() {};

    function syncMobileBadges() {
        const desktopCart = document.getElementById('nav-cart-count');
        const desktopWish = document.getElementById('nav-wishlist-count');
        const desktopCmp  = document.getElementById('nav-compare-count');
        const mobileCart = document.getElementById('mobile-cart-count');
        const mobileWish = document.getElementById('mobile-wishlist-count');
        const mobileCmp  = document.getElementById('mobile-compare-count');
        const botCart = document.getElementById('bot-cart-count');
        const botWish = document.getElementById('bot-wishlist-count');
        const botCmp  = document.getElementById('bot-compare-count');

        if (desktopCart && mobileCart) mobileCart.textContent = desktopCart.textContent;
        if (desktopCart && botCart) botCart.textContent = desktopCart.textContent;
        if (desktopWish && mobileWish) mobileWish.textContent = desktopWish.textContent;
        if (desktopWish && botWish) botWish.textContent = desktopWish.textContent;
        if (desktopCmp && mobileCmp) mobileCmp.textContent = desktopCmp.textContent;
        if (desktopCmp && botCmp) botCmp.textContent = desktopCmp.textContent;
    }

    // Override updateCartBadge to sync mobile
    window.updateCartBadge = function(count) {
        const badge = document.getElementById('nav-cart-count');
        if (badge) badge.textContent = count;
        ['mobile-cart-count', 'bot-cart-count'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = count;
        });
    };

    window.updateWishlistBadge = function(count) {
        const badge = document.getElementById('nav-wishlist-count');
        if (badge) badge.textContent = count;
        ['mobile-wishlist-count', 'bot-wishlist-count'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = count;
        });
    };

    window.updateCompareBadge = function(count) {
        const badge = document.getElementById('nav-compare-count');
        if (badge) badge.textContent = count;
        ['mobile-compare-count', 'bot-compare-count'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = count;
        });
    };

    // Initial sync on load
    setTimeout(syncMobileBadges, 50);

    /* ═══════════════════════════════════════════════════════════════════
       NEWSLETTER FORM
       ═══════════════════════════════════════════════════════════════════ */
    const nlForm = document.getElementById('newsletterForm');
    if (nlForm) {
        nlForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const email = nlForm.querySelector('input[type="email"]');
            if (!email || !email.value.trim()) return;

            const btn = nlForm.querySelector('button[type="submit"]');
            const origHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subscribing...';

            try {
                const fd = new FormData();
                fd.append('email', email.value.trim());
                if (window.CSRF_TOKEN) fd.append('csrf_token', window.CSRF_TOKEN);

                const resp = await fetch(BASE + 'ajax/newsletter/subscribe.php', { method: 'POST', body: fd });
                const data = await resp.json();

                const formWrap = nlForm.closest('.col-lg-7');
                nlForm.classList.add('d-none');
                const disclaimer = formWrap ? formWrap.querySelector('.newsletter-disclaimer') : null;
                if (disclaimer) disclaimer.classList.add('d-none');
                const success = document.getElementById('newsletterSuccess');
                if (success) {
                    const msg = success.querySelector('.newsletter-success-msg');
                    if (msg && data.message) msg.textContent = data.message;
                    success.classList.remove('d-none');
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = origHTML;
            }
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       CUSTOMER REVIEWS CAROUSEL
       ═══════════════════════════════════════════════════════════════════ */
    const reviewsTrack = document.getElementById('reviewsTrack');
    const reviewsPrev = document.getElementById('reviewsPrev');
    const reviewsNext = document.getElementById('reviewsNext');
    const reviewsDots = document.getElementById('reviewsDots');

    if (reviewsTrack && reviewsPrev && reviewsNext) {
        const items = reviewsTrack.querySelectorAll('.review-card-item');
        const totalItems = items.length;
        let visibleCount = 3;
        let currentIndex = 0;
        let itemWidth = 0;

        function calcVisibleCount() {
            if (window.innerWidth < 768) visibleCount = 1;
            else if (window.innerWidth < 992) visibleCount = 2;
            else visibleCount = 3;
        }

        function updateDots() {
            if (!reviewsDots) return;
            const totalDots = Math.max(1, totalItems - visibleCount + 1);
            reviewsDots.innerHTML = '';
            for (let i = 0; i < totalDots; i++) {
                const dot = document.createElement('button');
                dot.setAttribute('aria-label', 'Go to review ' + (i + 1));
                if (i === currentIndex) dot.classList.add('active');
                dot.addEventListener('click', () => goTo(i));
                reviewsDots.appendChild(dot);
            }
        }

        function goTo(index) {
            calcVisibleCount();
            const maxIndex = Math.max(0, totalItems - visibleCount);
            currentIndex = Math.min(Math.max(0, index), maxIndex);
            if (totalItems > 0 && items[0]) {
                const first = items[0];
                const gap = parseFloat(getComputedStyle(reviewsTrack).gap) || 0;
                itemWidth = first.offsetWidth + gap;
                reviewsTrack.style.transform = 'translateX(-' + (currentIndex * itemWidth) + 'px)';
            }
            document.querySelectorAll('.reviews-dots button').forEach((d, i) => {
                d.classList.toggle('active', i === currentIndex);
            });
        }

        function goNext() { goTo(currentIndex + 1); }
        function goPrev() { goTo(currentIndex - 1); }

        reviewsNext.addEventListener('click', goNext);
        reviewsPrev.addEventListener('click', goPrev);

        let autoPlay = setInterval(goNext, 5000);
        reviewsTrack.addEventListener('mouseenter', () => clearInterval(autoPlay));
        reviewsTrack.addEventListener('mouseleave', () => { autoPlay = setInterval(goNext, 5000); });

        window.addEventListener('resize', () => { goTo(currentIndex); });
        setTimeout(() => { calcVisibleCount(); updateDots(); goTo(0); }, 100);
    }

    /* ═══════════════════════════════════════════════════════════════════
       PASSWORD TOGGLE (Login / Register pages)
    ═══════════════════════════════════════════════════════════════════ */

    window.togglePassword = function (fieldId, btn) {
        const field = document.getElementById(fieldId);
        const icon  = btn.querySelector('i');
        if (!field || !icon) return;

        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    };

    console.log('Champion Liquor Store Ltd loaded.');
});
