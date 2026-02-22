(function () {
    const cfg  = window.DASHBOARD_CONFIG || {};
    const btn  = document.getElementById('btn-sync-dropbox-dash');
    if (!btn) return;
    const modal = new bootstrap.Modal(document.getElementById('syncModalDash'));
    const body  = document.getElementById('syncModalDashBody');

    btn.addEventListener('click', async () => {
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
})();
