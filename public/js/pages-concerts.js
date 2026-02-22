document.addEventListener('turbo:load', function () {
    const yearSelector = document.getElementById('year-selector');
    if (!yearSelector) return;

    function filterPosts() {
        const selectedYear = yearSelector.value ? parseInt(yearSelector.value) : null;

        document.querySelectorAll('[data-year]').forEach(function (el) {
            const postYear = parseInt(el.dataset.year);
            const row = el.parentElement;
            const visible = selectedYear === null || postYear <= selectedYear;
            row.style.display = visible ? '' : 'none';
        });
    }

    yearSelector.addEventListener('change', filterPosts);
    filterPosts();
});
