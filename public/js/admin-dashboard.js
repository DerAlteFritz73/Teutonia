function showCacheToast(message, type) {
    const existing = document.getElementById('cache-toast');
    if (existing) existing.remove();

    const bg   = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'bi-check2-circle' : 'bi-exclamation-triangle';
    const el   = document.createElement('div');
    el.id        = 'cache-toast';
    el.className = `toast align-items-center text-white ${bg} border-0 position-fixed bottom-0 end-0 m-4`;
    el.style.zIndex = 9999;
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
                showCacheToast(data.error ?? 'Fehler beim Leeren des Caches.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash2 me-1"></i>Jetzt leeren';
                return;
            }

            sessionStorage.setItem('cacheClearSuccess', '1');
            showCacheToast('Cache geleert – Seite wird neu geladen…', 'success');
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

        body.innerHTML = `
            <div class="list-group list-group-flush">
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
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger mb-0">Netzwerkfehler: ${err.message}</div>`;
    }
});
