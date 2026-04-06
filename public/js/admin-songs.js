/* All document-level listeners are registered only once per session.
   admin-songs.js lives in a page body block and Turbo re-executes it on
   each visit; the flag below prevents accumulating duplicate handlers.  */
if (!window._adminSongsInit) {
    window._adminSongsInit = true;

(function () {
    'use strict';
    const cfg = window.SONGS_CONFIG || {};

    /* ── Helpers ─────────────────────────────────────────────────────── */
    function esc(s)     { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escAttr(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;'); }

    function updateDisplay(cell, field, value) {
        const d = cell.querySelector('.display-val');
        if (field === 'etikett') {
            if (!value) { d.innerHTML = '<span class="text-muted">—</span>'; return; }
            const map = { blau:'bg-primary text-white', gelb:'bg-warning text-dark',
                          rosa:'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
                          extrabox:'bg-secondary text-white' };
            const cls = map[value.split(' ')[0].toLowerCase()] ?? 'bg-light text-dark border';
            d.innerHTML = `<span class="badge ${cls} fw-normal">${esc(value)}</span>`;
        } else if (field === 'dropboxlink') {
            d.innerHTML = value
                ? `<i class="bi bi-folder-check text-success" title="${escAttr(value)}"></i>`
                : '<i class="bi bi-folder-x text-muted"></i>';
        } else if (field === 'composer') {
            d.textContent = value || '—';
            d.className   = 'display-val text-muted';
        } else {
            d.textContent = value;
        }
    }

    /* ── Cell state ──────────────────────────────────────────────────── */
    function activate(cell) {
        if (cell.classList.contains('editing')) return;
        const input = cell.querySelector('.edit-input');
        if (!input) return;
        input.dataset.original = input.value;   // baseline for change detection
        cell.classList.add('editing');
        cell.querySelector('.display-val').classList.add('d-none');
        input.classList.remove('d-none');
        input.focus();
        if (input.type === 'text') input.select();
    }

    function deactivate(cell) {
        cell.classList.remove('editing');
        cell.querySelector('.display-val').classList.remove('d-none');
        cell.querySelector('.edit-input').classList.add('d-none');
    }

    function flash(cell, ok) {
        cell.classList.remove('cell-flash-ok', 'cell-flash-err');
        cell.classList.add(ok ? 'cell-flash-ok' : 'cell-flash-err');
        setTimeout(() => cell.classList.remove('cell-flash-ok', 'cell-flash-err'), 1400);
    }

    /* ── Save ────────────────────────────────────────────────────────── */
    async function commit(cell) {
        if (!cell.classList.contains('editing')) return;
        const input  = cell.querySelector('.edit-input');
        const songId = cell.closest('tr').dataset.id;
        const field  = cell.dataset.field;
        const value  = input.value;

        // No change → just close
        if (value === input.dataset.original) { deactivate(cell); return; }

        // Deactivate now: removes .editing so any focusout that fires while we
        // await the fetch hits the guard above and returns without double-saving.
        deactivate(cell);

        try {
            const res  = await fetch(`/admin/songs/${songId}/patch-field`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ _token: cfg.csrfPatch, field, value }),
            });
            const json = await res.json();
            if (!res.ok || json.error) throw new Error(json.error ?? 'Fehler beim Speichern');
            input.dataset.original = value;
            updateDisplay(cell, field, value);
            flash(cell, true);
        } catch (err) {
            input.value = input.dataset.original;
            flash(cell, false);
            if (err.message) alert(err.message);
        }
    }

    /* ── Event delegation — works regardless of when DOM is ready ────── */

    // Expand / collapse movement rows
    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-expand-movements');
        if (!btn) return;
        const parentId = btn.dataset.parent;
        const rows = document.querySelectorAll('.movement-parent-' + parentId);
        const chev = document.getElementById('chev-' + parentId);
        const open = rows[0] && rows[0].style.display !== 'none';
        rows.forEach(r => { r.style.display = open ? 'none' : ''; });
        if (chev) chev.className = open ? 'bi bi-chevron-right text-muted' : 'bi bi-chevron-down text-primary';
    });

    // Click cell → activate
    document.addEventListener('click', e => {
        if (window.innerWidth < 768) return;
        if (e.target.closest('.btn-expand-movements')) return;
        const cell = e.target.closest('.editable-cell');
        if (cell && !e.target.closest('.edit-input')) activate(cell);
    });

    // Enter → save,  Escape → cancel
    document.addEventListener('keydown', e => {
        if (!e.target.classList.contains('edit-input')) return;
        const cell = e.target.closest('.editable-cell');
        if (!cell) return;
        if (e.key === 'Enter' && e.target.type !== 'date') {
            e.preventDefault();
            commit(cell);
        } else if (e.key === 'Escape') {
            e.target.value = e.target.dataset.original;
            deactivate(cell);
        }
    });

    // Focus leaves input → save  (focusout bubbles; blur does not)
    document.addEventListener('focusout', e => {
        if (!e.target.classList.contains('edit-input')) return;
        const cell = e.target.closest('.editable-cell');
        if (cell) commit(cell);
    });

    // Date picker changed → save
    document.addEventListener('change', e => {
        if (!e.target.classList.contains('edit-input') || e.target.type !== 'date') return;
        const cell = e.target.closest('.editable-cell');
        if (cell) commit(cell);
    });
}());

