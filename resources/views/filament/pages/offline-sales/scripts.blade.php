    <script>
        let barcodeBuffer = '';
        let lastKeyTime = Date.now();

        window.addEventListener('focus-search', () => {
            setTimeout(() => {
                const searchEl = document.getElementById('lj-pos-search-input');
                if (searchEl) {
                    searchEl.focus();
                    searchEl.select();
                }
            }, 50);
        });

        document.addEventListener('keydown', function(e) {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            const isInput = activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select';
            const isSearchInput = document.activeElement && document.activeElement.id === 'lj-pos-search-input';

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
                @this.holdCurrentSale();
                return;
            }

            // Slash '/' or Ctrl+K: Focus Search Input
            if ((e.key === '/' && !isInput) || ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K'))) {
                e.preventDefault();
                const searchEl = document.getElementById('lj-pos-search-input');
                if (searchEl) {
                    searchEl.focus();
                    searchEl.select();
                }
                return;
            }

            // Escape: Close active modal
            if (e.key === 'Escape') {
                @this.closeModal();
                return;
            }

            // 'P' key when receipt or shift report modal is active triggers print
            const isReceiptOpen = document.querySelector('.lj-thermal-printable') !== null;
            if (isReceiptOpen && !isInput && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                const shiftEl = document.getElementById('lj-pos-shift-report-content');
                if (shiftEl && shiftEl.offsetParent !== null) {
                    window.printPosShiftReport();
                } else {
                    window.print();
                }
                return;
            }

            // Hardware USB Barcode Scanner Buffer:
            // Hardware scanners emit characters at rapid bursts (< 120ms) terminated by 'Enter'
            const currentTime = Date.now();
            const timeDiff = currentTime - lastKeyTime;
            lastKeyTime = currentTime;

            if (e.key === 'Enter') {
                if (isSearchInput) {
                    const searchInputEl = document.getElementById('lj-pos-search-input');
                    const code = searchInputEl ? searchInputEl.value.trim() : '';
                    if (code.length >= 2) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (searchInputEl) searchInputEl.value = '';
                        barcodeBuffer = '';
                        @this.handleBarcodeScan(code);
                        return;
                    }
                } else if (barcodeBuffer.length >= 2) {
                    e.preventDefault();
                    e.stopPropagation();
                    const code = barcodeBuffer.trim();
                    barcodeBuffer = '';
                    if (code.length > 0) {
                        @this.handleBarcodeScan(code);
                    }
                    return;
                }
                barcodeBuffer = '';
            } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (timeDiff > 120) {
                    // Gap too long for hardware scanner burst, reset buffer
                    barcodeBuffer = '';
                }
                if (!isInput || isSearchInput) {
                    barcodeBuffer += e.key;
                }
            }
        });

        // Initialize auto-print checkbox state from localStorage
        function syncAutoPrintCheckbox() {
            const cb = document.getElementById('lj-pos-autoprint-toggle');
            if (cb) {
                cb.checked = localStorage.getItem('lj_pos_autoprint') === '1';
            }
        }
        document.addEventListener('DOMContentLoaded', syncAutoPrintCheckbox);
        document.addEventListener('livewire:navigated', syncAutoPrintCheckbox);

        // Auto-print event listener dispatched from Livewire completeSale
        window.addEventListener('sale-completed-print', () => {
            syncAutoPrintCheckbox();
            if (localStorage.getItem('lj_pos_autoprint') === '1') {
                setTimeout(() => {
                    window.print();
                }, 350);
            }
        });

        // Dedicated 80mm Thermal Shift Report Printing Handler
        window.printPosShiftReport = function() {
            const reportEl = document.getElementById('lj-pos-shift-report-thermal-content')
                || document.getElementById('lj-pos-shift-report-content');
            if (!reportEl) {
                console.error('Thermal shift report content not found');
                window.print();
                return;
            }

            const htmlContent = reportEl.innerHTML;
            const printTitle = 'Laijau POS Shift Report (80mm) - ' + (new Date().toISOString().slice(0, 10));

            const fullHtml = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${printTitle}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10.5px;
            line-height: 1.35;
            color: #000000;
            background: #ffffff;
            width: 76mm;
            max-width: 76mm;
            padding: 4mm 2mm;
            margin: 0 auto;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.5px 2px; }
        @media print {
            body {
                width: 76mm !important;
                padding: 2mm 1mm !important;
            }
        }
    </style>
