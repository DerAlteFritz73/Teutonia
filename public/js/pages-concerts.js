document.addEventListener('DOMContentLoaded', function () {
    const yearSelector = document.getElementById('year-selector');
    if (!yearSelector) return;

    function filterPosts() {
        const selectedYear = yearSelector.value ? parseInt(yearSelector.value) : null;

        document.querySelectorAll('[data-year]').forEach(function (el) {
            const postYear = parseInt(el.dataset.year);
            if (selectedYear === null || postYear <= selectedYear) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }

    yearSelector.addEventListener('change', filterPosts);
});
