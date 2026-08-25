// Initializes the Swiper carousel for post photo galleries (see
// components/_post_gallery.html.twig). Loaded once as a <script src> in
// <head>, so -- like image-zoom.js -- this file's top-level code runs exactly
// once per browser session; pageLoad() re-invokes initGalleries() on every
// Turbo navigation, each time against the current (freshly rendered) DOM.
(function () {
    function initGalleries() {
        if (typeof Swiper === 'undefined') return;

        document.querySelectorAll('.post-gallery-swiper').forEach(function (el) {
            if (el.swiper) return;

            new Swiper(el, {
                // Deliberately minimal config -- see the CSS comment in
                // base.html.twig for why. Fixed-size slides, plain "slide"
                // effect, no autoHeight/offset options.
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: 'auto',
                pagination: {
                    el: el.querySelector('.swiper-pagination'),
                    clickable: true
                },
                navigation: {
                    nextEl: el.querySelector('.swiper-button-next'),
                    prevEl: el.querySelector('.swiper-button-prev')
                }
            });
        });
    }

    if (window.pageLoad) {
        window.pageLoad(initGalleries);
    } else {
        document.addEventListener('DOMContentLoaded', initGalleries);
    }
})();
