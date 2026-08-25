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

            // Nav buttons are siblings of .post-gallery-swiper in the
            // surrounding .post-gallery-wrap (flex row), not children inside
            // it -- see the CSS comment in base.html.twig for why (both
            // Swiper's default in-container button position and its
            // slidesOffsetBefore/After options proved unreliable here).
            var wrap = el.closest('.post-gallery-wrap');
            var prevEl = wrap ? wrap.querySelector('.post-gallery-prev') : null;
            var nextEl = wrap ? wrap.querySelector('.post-gallery-next') : null;

            new Swiper(el, {
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: 'auto',
                pagination: {
                    el: el.querySelector('.swiper-pagination'),
                    clickable: true
                },
                navigation: {
                    nextEl: nextEl,
                    prevEl: prevEl
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
