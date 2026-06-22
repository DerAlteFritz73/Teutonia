/* All document-level listeners are registered only once per session.
   admin-songs.js lives in a page body block and Turbo re-executes it on
   each visit; the flag below prevents accumulating duplicate handlers.  */
if (!window._adminSongsInit) {
    window._adminSongsInit = true;

    /* Canonical escaper from base.html.twig; aliased for all IIFEs in this file. */
    const esc = window.escapeHtml;

(function () {
    'use strict';
    const cfg = window.SONGS_CONFIG || {};

    /* ── Helpers ─────────────────────────────────────────────────────── */
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
        } else if (field === 'composer' || field === 'arrangeur' || field === 'compositionYear') {
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

    // Checkbox "Aktuell in Proben" changed → save
    document.addEventListener('change', e => {
        if (e.target.type !== 'checkbox' || e.target.dataset.field !== 'isAktuelleProben') return;
        const row = e.target.closest('tr');
        const songId = row.dataset.id;
        const value = e.target.checked ? '1' : '0';
        const wasChecked = e.target.checked;

        // Disable checkbox during save
        e.target.disabled = true;

        fetch(`/admin/songs/${songId}/patch-field`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: cfg.csrfPatch, field: 'isAktuelleProben', value }),
        })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(json => {
            if (json.error) throw new Error(json.error);
            flash(row, true);
        })
        .catch(err => {
            e.target.checked = !wasChecked;
            flash(row, false);
            alert(err.message || 'Fehler beim Speichern');
        })
        .finally(() => {
            // Re-enable checkbox unless it's supposed to be disabled
            const hasDropbox = row.querySelector('[data-field="dropboxlink"] .display-val i.bi-folder-check') !== null;
            if (!hasDropbox) e.target.disabled = true;
            else e.target.disabled = false;
        });
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
                    <ul class="small mt-2 mb-0 ps-3">${data.unmatched_names.map(n => `<li>${esc(n)}</li>`).join('')}</ul>
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

    /* ── Bulk delete ─────────────────────────────────────────────────────── */
    /* Document-level delegation so the controls keep working after Turbo
       swaps the table body; only visible (filtered) rows are ever acted on. */

    // Checkboxes whose row is currently displayed (offsetParent is null if hidden)
    function getVisibleCheckboxes() {
        const table = document.getElementById('songs-table');
        if (!table) return [];
        return Array.from(table.querySelectorAll('.song-checkbox'))
            .filter(cb => { const row = cb.closest('tr'); return row && row.offsetParent !== null; });
    }

    function updateDeleteButton() {
        const deleteBtn   = document.getElementById('btn-delete-selected');
        const deleteCount = document.getElementById('delete-count');
        if (!deleteBtn || !deleteCount) return;
        const count = getVisibleCheckboxes().filter(cb => cb.checked).length;
        deleteCount.textContent = count;
        deleteBtn.style.display = count > 0 ? 'inline-block' : 'none';
    }

    // Select-all → toggle every visible checkbox
    document.addEventListener('change', e => {
        if (e.target.id !== 'selectAllCheckbox') return;
        getVisibleCheckboxes().forEach(cb => { cb.checked = e.target.checked; });
        updateDeleteButton();
    });

    // Individual checkbox → reconcile the select-all tri-state
    document.addEventListener('change', e => {
        if (!e.target.classList.contains('song-checkbox')) return;
        const visible        = getVisibleCheckboxes();
        const selectAll      = document.getElementById('selectAllCheckbox');
        if (selectAll) {
            const allChecked  = visible.length > 0 && visible.every(cb => cb.checked);
            const someChecked = visible.some(cb => cb.checked);
            selectAll.checked       = allChecked;
            selectAll.indeterminate = someChecked && !allChecked;
        }
        updateDeleteButton();
    });

    // Delete button → POST selected IDs
    document.addEventListener('click', e => {
        if (!e.target.closest('#btn-delete-selected')) return;
        const cfg = window.SONGS_CONFIG || {};
        const selected = getVisibleCheckboxes()
            .filter(cb => cb.checked)
            .map(cb => parseInt(cb.dataset.id, 10));

        if (!selected.length) return;
        if (!confirm(`Wirklich ${selected.length} Lied(er) löschen?`)) return;

        fetch(cfg.bulkDeleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': cfg.csrfPatch
            },
            body: JSON.stringify({ ids: selected })
        })
        .then(r => r.ok ? location.reload() : Promise.reject(r.statusText))
        .catch(err => alert('Fehler beim Löschen: ' + err));
    });
})();

} // end if (!window._adminSongsInit)
