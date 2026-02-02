jQuery(document).ready(function($) {
    // تغییر وضعیت دسترسی ویرایش کاربر
    $(document).on('change', '.jay-user-edit-toggle', function() {
        var $checkbox = $(this);
        var userId = $checkbox.data('user-id');
        var state = $checkbox.is(':checked') ? 1 : 0;
        
        // اصلاح نام متغیر: استفاده از jayPermissionObj به جای jay-login...
        if (typeof jayPermissionObj === 'undefined') {
            alert('خطای امنیتی: توکن نامعتبر است.');
            return;
        }

        var endpoint = jayPermissionObj.ajax_url;
        var nonce = jayPermissionObj.nonce;

        $checkbox.prop('disabled', true);

        $.post(endpoint, {
            action: 'jay_toggle_edit_access',
            user_id: userId,
            state: state,
            nonce: nonce
        }, function(response) {
            $checkbox.prop('disabled', false);
            
            if (!response.success) {
                alert(response.data.message || 'خطایی رخ داد');
                $checkbox.prop('checked', !state);
            }
        }).fail(function() {
            $checkbox.prop('disabled', false);
            alert('خطای ارتباط با سرور');
            $checkbox.prop('checked', !state);
        });
    });
});
