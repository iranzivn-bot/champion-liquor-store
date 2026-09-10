/**
 * Admin JavaScript
 *
 * Champion Liquor Store Ltd - Admin Panel
 *
 * Features:
 *   - Sidebar toggle on mobile
 *   - Active menu highlighting
 *   - Click outside to close sidebar
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /* ─── DOM References ──────────────────────────────── */
    const sidebar     = document.getElementById('adminSidebar');
    const toggleBtn   = document.getElementById('sidebarToggle');
    const closeBtn    = document.getElementById('sidebarClose');
    const overlay     = document.getElementById('sidebarOverlay');

    /**
     * Open the sidebar and show overlay.
     */
    function openSidebar() {
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
    }

    /**
     * Close the sidebar and hide overlay.
     */
    function closeSidebar() {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }

    /* ─── Sidebar Toggle (Mobile) ─────────────────────── */
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    /* ─── Close sidebar on overlay click ──────────────── */
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    /* ─── Close sidebar on Escape key ─────────────────── */
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

});
