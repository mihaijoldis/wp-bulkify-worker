(function() {
    function initWPBHelper() {
        if (window.wpbHelper) {
            return;
        }
        if (typeof wpbulkifyAdminData === 'undefined') {
            console.error('[WPBulkifyWP] wpbulkifyAdminData is not defined!');
            return;
        }
        if (!window.wpbHelper) {
            window.wpbHelper = {
                version: wpbulkifyAdminData.version,
                ajaxUrl: wpbulkifyAdminData.ajaxUrl,
                restUrl: wpbulkifyAdminData.restUrl,
                restNonce: wpbulkifyAdminData.restNonce,
                nonce: wpbulkifyAdminData.nonce,
                initialized: true,
                debug_mode: localStorage.getItem('wpb_debug_mode') === 'true'
            };
        }
        document.body.setAttribute('data-wpb-helper', 'active');
        document.body.setAttribute('data-wpb-nonce', wpbulkifyAdminData.restNonce);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initWPBHelper();
        });
    } else {
        initWPBHelper();
    }
})();