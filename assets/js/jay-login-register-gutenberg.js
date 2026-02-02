(function (wp, $) {
    var registerBlockType = wp.blocks.registerBlockType;
    var InnerBlocks = wp.blockEditor.InnerBlocks;
    var BlockControls = wp.blockEditor.BlockControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var ToolbarButton = wp.components.ToolbarButton;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;

    // آیکون قفل برای بلوک
    var icon = el('svg', { width: 20, height: 20, viewBox: '0 0 20 20' },
        el('path', { d: 'M16 8h-3V5.5C13 3.01 11 1 8.5 1S4 3.01 4 5.5V8H1v11h15V8zm-5 0H6V5.5C6 4.12 7.12 3 8.5 3s2.5 1.12 2.5 2.5V8z' })
    );

    registerBlockType('jay-login-register/content-lock', {
        apiVersion: 2,
        title: 'قفل محتوا (Jay)',
        icon: icon,
        category: 'widgets',
        description: 'محتوای داخل این بلوک فقط برای کاربرانی که شرایط را داشته باشند نمایش داده می‌شود.',
        attributes: {
            shortcodeOpen: {
                type: 'string',
                // پیش‌فرض ساده
                default: '[jay_content_lock mode="redirect"]'
            }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            
            var blockProps = useBlockProps({
                className: 'jay-gutenberg-lock-wrapper'
            });

            var openSettingsModal = function () {
                if (typeof window.openJayLockModal === 'function') {
                    // استخراج پارامترهای فعلی شورت‌کد برای ارسال به مودال
                    var shortcodeStr = attributes.shortcodeOpen;
                    // حذف نام شورت‌کد و براکت‌ها برای پارس کردن
                    var content = shortcodeStr.replace('[jay_content_lock', '').replace(']', '');
                    
                    var data = {};
                    // این Regex تمام ویژگی‌ها (حتی فیلدهای سفارشی طولانی) را می‌گیرد
                    var regex = /(\w+)\s*=\s*"([^"]*)"/g;
                    var match;
                    while ((match = regex.exec(content)) !== null) { 
                        data[match[1]] = match[2]; 
                    }

                    // باز کردن مودال تنظیمات (که در فایل editor-script.js تعریف شده)
                    window.openJayLockModal(function (newShortcode) {
                        // وقتی دکمه "درج/بروزرسانی" در مودال زده شد، این تابع اجرا می‌شود
                        setAttributes({ shortcodeOpen: newShortcode });
                    }, data);

                } else {
                    alert('خطا: اسکریپت تنظیمات افزونه بارگذاری نشده است.');
                }
            };

            return el(Fragment, {},
                el(BlockControls, {},
                    el(ToolbarButton, {
                        icon: 'lock',
                        label: 'تنظیمات قفل و دسترسی',
                        onClick: openSettingsModal
                    })
                ),
                el('div', blockProps,
                    el('div', { className: 'jay-lock-header' },
                        el('span', { className: 'dashicons dashicons-lock', style: { marginLeft: '5px' } }),
                        ' محتوای محافظت شده (تنظیمات در نوار ابزار بالا)'
                    ),
                    el('div', { className: 'jay-lock-inner-content' },
                        el(InnerBlocks, {})
                    )
                )
            );
        },
        save: function (props) {
            var blockProps = useBlockProps.save({
                className: 'jay-locked-content-block'
            });

            return el('div', blockProps,
                // چاپ شورت‌کد باز (که شامل تمام تنظیمات جدید است)
                props.attributes.shortcodeOpen, 
                // محتوای داخلی بلوک
                el(InnerBlocks.Content),        
                // شورت‌کد بسته
                '[/jay_content_lock]'            
            );
        }
    });
})(window.wp, jQuery);
