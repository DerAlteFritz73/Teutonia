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
                // Plain "slide" effect (not 3D "coverflow"): the active-slide
                // scale-up is done in CSS (.swiper-slide-active), which avoids
                // coverflow's perspective transform getting clipped by the
                // container's necessary horizontal overflow clipping.
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
