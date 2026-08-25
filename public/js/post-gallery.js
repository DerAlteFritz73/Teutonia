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
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: 'auto',
                // Reserves edge space Swiper itself accounts for in its slide
                // positioning math, so slides never render under the nav
                // arrows -- unlike CSS padding on the swiper container, which
                // Swiper's own clientWidth-based measurements don't respect.
                // (Tried together with autoHeight before, which broke
                // scrolling entirely; autoHeight is gone now, this is being
                // tried alone.)
                slidesOffsetBefore: 40,
                slidesOffsetAfter: 40,
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
