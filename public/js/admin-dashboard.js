function showCacheToast(message, type) {
    const existing = document.getElementById('cache-toast');
    if (existing) existing.remove();

    const bg   = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'bi-check2-circle' : 'bi-exclamation-triangle';
    const el   = document.createElement('div');
    el.id        = 'cache-toast';
    el.className = `toast align-items-center text-white ${bg} border-0 position-fixed bottom-0 end-0 m-4`;
    el.style.zIndex    = 9999;
    el.style.maxWidth  = '500px';
    el.style.whiteSpace = 'pre-wrap';
    el.setAttribute('role', 'alert');
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fs-6">
                <i class="bi ${icon} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    document.body.appendChild(el);
    const toast = new bootstrap.Toast(el, { delay: type === 'success' ? 4000 : 8000 });
    toast.show();
}

// Show success toast after reload if flagged
if (sessionStorage.getItem('cacheClearSuccess')) {
    sessionStorage.removeItem('cacheClearSuccess');
    showCacheToast('Cache erfolgreich geleert.', 'success');
}

document.addEventListener('click', async (e) => {
    if (e.target.closest('#btn-cache-clear')) {
        const cfg = window.DASHBOARD_CONFIG || {};
        const btn = document.getElementById('btn-cache-clear');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Wird geleert…';

        try {
            const form = new FormData();
            form.append('_token', cfg.cacheClearToken);
            const res  = await fetch(cfg.cacheClearUrl, { method: 'POST', body: form });
            const data = await res.json();

            if (!res.ok || data.error) {
                const msg = (data.error ?? 'Fehler beim Leeren des Caches.')
                    + (data.cleared?.length ? '\nErfolgreich: ' + data.cleared.join(', ') : '');
                showCacheToast(msg, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash2 me-1"></i>Jetzt leeren';
                return;
            }

            // Clear Turbo's in-memory page cache so no stale previews show after reload
            if (window.Turbo) Turbo.clearCache();

            const what = Array.isArray(data.cleared) && data.cleared.length
                ? data.cleared.join(', ')
                : 'cache';
            sessionStorage.setItem('cacheClearSuccess', '1');
            showCacheToast('Geleert: ' + what + '\nSeite wird neu geladen…', 'success');
            setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('_cb', Date.now());
                window.location.href = url.toString();
            }, 2000);
        } catch (err) {
            showCacheToast('Netzwerkfehler: ' + err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash2 me-1"></i>Jetzt leeren';
        }
        return;
    }
});

document.addEventListener('click', async (e) => {
    if (!e.target.closest('#btn-prod-sync')) return;

    const cfg = window.DASHBOARD_CONFIG || {};
    const btn = document.getElementById('btn-prod-sync');

    if (!confirm('Datenbank und Assets von der Produktion (Hetzner) nach Dev kopieren?\n\nDie lokale Dev-Datenbank wird dabei überschrieben.')) {
        return;
    }

    const original = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Kopiere…';

    try {
        const form = new FormData();
        form.append('_token', cfg.prodSyncToken);
        const res  = await fetch(cfg.prodSyncUrl, { method: 'POST', body: form });
        const data = await res.json();

        if (data.error) {
            showCacheToast(data.error, 'error');
        } else {
            const lines = (data.steps || []).map(s =>
                `${s.ok ? '✓' : '✗'} ${s.label}: ${s.detail}`).join('\n');
            showCacheToast(
                (data.success ? 'Von Prod kopiert:\n' : 'Mit Fehlern abgeschlossen:\n') + lines,
                data.success ? 'success' : 'error'
            );
        }
    } catch (err) {
        showCacheToast('Netzwerkfehler: ' + err.message, 'error');
    } finally {
        btn.disabled  = false;
        btn.innerHTML = original;
    }
    return;
});

document.addEventListener('click', async (e) => {
    if (!e.target.closest('#btn-sync-dropbox-dash')) return;

    const cfg   = window.DASHBOARD_CONFIG || {};
    const body  = document.getElementById('syncModalDashBody');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('syncModalDash'));

    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 mb-0">Verbindung zu Dropbox…</p></div>';
    modal.show();

    try {
        const form = new FormData();
        form.append('_token', cfg.csrfToken);
        const res  = await fetch(cfg.syncUrl, { method: 'POST', body: form });
        const data = await res.json();

        if (!res.ok || data.error) {
            body.innerHTML = `<div class="alert alert-danger mb-0">${data.error ?? 'Fehler'}</div>`;
            return;
        }

        const rows = [
            `<div class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-link-45deg text-success me-1"></i>Neu verknüpft</span>
                <strong class="text-success">${data.matched}</strong>
            </div>`,
            data.movements_matched > 0 ? `<div class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-music-note text-primary me-1"></i>Sätze verknüpft</span>
                <strong class="text-primary">${data.movements_matched}</strong>
            </div>` : '',
            data.merged > 0 ? `<div class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-intersect text-info me-1"></i>Duplikate zusammengeführt</span>
                <strong class="text-info">${data.merged}</strong>
            </div>` : '',
            data.created > 0 ? `<div class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-plus-circle text-secondary me-1"></i>Neu angelegt</span>
                <strong class="text-secondary">${data.created}</strong>
            </div>` : '',
            `<div class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-folder-check text-muted me-1"></i>Bereits verknüpft</span>
                <strong>${data.already_linked}</strong>
            </div>`,
            data.unmatched > 0 ? `<div class="list-group-item d-flex justify-content-between">
                <span><i class="bi bi-folder-x text-warning me-1"></i>Nicht gefunden</span>
                <strong class="text-warning">${data.unmatched}</strong>
            </div>` : '',
        ].filter(Boolean).join('');
        body.innerHTML = `<div class="list-group list-group-flush">${rows}</div>`;
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger mb-0">Netzwerkfehler: ${err.message}</div>`;
    }
});