/* ── Song detail row expand + Dropbox file loading ──────────────────── */
(function () {
    'use strict';

    function formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return Math.round(bytes * 100) / 100 + ' ' + units[i];
    }

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fileItemHtml(file) {
        const icon = file.type === 'pdf'
            ? '<i class="bi bi-file-pdf-fill text-danger me-3 fs-4"></i>'
            : file.type === 'audio'
                ? '<i class="bi bi-file-music-fill text-primary me-3 fs-4"></i>'
                : '<i class="bi bi-file-play-fill text-info me-3 fs-4"></i>';
        const badge = file.type === 'pdf'
            ? '<span class="badge bg-danger">PDF</span>'
            : file.type === 'audio'
                ? '<span class="badge bg-primary">Audio</span>'
                : '<span class="badge bg-info">Video</span>';
        const sizeStr = file.size > 0 ? `<small class="text-muted">${formatBytes(file.size)}</small>` : '';
        return `<a href="#"
            class="dropbox-file-link list-group-item list-group-item-action d-flex justify-content-between align-items-center"
            data-file-path="${escHtml(file.path)}"
            data-file-name="${escHtml(file.name)}"
            data-file-type="${escHtml(file.type)}">
            <div class="d-flex align-items-center flex-grow-1">
                ${icon}
                <div class="flex-grow-1">
                    <div class="fw-semibold">${escHtml(file.name)}</div>
                    ${sizeStr}
                </div>
            </div>
            <div class="ms-3 d-flex align-items-center gap-2">
                ${badge}
                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
            </div>
        </a>`;
    }

    function updateSpeedPresets(speed) {
        document.querySelectorAll('.speed-preset').forEach(function (btn) {
            const s = parseFloat(btn.dataset.speed);
            btn.classList.remove('btn-primary', 'btn-outline-primary', 'btn-outline-secondary');
            if (Math.abs(s - speed) < 0.01)  btn.classList.add('btn-primary');
            else if (s === 1.0)               btn.classList.add('btn-outline-primary');
            else                              btn.classList.add('btn-outline-secondary');
        });
    }

    function attachFileHandlers(root) {
        root.querySelectorAll('.dropbox-file-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const cfg      = window.SONGS_CONFIG || {};
                const filePath = this.dataset.filePath;
                const fileName = this.dataset.fileName;
                const fileType = this.dataset.fileType;
                const spinner  = this.querySelector('.spinner-border');
                if (spinner) spinner.classList.remove('d-none');

                fetch(cfg.linkUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: filePath })
                })
                .then(r => r.json())
                .then(data => {
                    if (spinner) spinner.classList.add('d-none');
                    if (!data.link) { alert('Fehler: ' + (data.error || 'Unbekannter Fehler')); return; }
                    if (fileType === 'pdf') {
                        document.getElementById('pdfFileName').textContent  = fileName;
                        document.getElementById('pdfViewerFrame').src       = cfg.viewUrl + '?path=' + encodeURIComponent(filePath);
                        document.getElementById('pdfDownloadLink').href     = data.link;
                        document.getElementById('pdfDownloadLink').download = fileName;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('pdfViewerModal')).show();
                    } else if (fileType === 'audio') {
                        const audioPlayer = document.getElementById('audioPlayer');
                        const speedSlider = document.getElementById('speedSlider');
                        const speedValue  = document.getElementById('speedValue');
                        document.getElementById('audioFileName').textContent  = fileName;
                        audioPlayer.src = data.link;
                        document.getElementById('audioDownloadLink').href     = data.link;
                        document.getElementById('audioDownloadLink').download = fileName;
                        if (speedSlider) { speedSlider.value = 1.0; audioPlayer.playbackRate = 1.0; }
                        if (speedValue)  speedValue.textContent = '1.0';
                        updateSpeedPresets(1.0);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('audioPlayerModal')).show();
                    } else {
                        window.open(data.link, '_blank');
                    }
                })
                .catch(() => {
                    if (spinner) spinner.classList.add('d-none');
                    alert('Fehler beim Laden der Datei. Bitte versuchen Sie es erneut.');
                });
            });
        });
    }

    function loadSongFiles(container) {
        const cfg         = window.SONGS_CONFIG || {};
        const dropboxPath = container.dataset.dropboxPath;
        if (!dropboxPath) {
            container.innerHTML = '<p class="text-muted p-3 mb-0 small">Kein Dropbox-Pfad hinterlegt.</p>';
            return;
        }
        fetch(cfg.filesUrl + '?path=' + encodeURIComponent(dropboxPath))
            .then(r => r.json())
            .then(data => {
                container.dataset.loaded = '1';
                const files = data.files || [];
                if (files.length === 0) {
                    container.innerHTML = '<p class="text-muted p-3 mb-0 small">Keine Dateien gefunden.</p>';
                    return;
                }
                container.innerHTML = files.map(fileItemHtml).join('');
                attachFileHandlers(container);
            })
            .catch(() => {
                container.innerHTML = '<p class="text-danger p-3 mb-0 small">Fehler beim Laden der Dateien.</p>';
            });
    }

    // Toggle detail row visibility
    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-expand-detail');
        if (!btn) return;
        const songId    = btn.dataset.song;
        const detailRow = document.querySelector('.song-detail-row[data-song-id="' + songId + '"]');
        const chev      = document.getElementById('detail-chev-' + songId);
        if (!detailRow) return;

        const open = detailRow.style.display !== 'none';
        detailRow.style.display = open ? 'none' : '';
        if (chev) chev.className = open ? 'bi bi-chevron-right' : 'bi bi-chevron-down text-primary';

        if (!open) {
            const container = detailRow.querySelector('.song-files-container:not([data-loaded])');
            if (container) loadSongFiles(container);
        }
    });

    // Audio player speed controls
    const audioPlayer = document.getElementById('audioPlayer');
    if (audioPlayer) {
        const speedSlider  = document.getElementById('speedSlider');
        const speedValue   = document.getElementById('speedValue');
        const audioPlayBtn = document.getElementById('audioPlayBtn');

        if (speedSlider) {
            speedSlider.addEventListener('input', function () {
                const speed = parseFloat(this.value);
                audioPlayer.playbackRate = speed;
                speedValue.textContent   = speed.toFixed(2);
                updateSpeedPresets(speed);
            });
        }
        document.querySelectorAll('.speed-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const speed = parseFloat(this.dataset.speed);
                if (speedSlider) speedSlider.value = speed;
                audioPlayer.playbackRate = speed;
                if (speedValue) speedValue.textContent = speed.toFixed(2);
                updateSpeedPresets(speed);
            });
        });
        if (audioPlayBtn) {
            audioPlayBtn.addEventListener('click', function () {
                audioPlayer.paused ? audioPlayer.play() : audioPlayer.pause();
            });
            audioPlayer.addEventListener('play',  function () {
                audioPlayBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i>Pause';
                audioPlayBtn.classList.replace('btn-success', 'btn-warning');
            });
            audioPlayer.addEventListener('pause', function () {
                audioPlayBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Abspielen';
                audioPlayBtn.classList.replace('btn-warning', 'btn-success');
            });
        }
        const audioModal = document.getElementById('audioPlayerModal');
        if (audioModal) {
            audioModal.addEventListener('hidden.bs.modal', function () {
                audioPlayer.pause();
                audioPlayer.currentTime = 0;
            });
        }
    }
}());

