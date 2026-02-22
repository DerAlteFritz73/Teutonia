(function () {
    'use strict';
    const cfg = window.SONGS_CONFIG || {};

    /* ── Badge renderer ─────────────────────────────────────────────── */
    function renderEtikett(val) {
        if (!val) return '<span class="text-muted">—</span>';
        const prefix = val.split(' ')[0].toLowerCase();
        const map = {
            blau:     'bg-primary text-white',
            gelb:     'bg-warning text-dark',
            rosa:     'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
            extrabox: 'bg-secondary text-white',
        };
        const cls = map[prefix] ?? 'bg-light text-dark border';
        return `<span class="badge ${cls} fw-normal">${escHtml(val)}</span>`;
    }

    function renderDropbox(val) {
        if (!val) return '<i class="bi bi-folder-x text-muted"></i>';
        return `<i class="bi bi-folder-check text-success" title="${escAttr(val)}"></i>`;
    }

    function escHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escAttr(s) {
        return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    }

    /* ── AJAX save ──────────────────────────────────────────────────── */
    async function saveField(cell, songId, field, value) {
        try {
            const res = await fetch(`/admin/songs/${songId}/patch-field`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: cfg.csrfPatch, field, value }),
            });
            const json = await res.json();
            if (!res.ok || json.error) {
                flashCell(cell, false);
                alert(json.error ?? 'Fehler beim Speichern');
                return false;
            }
            flashCell(cell, true);
            return true;
        } catch {
            flashCell(cell, false);
            return false;
        }
    }

    function flashCell(cell, ok) {
        cell.style.transition = 'background-color 0.15s';
        cell.style.backgroundColor = ok ? '#d1e7dd' : '#f8d7da';
        setTimeout(() => { cell.style.backgroundColor = ''; }, 1400);
    }

    /* ── Activate / deactivate ──────────────────────────────────────── */
    function activate(cell) {
        if (cell.classList.contains('editing')) return;
        cell.classList.add('editing');
        cell.querySelector('.display-val').classList.add('d-none');
        const input = cell.querySelector('.edit-input');
        input.classList.remove('d-none');
        input.focus();
        if (input.type === 'text') input.select();
    }

    function deactivate(cell) {
        cell.classList.remove('editing');
        cell.querySelector('.display-val').classList.remove('d-none');
        cell.querySelector('.edit-input').classList.add('d-none');
    }

    function updateDisplay(cell, field, value) {
        const display = cell.querySelector('.display-val');
        if (field === 'etikett') {
            display.innerHTML = renderEtikett(value);
        } else if (field === 'dropboxlink') {
            display.innerHTML = renderDropbox(value);
        } else if (field === 'composer') {
            display.textContent = value || '—';
            display.className = 'display-val text-muted';
        } else {
            display.textContent = value;
        }
    }

    /* ── Wire up every editable cell ───────────────────────────────── */
    document.querySelectorAll('.editable-cell').forEach(cell => {
        const input = cell.querySelector('.edit-input');
        if (!input) return;

        input.dataset.original = input.value;

        cell.addEventListener('click', () => {
            if (window.innerWidth < 768) return; // mobile: edit via song edit page
            activate(cell);
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && input.type !== 'date') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                input.value = input.dataset.original;
                deactivate(cell);
            }
        });

        input.addEventListener('click', e => e.stopPropagation());

        input.addEventListener('blur', async () => {
            if (!cell.classList.contains('editing')) return;

            const songId = cell.closest('tr').dataset.id;
            const field  = cell.dataset.field;
            const value  = input.value;

            if (value === input.dataset.original) {
                deactivate(cell);
                return;
            }

            const ok = await saveField(cell, songId, field, value);
            if (ok) {
                input.dataset.original = value;
                updateDisplay(cell, field, value);
            } else {
                input.value = input.dataset.original;
            }
            deactivate(cell);
        });

        if (input.type === 'date') {
            input.addEventListener('change', () => input.blur());
        }
    });
})();

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