</head>
<body>
    <div style="width: 100%;">
        ${htmlContent}
    </div>
</body>
</html>`;

            let iframe = document.getElementById('lj-pos-print-frame-thermal');
            if (iframe) {
                iframe.remove();
            }
            iframe = document.createElement('iframe');
            iframe.id = 'lj-pos-print-frame-thermal';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '350px';
            iframe.style.height = '600px';
            iframe.style.opacity = '0';
            iframe.style.pointerEvents = 'none';
            iframe.style.border = '0';
            iframe.style.zIndex = '-999';
            document.body.appendChild(iframe);

            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(fullHtml);
            doc.close();

            setTimeout(() => {
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch(e) {
                    console.error('Thermal print error:', e);
                }
            }, 300);
        };

        // Dedicated Standard (A4 / PDF) Shift Report Printing Handler
        window.printPosShiftReportA4 = function() {
            const reportEl = document.getElementById('lj-pos-shift-report-content');
            if (!reportEl) {
                console.error('A4 shift report content not found');
                window.print();
                return;
            }

            const htmlContent = reportEl.innerHTML;
            const printTitle = 'Laijau POS Shift Report (A4) - ' + (new Date().toISOString().slice(0, 10));

            const fullHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${printTitle}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #111827;
            background: #ffffff;
            width: 100%;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .lj-a4-wrapper {
            width: 100%;
            max-width: 180mm;
            margin: 0 auto;
            padding: 0;
            background: #ffffff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 3px 4px;
        }
        .page-break-avoid {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        @media print {
            body {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .lj-a4-wrapper {
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="lj-a4-wrapper">
        ${htmlContent}
    </div>
</body>
</html>`;

            // Try opening dedicated print window first (optimal for PDF export & A4 sizing)
            let printWin = null;
            try {
                printWin = window.open('', '_blank', 'width=950,height=850,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes');
            } catch(e) {
                printWin = null;
            }

            if (printWin && !printWin.closed) {
                printWin.document.open();
                printWin.document.write(fullHtml);
                printWin.document.close();
                printWin.focus();

                const triggerWinPrint = () => {
                    try {
                        printWin.focus();
                        printWin.print();
                    } catch(err) {
                        console.error('Print window error:', err);
                    }
                };

                if (printWin.document.readyState === 'complete') {
                    setTimeout(triggerWinPrint, 250);
                } else {
                    printWin.onload = () => setTimeout(triggerWinPrint, 250);
                    setTimeout(triggerWinPrint, 600);
                }
            } else {
                // Fallback to high-resolution hidden iframe if popup is blocked
                let iframe = document.getElementById('lj-pos-print-frame-a4');
                if (iframe) {
                    iframe.remove();
                }
                iframe = document.createElement('iframe');
                iframe.id = 'lj-pos-print-frame-a4';
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '1024px';
                iframe.style.height = '1200px';
                iframe.style.opacity = '0';
                iframe.style.pointerEvents = 'none';
                iframe.style.border = '0';
                iframe.style.zIndex = '-999';
                document.body.appendChild(iframe);

                const frameDoc = iframe.contentWindow.document;
                frameDoc.open();
                frameDoc.write(fullHtml);
                frameDoc.close();

                setTimeout(() => {
                    try {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    } catch(e) {
                        console.error('A4 iframe print error:', e);
                    }
                }, 350);
            }
        };
    </script>

