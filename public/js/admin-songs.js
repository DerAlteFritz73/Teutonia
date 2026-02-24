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

    // Click cell → activate
    document.addEventListener('click', e => {
        if (window.innerWidth < 768) return;
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
(function () {
    const cfg       = window.SONGS_CONFIG || {};
    const btn       = document.getElementById('btn-sync-dropbox');
    const modal     = new bootstrap.Modal(document.getElementById('syncResultModal'));
    const body      = document.getElementById('syncResultBody');
    const reloadBtn = document.getElementById('btnReloadAfterSync');

    btn.addEventListener('click', async () => {
        body.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 mb-0">Verbindung zu Dropbox…</p>
            </div>`;
        reloadBtn.classList.add('d-none');
        modal.show();

        try {
            const form = new FormData();
            form.append('_token', cfg.csrfSync);

            const res  = await fetch(cfg.syncUrl, { method: 'POST', body: form });
            const data = await res.json();

            if (!res.ok || data.error) {
                body.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>${data.error ?? 'Unbekannter Fehler'}</div>`;
                return;
            }

            let html = `
                <div class="list-group list-group-flush mb-3">
                    <div class="list-group-item d-flex justify-content-between">
                        <span><i class="bi bi-link-45deg text-success me-1"></i>Neu verknüpft</span>
                        <strong class="text-success">${data.matched}</strong>
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
            if (data.matched > 0) {
                reloadBtn.classList.remove('d-none');
            }
        } catch (err) {
            body.innerHTML = `<div class="alert alert-danger mb-0">Netzwerkfehler: ${err.message}</div>`;
        }
    });
})();
