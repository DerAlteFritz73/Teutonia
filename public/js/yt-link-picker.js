/* Shared YouTube link picker.
 *
 * Replaces a raw URL input with a "search YouTube → preview → pick" flow for a
 * Symfony SongLink collection. Driven entirely by data-* attributes on
 * #song-links-container so it works regardless of the form name:
 *   data-search-url            (required) endpoint → {results:[{url,title,channel,duration,thumbnail}]}
 *   data-links-name            (required) collection base name, e.g. "song_keyword[links]"
 *   data-default-query         (optional) fallback search query
 *   data-query-title-sel       (optional) selector of a live title input
 *   data-query-composer-sel    (optional) selector of a live composer input
 *
 * Expects a search modal (#ytSearchModal with #yt-search-input / #yt-search-btn /
 * #yt-results / #yt-open-external) and an #add-link-btn trigger on the page.
 */
(function () {
    const container = document.getElementById('song-links-container');
    const addBtn    = document.getElementById('add-link-btn');
    if (!container || !addBtn) return;

    const SEARCH_URL = container.dataset.searchUrl;
    const LINKS_NAME = container.dataset.linksName;
    if (!SEARCH_URL || !LINKS_NAME) return;

    const modalEl     = document.getElementById('ytSearchModal');
    const modal       = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;
    const searchInput = document.getElementById('yt-search-input');
    const searchBtn   = document.getElementById('yt-search-btn');
    const resultsEl   = document.getElementById('yt-results');
    const externalA   = document.getElementById('yt-open-external');

    function attachRemove(row) {
        row.querySelector('.remove-link').addEventListener('click', function () {
            row.remove();
            if (window.markFormDirty) window.markFormDirty();
        });
    }
    container.querySelectorAll('.link-row').forEach(attachRemove);

    function defaultQuery() {
        const tSel = container.dataset.queryTitleSel;
        const cSel = container.dataset.queryComposerSel;
        if (tSel || cSel) {
            const t = (tSel && document.querySelector(tSel) || {}).value || '';
            const c = (cSel && document.querySelector(cSel) || {}).value || '';
            const live = (t + ' ' + c).trim();
            if (live) return live;
        }
        return container.dataset.defaultQuery || '';
    }

    function addLink(url, label) {
        const idx = parseInt(container.dataset.index, 10);
        const row = document.createElement('div');
        row.className = 'link-row d-flex align-items-center gap-2 mb-2';
        row.innerHTML =
            '<i class="bi bi-youtube text-danger fs-5 flex-shrink-0"></i>' +
            '<a target="_blank" rel="noopener" class="flex-grow-1 text-truncate" style="min-width:0"></a>' +
            '<input type="hidden" name="' + LINKS_NAME + '[' + idx + '][url]">' +
            '<input type="hidden" name="' + LINKS_NAME + '[' + idx + '][label]">' +
            '<button type="button" class="btn btn-outline-danger btn-sm remove-link flex-shrink-0" title="Entfernen"><i class="bi bi-trash"></i></button>';
        const a = row.querySelector('a');
        a.href = url;
        a.textContent = label || url;
        row.querySelector('input[name$="[url]"]').value   = url;
        row.querySelector('input[name$="[label]"]').value = (label || '').slice(0, 250);
        attachRemove(row);
        container.appendChild(row);
        container.dataset.index = idx + 1;
        if (window.markFormDirty) window.markFormDirty();
    }

    function escapeText(el, text) { el.textContent = text || ''; }

    function runSearch() {
        const q = searchInput.value.trim();
        if (!q) return;
        externalA.href = 'https://www.youtube.com/results?search_query=' + encodeURIComponent(q);
        resultsEl.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Suche…</div>';
        fetch(SEARCH_URL + '?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const results = (data && data.results) || [];
                resultsEl.innerHTML = '';
                if (!results.length) {
                    resultsEl.innerHTML = '<p class="text-muted text-center py-4 mb-0">Keine Ergebnisse – versuche es direkt auf YouTube.</p>';
                    return;
                }
                results.forEach(function (v) {
                    const videoId = (v.url.match(/[?&]v=([^&]+)/) || [])[1] || '';

                    const item = document.createElement('div');
                    item.className = 'border rounded mb-2 overflow-hidden';

                    const row = document.createElement('div');
                    row.className = 'd-flex gap-2 align-items-center p-2';

                    const img = document.createElement('img');
                    img.src = v.thumbnail; img.alt = ''; img.loading = 'lazy';
                    img.title = 'Vorschau abspielen';
                    img.style.cssText = 'width:120px;height:67px;object-fit:cover;border-radius:4px;flex:0 0 auto;cursor:pointer;';

                    const info = document.createElement('div');
                    info.className = 'flex-grow-1'; info.style.minWidth = '0';
                    const title = document.createElement('div');
                    title.className = 'fw-semibold text-truncate'; escapeText(title, v.title);
                    const meta = document.createElement('small');
                    meta.className = 'text-muted'; escapeText(meta, (v.channel || '') + (v.duration ? ' · ' + v.duration : ''));
                    info.append(title, meta);

                    const playBtn = document.createElement('button');
                    playBtn.type = 'button';
                    playBtn.className = 'btn btn-sm btn-outline-secondary flex-shrink-0';
                    playBtn.title = 'Vorschau abspielen';
                    playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';

                    const addResultBtn = document.createElement('button');
                    addResultBtn.type = 'button';
                    addResultBtn.className = 'btn btn-sm btn-primary flex-shrink-0';
                    addResultBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Übernehmen';

                    const player = document.createElement('div');
                    player.className = 'yt-player ratio ratio-16x9 d-none';

                    function togglePlay() {
                        const open = !player.classList.contains('d-none');
                        resultsEl.querySelectorAll('.yt-player').forEach(function (p) {
                            if (p !== player) { p.classList.add('d-none'); p.innerHTML = ''; }
                        });
                        if (open || !videoId) {
                            player.classList.add('d-none'); player.innerHTML = '';
                            playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
                        } else {
                            player.innerHTML = '<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>';
                            player.classList.remove('d-none');
                            playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
                        }
                    }

                    playBtn.addEventListener('click', togglePlay);
                    img.addEventListener('click', togglePlay);
                    addResultBtn.addEventListener('click', function () { addLink(v.url, v.title); if (modal) modal.hide(); });

                    row.append(img, info, playBtn, addResultBtn);
                    item.append(row, player);
                    resultsEl.appendChild(item);
                });
            })
            .catch(function () { resultsEl.innerHTML = '<p class="text-danger text-center py-4 mb-0">Fehler bei der Suche.</p>'; });
    }

    addBtn.addEventListener('click', function () {
        if (!modal) return;
        searchInput.value = defaultQuery();
        resultsEl.innerHTML = '';
        modal.show();
        setTimeout(function () { searchInput.focus(); runSearch(); }, 250);
    });
    searchBtn.addEventListener('click', runSearch);
    searchInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } });

    // Stop any preview that's still playing when the dialog is closed.
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            resultsEl.querySelectorAll('.yt-player').forEach(function (p) { p.classList.add('d-none'); p.innerHTML = ''; });
        });
    }
}());
