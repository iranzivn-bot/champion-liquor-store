/**
 * Analytics Dashboard JavaScript
 *
 * Champion Liquor Store Ltd
 * - Chart.js auto-refresh via AJAX
 * - Period filter handling
 * - Window unload cleanup
 */

let analyticsRefreshTimer = null;

document.addEventListener('DOMContentLoaded', function () {

    // ─── Period Filter Auto-Submit ───────────────────────────
    const periodSelect = document.getElementById('periodSelect');
    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            this.closest('form').submit();
        });
    }

    // ─── Auto-Refresh (every 60 seconds) ─────────────────────
    const refreshWrap = document.getElementById('analyticsRefreshWrap');
    if (refreshWrap) {
        startAnalyticsRefresh();
    }

    // ─── Tooltip init ────────────────────────────────────────
    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function (el) { return new bootstrap.Tooltip(el); });
});

function startAnalyticsRefresh() {
    stopAnalyticsRefresh();
    analyticsRefreshTimer = setInterval(function () {
        refreshAnalyticsContent();
    }, 60000);
}

function stopAnalyticsRefresh() {
    if (analyticsRefreshTimer) {
        clearInterval(analyticsRefreshTimer);
        analyticsRefreshTimer = null;
    }
}

function refreshAnalyticsContent() {
    const wrap = document.getElementById('analyticsRefreshWrap');
    if (!wrap) return;

    const url = window.location.href.split('#')[0] + '&ajax=1&_=' + Date.now();

    fetch(url)
        .then(function (r) { return r.text(); })
        .then(function (html) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById('analyticsRefreshWrap');
            if (newContent) {
                wrap.innerHTML = newContent.innerHTML;
                reInitCharts();
            }
        })
        .catch(function () {});
}

function reInitCharts() {
    if (typeof Chart !== 'undefined') {
        Object.keys(Chart.instances).forEach(function (k) {
            try { Chart.instances[k].destroy(); } catch (e) {}
        });
    }
    if (typeof initAnalyticsCharts === 'function') {
        initAnalyticsCharts();
    }
}

window.addEventListener('beforeunload', function () {
    stopAnalyticsRefresh();
});
