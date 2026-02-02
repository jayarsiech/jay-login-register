jQuery(function ($) {
    'use strict';

    $('.jay-content-lock-wrapper').on('click', '.jay-lock-button', function (e) {
        e.preventDefault();
        const button = $(this);
        const wrapper = button.closest('.jay-content-lock-wrapper');
        const mode = button.data('mode');
        const lockId = wrapper.attr('id');
        const targetRedirect = button.data('redirect-url'); // (جدید)

        if (mode === 'redirect') {
            let loginUrl = (typeof jay_lock_obj_inline !== 'undefined' && jay_lock_obj_inline.login_page_url)
                            ? jay_lock_obj_inline.login_page_url
                            : '/';

            // اگر ریدایرکت سفارشی داشت، به آنجا برود، وگرنه به صفحه جاری
            let finalDestination = targetRedirect ? targetRedirect : window.location.href.split('#')[0];
            
            if (!targetRedirect) {
                 finalDestination += '#' + lockId; // اسکرول فقط برای صفحه جاری
            }

            const redirectParam = encodeURIComponent(finalDestination);

            let finalLoginUrl = loginUrl;
            if (loginUrl.indexOf('?') > -1) {
                finalLoginUrl += '&redirect_to=' + redirectParam;
            } else {
                finalLoginUrl += '?redirect_to=' + redirectParam;
            }

            window.location.href = finalLoginUrl;

        } else if (mode === 'inline') {
            // Inline توسط فایل دیگر مدیریت می‌شود
        }
    });

    function highlightAndScroll() {
        if (window.location.hash && window.location.hash.startsWith('#jay-lock-')) {
            const lockId = window.location.hash;
            const targetElement = $(lockId);

            if (targetElement.length) {
                setTimeout(function() {
                    $('html, body').animate({
                        scrollTop: targetElement.offset().top - 100 
                    }, 800, function() { 
                        targetElement.addClass('jay-lock-highlight');
                        setTimeout(function() {
                            targetElement.removeClass('jay-lock-highlight');
                            if (history.pushState) {
                                 history.pushState(null, null, window.location.pathname + window.location.search);
                            }
                        }, 2000); 
                    });
                }, 300); 
            }
        }
    }
    highlightAndScroll();
});
