/**
 * LAIJAU ADMIN V2.0 — RETAIL CONSOLE KEYBOARD SHORTCUTS
 * Optimized for showroom POS, warehouse stock receiving, and rapid product management.
 */
(function () {
    'use strict';

    document.addEventListener('keydown', function (e) {
        // Ignore if user is holding Ctrl+F5 or Shift+F5 (browser hard reload)
        if (e.key === 'F5' && (e.ctrlKey || e.shiftKey)) {
            return;
        }

        // F2: Point of Sale (POS) - Opens dedicated fullscreen POS in a new tab
        if (e.key === 'F2') {
            e.preventDefault();
            window.open('/intadmin/offline-sales/POS', '_blank');
            return;
        }

        // F3: Quick Stock Entry
        if (e.key === 'F3') {
            e.preventDefault();
            window.location.href = '/intadmin/quick-stock-entry';
            return;
        }

        // F4: Products Module
        if (e.key === 'F4') {
            e.preventDefault();
            window.location.href = '/intadmin/products';
            return;
        }

        // Alt + F5 or Alt + O: Orders Module
        if ((e.key === 'F5' && e.altKey) || (e.altKey && (e.key === 'o' || e.key === 'O'))) {
            e.preventDefault();
            window.location.href = '/intadmin/orders';
            return;
        }

        // Escape: Close modals
        if (e.key === 'Escape') {
            const closeButtons = document.querySelectorAll('[aria-label="Close"], [data-action="close"], .fi-modal-close-btn');
            if (closeButtons.length > 0) {
                closeButtons[closeButtons.length - 1].click();
            }
        }

        // Specialized POS & Offline Sales Shortcuts (When on /intadmin/offline-sales or /admin/offline-sales)
        if (window.location.pathname.includes('/offline-sales')) {
            // F8: Trigger Payment & Checkout
            if (e.key === 'F8') {
                e.preventDefault();
                const payBtn = document.getElementById('lj-pos-pay-btn');
                if (payBtn && !payBtn.disabled) {
                    payBtn.click();
                }
                return;
            }

            // F9: Hold Current Sale
            if (e.key === 'F9') {
                e.preventDefault();
                const holdBtn = document.querySelector('[title*="Hold Current Sale"], [title*="F9"]');
                if (holdBtn && !holdBtn.disabled) {
                    holdBtn.click();
                }
                return;
            }

            // Ctrl/Cmd + K or /: Focus Search & Barcode Input
            if (((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) || (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA')) {
                e.preventDefault();
                const searchInput = document.getElementById('lj-pos-search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
                return;
            }
        }
    });
})();