/* ── Movement row drag & drop reorder ──────────────────────────────── */
(function () {
    const cfg = window.SONGS_CONFIG || {};
    let dragRow = null;

    function clearIndicators() {
        document.querySelectorAll('tr.movement-row.drop-before, tr.movement-row.drop-after')
            .forEach(r => r.classList.remove('drop-before', 'drop-after'));
    }

    async function saveOrder(parentId) {
        const rows  = document.querySelectorAll('.movement-parent-' + parentId);
        const order = Array.from(rows).map(r => parseInt(r.dataset.id));
        try {
            await fetch('/admin/songs/' + parentId + '/reorder-children', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: cfg.csrfPatch, order }),
            });
        } catch (e) {
            console.error('Reorder failed', e);
        }
    }

    document.addEventListener('dragstart', e => {
        const row = e.target.closest('tr.movement-row');
        if (!row) return;
        dragRow = row;
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(() => row.classList.add('dragging'), 0);
    });

    document.addEventListener('dragend', () => {
        if (dragRow) dragRow.classList.remove('dragging');
        clearIndicators();
        dragRow = null;
    });

    document.addEventListener('dragover', e => {
        if (!dragRow) return;
        const target = e.target.closest('tr.movement-row');
        if (!target || target === dragRow || target.dataset.parentId !== dragRow.dataset.parentId) {
            clearIndicators();
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        clearIndicators();
        const rect = target.getBoundingClientRect();
        target.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drop-before' : 'drop-after');
    });

    document.addEventListener('drop', e => {
        if (!dragRow) return;
        const target = e.target.closest('tr.movement-row');
        if (!target || target === dragRow || target.dataset.parentId !== dragRow.dataset.parentId) {
            clearIndicators();
            return;
        }
        e.preventDefault();
        clearIndicators();
        const rect   = target.getBoundingClientRect();
        const before = e.clientY < rect.top + rect.height / 2;
        target.parentNode.insertBefore(dragRow, before ? target : target.nextSibling);
        saveOrder(dragRow.dataset.parentId);
    });
}());

