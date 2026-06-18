pageLoad(function () {
    const imageModalEl = document.getElementById('concertImageModal');
    if (imageModalEl) {
        const modalImg = imageModalEl.querySelector('img');
        const modalTitle = document.getElementById('concertImageModalTitle');
        document.querySelectorAll('.concert-zoom-thumb').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                modalImg.src = thumb.dataset.imgSrc;
                modalImg.alt = thumb.dataset.imgTitle || '';
                if (modalTitle) modalTitle.textContent = thumb.dataset.imgTitle || '';
                bootstrap.Modal.getOrCreateInstance(imageModalEl).show();
            });
        });
    }

    const yearSelector = document.getElementById('year-selector');
    if (!yearSelector) return;

    function filterPosts() {
        const selectedYear = yearSelector.value ? parseInt(yearSelector.value, 10) : null;

        document.querySelectorAll('[data-year]').forEach(function (el) {
            const postYear = parseInt(el.dataset.year, 10);
            el.style.display = (selectedYear === null || postYear <= selectedYear) ? '' : 'none';
        });

        // Hide rows whose every [data-year] child is hidden, to avoid leftover margins
        document.querySelectorAll('#posts-container .row').forEach(function (row) {
            const posts = Array.from(row.querySelectorAll('[data-year]'));
            if (posts.length === 0) return;
            const anyVisible = posts.some(function (p) { return p.style.display !== 'none'; });
            row.style.display = anyVisible ? '' : 'none';
        });
    }

    yearSelector.addEventListener('change', filterPosts);
    filterPosts();
});
