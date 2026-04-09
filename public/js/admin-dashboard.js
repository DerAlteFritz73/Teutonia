document.addEventListener('click', async (e) => {
    if (e.target.closest('#btn-cache-clear')) {
        const cfg    = window.DASHBOARD_CONFIG || {};
        const btn    = document.getElementById('btn-cache-clear');
        const result = document.getElementById('cache-clear-result');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Wird geleert…';
        result.innerHTML = '';

        try {
            const form = new FormData();
            form.append('_token', cfg.cacheClearToken);
            const res  = await fetch(cfg.cacheClearUrl, { method: 'POST', body: form });
            const data = await res.json();

            if (!res.ok || data.error) {
                result.innerHTML = `<span class="text-warning">${data.error ?? 'Fehler beim Leeren'}</span>`;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash2 me-1"></i>Jetzt leeren';
                return;
            }

            result.innerHTML = '<span class="text-white"><i class="bi bi-check2-circle me-1"></i>Cache geleert – Seite wird neu geladen…</span>';
            // Hard-reload bypassing browser cache
            setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('_cb', Date.now());
                window.location.href = url.toString();
            }, 800);
        } catch (err) {
            result.innerHTML = `<span class="text-warning">Netzwerkfehler: ${err.message}</span>`;
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
