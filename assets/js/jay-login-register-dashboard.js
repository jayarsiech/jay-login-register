jQuery(window).on('load', function() {
    'use strict';
    
    // از 'load' به جای 'ready' استفاده می‌کنیم تا مطمئن شویم
    // تمام ویجت‌ها بارگذاری شده‌اند، سپس ویجت خود را جابجا می‌کنیم.
    
    const $ = jQuery;
    const widgetId = 'jay_relog_dashboard_widget';
    
    // --- ۱. (مهم) جابجایی ویجت به بالا (روش JS) ---
    const widget = $('#' + widgetId);
    const dashboardColumn = $('#normal-sortables'); // ستون اصلی (راست)
    
    if (widget.length && dashboardColumn.length) {
        // ویجت را پیدا کن و آن را به ابتدای ستون اصلی منتقل کن
        dashboardColumn.prepend(widget);
    }

    // --- ۲. حذف تیک ویجت از "تنظیمات صفحه" ---
    const checkboxLabel = $( 'label[for="' + widgetId + '-hide"]' );
    if ( checkboxLabel.length > 0 ) {
        checkboxLabel.remove();
    }

    // --- ۳. راه‌اندازی اسلایدر کامل Keen-Slider ---
    const sliderContainer = $("#jay-relog-sponsor-slider");
    
    // بررسی اینکه آیا کتابخانه KeenSlider و آبجکت داده ما وجود دارد
    if (sliderContainer.length === 0 || typeof KeenSlider === 'undefined' || typeof jayRelogDashboard === 'undefined') {
        return;
    }

    // پیدا کردن عناصر ناوبری
    const arrowLeft = $('#jay-relog-slider-arrow-left');
    const arrowRight = $('#jay-relog-slider-arrow-right');
    const dotsContainer = $('#jay-relog-slider-dots');
    const sponsors = jayRelogDashboard.sponsors; // خواندن داده‌های اسپانسر از PHP

    if (!sponsors || sponsors.length === 0) {
        sliderContainer.parent().hide(); // اگر اسپانسری نبود، کل بخش اسلایدر را مخفی کن
        return;
    }
    
    if (sponsors.length <= 1) {
        // اگر فقط یک اسلاید بود، کنترل‌ها را مخفی کن
        dotsContainer.hide();
        arrowLeft.hide();
        arrowRight.hide();
    }

    // --- (جدید) ۴. ساخت داینامیک اسلایدها ---
    // ابتدا کانتینر اسلایدر را (از placeholder) خالی می‌کنیم
    sliderContainer.empty(); 
    
    // حالا اسلایدهای واقعی را بر اساس داده‌های PHP می‌سازیم
    $.each(sponsors, function(index, sponsor) {
        const slideHTML = `
            <div class="keen-slider__slide">
                <a href="${sponsor.link}" target="_blank">
                    <img src="${sponsor.image_url}" alt="${sponsor.image_alt}" loading="lazy">
                </a>
            </div>
        `;
        sliderContainer.append(slideHTML);
    });
    // --- پایان ساخت اسلایدها ---


    let keenSlider = null; // متغیر برای نگهداری اسلایدر
    let autoplayInterval = null; // متغیر برای چرخش خودکار

    /**
     * تابع ساخت نقطه‌ها (از مستندات keen-slider)
     */
    function buildDots(slider) {
        if (!dotsContainer.length) return;
        dotsContainer.empty(); // پاک کردن نقطه‌های قبلی (از PHP)
        
        const slidesCount = slider.track.details.slides.length;
        
        for (let i = 0; i < slidesCount; i++) {
            const dot = $('<div class="dot"></div>');
            dot.on('click', () => slider.moveToIdx(i));
            dotsContainer.append(dot);
        }
        updateDots(slider);
    }
    
    /**
     * تابع آپدیت نقطه‌ها
     */
    function updateDots(slider) {
         if (!dotsContainer.length) return;
         const slide = slider.track.details.rel; // اسلاید فعلی
         dotsContainer.find('.dot').each((idx, dot) => {
            $(dot).toggleClass('dot--active', idx === slide);
         });
    }

    /**
     * تابع آپدیت فلش‌ها
     */
    function updateArrows(slider) {
        if (!arrowLeft.length || !arrowRight.length) return;
        
        const s = slider.track.details;
        const slide = s.rel;
        const min = s.minIdx;
        const max = s.maxIdx;
        
        // در حالت loop، فلش‌ها هرگز غیرفعال نمی‌شوند
        if (slider.options.loop) {
            arrowLeft.removeClass('arrow--disabled');
            arrowRight.removeClass('arrow--disabled');
        } else {
             arrowLeft.toggleClass('arrow--disabled', slide === min);
             arrowRight.toggleClass('arrow--disabled', slide === max);
        }
    }

    /**
     * تابع شروع/ریست چرخش خودکار
     */
    function startAutoplay(slider) {
        clearInterval(autoplayInterval); // پاک کردن تایمر قبلی
        autoplayInterval = setInterval(() => {
            slider.next();
        }, 5000); // 5 ثانیه
    }
    
    /**
     * تابع توقف چرخش خودکار (هنگام کشیدن)
     */
    function stopAutoplay() {
         clearInterval(autoplayInterval);
    }

    // --- ساخت اسلایدر ---
    keenSlider = new KeenSlider(sliderContainer[0], {
        loop: true,     // چرخشی (از آخر به اول برمی‌گردد)
        drag: true,     // قابلیت کشیدن فعال باشد
        rtl: jayRelogDashboard.is_rtl, // (جدید) تنظیم RTL از وردپرس
        slides: {
            perView: 1, // همیشه 1 اسلاید نمایش بده
            spacing: 0  // بدون فاصله
        },
        
        // رویدادها
        created(s) {
            buildDots(s);    // 1. نقطه‌ها را بساز
            updateArrows(s); // 2. فلش‌ها را تنظیم کن
            if (s.track.details.slides.length > 1) {
                startAutoplay(s); // 3. چرخش خودکار را (فقط اگر بیش از 1 اسلاید بود) شروع کن
            }
        },
        slideChanged(s) {
            updateDots(s);   // آپدیت نقطه‌ها در هر تغییر
            updateArrows(s); // آپدیت فلش‌ها در هر تغییر
        },
        dragStarted(s) {
            stopAutoplay(); // توقف چرخش هنگام کشیدن
        },
        dragEnded(s) {
            if (s.track.details.slides.length > 1) {
                 startAutoplay(s); // شروع مجدد چرخش بعد از رها کردن
            }
        }
    });

    // --- اتصال رویدادهای کلیک به فلش‌ها ---
    arrowLeft.on('click', (e) => {
        e.preventDefault();
        keenSlider.prev();
    });
    
    arrowRight.on('click', (e) => {
        e.preventDefault();
        keenSlider.next();
    });
    
    // --- (جدید) اتصال رویداد "این چیست؟" ---
    const sponsorshipGuide = $('#sponsorship-guide');
    sponsorshipGuide.on('click', '.question', function() {
        // کلاس 'show' را به ul اضافه یا کم می‌کند
        sponsorshipGuide.find('ul').toggleClass('show');
    });
});
