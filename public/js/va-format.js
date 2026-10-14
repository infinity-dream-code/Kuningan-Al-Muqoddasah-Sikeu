/**
 * Format No. VA: prefix + NIS (total 16 digit).
 * Open 777723 (VA 93). Close hanya jika APP_VA_CLOSE_ENABLED=true.
 */
(function (window) {
    const DEFAULT_OPEN = '777723';
    const DEFAULT_CLOSE = '777723';
    const TOTAL_LENGTH = 16;

    function digitsOnly(value) {
        return String(value ?? '').replace(/\D/g, '');
    }

    function closeEnabled() {
        return window.APP_VA_CLOSE_ENABLED === true || window.APP_VA_CLOSE_ENABLED === 1 || window.APP_VA_CLOSE_ENABLED === '1';
    }

    window.formatNoVA = function (nis, prefix) {
        const vaPrefix = digitsOnly(prefix ?? window.APP_VA_PREFIX ?? window.APP_VA_OPEN ?? DEFAULT_OPEN) || DEFAULT_OPEN;
        const digits = digitsOnly(nis);
        if (!digits) {
            return '';
        }
        const padLen = Math.max(1, TOTAL_LENGTH - vaPrefix.length);
        return vaPrefix + digits.padStart(padLen, '0');
    };

    window.showVAOpen = function (nis) {
        return window.formatNoVA(nis, window.APP_VA_OPEN || DEFAULT_OPEN);
    };

    window.showVAClose = function (nis) {
        if (!closeEnabled()) {
            return '';
        }
        return window.formatNoVA(nis, window.APP_VA_CLOSE || DEFAULT_CLOSE);
    };

    window.showVABoth = function (nis) {
        const open = window.showVAOpen(nis);
        if (!closeEnabled()) {
            return open;
        }
        const close = window.showVAClose(nis);
        if (open && close && open !== close) {
            return open + ' / ' + close;
        }
        return open || close || '';
    };

    window.novaFromBills = function (nis, tagihans) {
        const unique = [];
        (tagihans || []).forEach(function (item) {
            const v = item && item.NOVA ? String(item.NOVA).trim() : '';
            if (v && unique.indexOf(v) === -1) {
                unique.push(v);
            }
        });
        if (unique.length) {
            return unique.join(' / ');
        }
        return nis ? window.showVABoth(nis) : '';
    };
})(window);
