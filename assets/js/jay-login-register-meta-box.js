jQuery(document).ready(function($) {
    'use strict';
    // مدیریت رویداد کلیک آکاردیون
    $('.accordion-title').on('click', function() {
        $(this).next('.accordion-content').slideToggle(200); // 200ms animation
        $(this).toggleClass('active');
    });
});
