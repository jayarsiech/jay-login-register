(function($) {
    'use strict';

    // تابع کمکی برای استخراج پارامتر از URL
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    $(function() {
        if (typeof window.Eitaa !== 'undefined' && window.Eitaa.WebApp && typeof window.Eitaa.WebApp.initData === 'string' && window.Eitaa.WebApp.initData.length > 0 && jay_eitaa_obj.is_user_logged_in === 'false') {
            
            const webApp = window.Eitaa.WebApp;
            webApp.ready();

            const overlay = $('<div id="eitaa-auth-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(25,25,25,0.85);z-index:99999;display:flex;align-items:center;justify-content:center;color:white;font-family:sans-serif;font-size:16px;"></div>');
            const styles = `
                <style>
                    #eitaa-auth-overlay .jay-spinner { display:inline-block; width:2em; height:2em; border:3px solid transparent; border-top-color:currentColor; border-radius:50%; animation:spin 1s linear infinite; margin-bottom:10px; } 
                    @keyframes spin { to{transform:rotate(360deg);} }
                    #eitaa-retry-button { background: #50A8EB; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-top: 15px; font-size: 16px; font-family: inherit; }
                    #eitaa-retry-button:hover { background: #4097d9; }
                </style>`;
            $('body').append(overlay).append(styles);

            // تابع اصلی برای شروع فرآیند احراز هویت
            function initiateAuthentication() {
                // نمایش پیام "در حال اتصال..."
                overlay.html('<div style="text-align:center;"><span class="jay-spinner"></span><br>در حال اتصال به ایتا...</div>');

                webApp.requestContact(function(success, contactData) {
                    if (success && contactData) {
                        
                        let finalRedirectUrl = getUrlParameter('redirect_to');
                        if (!finalRedirectUrl) {
                            finalRedirectUrl = window.location.href;
                        }

                        $.ajax({
                            type: 'POST',
                            url: jay_eitaa_obj.ajax_url,
                            data: {
                                action: 'jay_login_register_handle_eitaa_login',
                                _ajax_nonce: jay_eitaa_obj.nonce,
                                initData: webApp.initData,
                                contactData: contactData,
                                current_url: finalRedirectUrl
                            },
                            success: function(response) {
                                if (response.success && response.data.redirect_url) {
                                    overlay.find('div').html('احراز هویت موفق!<br>در حال بارگذاری...');
                                    window.location.href = response.data.redirect_url;
                                } else {
                                    showRetry('خطا: ' + (response.data.message || 'پاسخ سرور نامعتبر است.'));
                                }
                            },
                            error: function() {
                                showRetry('خطا در ارتباط با سرور.');
                            }
                        });
                    } else {
                        // اگر کاربر لغو کرد، پیام به همراه دکمه تلاش مجدد نمایش داده می‌شود
                        showRetry('برای مشاهده این صفحه، باید شماره تماس خود را به اشتراک بگذارید.');
                    }
                });
            }

            // تابع برای نمایش پیام خطا به همراه دکمه تلاش مجدد
            function showRetry(message) {
                const retryHtml = `
                    <div style="text-align:center; padding: 20px;">
                        <p style="margin-bottom:15px;">${message}</p>
                        <button id="eitaa-retry-button">تلاش مجدد</button>
                    </div>`;
                overlay.html(retryHtml);
            }

            // اتصال رویداد کلیک به دکمه تلاش مجدد (با استفاده از event delegation)
            $('body').on('click', '#eitaa-retry-button', function() {
                initiateAuthentication(); // فرآیند را دوباره شروع کن
            });

            // شروع اولیه فرآیند
            initiateAuthentication();
        }
    });

})(jQuery);