/* ── Dropbox sync ───────────────────────────────────────────────────── */
/* Uses event delegation on document so the button works even after Turbo
   replaces the body and the original button element is gone.            */
(function () {
    document.addEventListener('click', async (e) => {
        if (!e.target.closest('#btn-sync-dropbox')) return;

        const cfg       = window.SONGS_CONFIG || {};
        const body      = document.getElementById('syncResultBody');
        const reloadBtn = document.getElementById('btnReloadAfterSync');
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('syncResultModal'));

        body.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 mb-0">Verbindung zu Dropbox wird hergestellt…</p>
            </div>`;
        reloadBtn.classList.add('d-none');
        modal.show();

        try {
            const form = new FormData();
            form.append('_token', cfg.csrfSync);

            // Update spinner text after a short delay so the user sees progress
            const progressTimer = setTimeout(() => {
                if (body.querySelector('.spinner-border')) {
                    body.querySelector('p').textContent = 'Dropbox-Ordner werden abgeglichen…';
                }
            }, 1500);

            const res  = await fetch(cfg.syncUrl, { method: 'POST', body: form });
            clearTimeout(progressTimer);
            const data = await res.json();

            if (!res.ok || data.error) {
                body.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>${data.error ?? 'Unbekannter Fehler'}</div>`;
                return;
            }

            const anyChanges = data.matched > 0 || data.created > 0;
            const statusIcon = anyChanges
                ? '<i class="bi bi-check-circle-fill text-success me-2"></i>'
                : '<i class="bi bi-info-circle-fill text-primary me-2"></i>';
            const statusText = anyChanges
                ? `${data.matched + data.created} Änderung${(data.matched + data.created) !== 1 ? 'en' : ''} vorgenommen`
                : 'Keine Änderungen';

            let html = `
                <p class="mb-3">${statusIcon}<strong>${statusText}</strong></p>
                <div class="list-group list-group-flush mb-3">
                    <div class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-link-45deg text-success me-1"></i>Neu verknüpft</span>
                        <strong class="text-success">${data.matched}</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-plus-circle text-primary me-1"></i>Neu angelegt</span>
                        <strong class="text-primary">${data.created}</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-folder-check text-muted me-1"></i>Bereits verknüpft</span>
                        <strong>${data.already_linked}</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-folder-x text-warning me-1"></i>Nicht gefunden</span>
                        <strong class="text-warning">${data.unmatched}</strong>
                    </div>
                </div>`;

            if (data.unmatched_names && data.unmatched_names.length) {
                html += `<details><summary class="text-muted small mb-1">Nicht zugeordnete Lieder (${data.unmatched_names.length})</summary>
                    <ul class="small mt-2 mb-0 ps-3">${data.unmatched_names.map(n => `<li>${n}</li>`).join('')}</ul>
                </details>`;
            }

            body.innerHTML = html;
            if (data.matched > 0 || data.created > 0) {
                reloadBtn.classList.remove('d-none');
            }
        } catch (err) {
            body.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Netzwerkfehler: ${err.message}</div>`;
        }
    });
})();

} // end if (!window._adminSongsInit)
