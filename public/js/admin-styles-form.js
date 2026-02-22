(function () {
    const cfg     = window.STYLE_CONFIG || {};
    const c1Input = document.querySelector('[name="' + cfg.colorFieldName + '"]');
    const c2Input = document.querySelector('[name="' + cfg.color2FieldName + '"]');
    const c1Hex   = document.getElementById('color-hex');
    const c2Hex   = document.getElementById('color2-hex');
    const preview = document.getElementById('gradient-preview');

    if (!c1Input || !c2Input || !preview) return;

    function sync(colorInput, hexInput) {
        colorInput.addEventListener('input', () => {
            hexInput.value = colorInput.value;
            updatePreview();
        });
        hexInput.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
                colorInput.value = hexInput.value;
                updatePreview();
            }
        });
    }

    function updatePreview() {
        preview.style.background = `linear-gradient(135deg,${c1Input.value},${c2Input.value})`;
    }

    sync(c1Input, c1Hex);
    sync(c2Input, c2Hex);
})();

document.querySelectorAll('.repertoire-toggle').forEach(btn => {
    const cfg = window.STYLE_CONFIG || {};
    btn.addEventListener('click', async () => {
        const songId    = btn.dataset.id;
        const newActive = btn.dataset.active === '1' ? '0' : '1';
        try {
            const res = await fetch(`/admin/styles/${cfg.styleId}/toggle-repertoire-song`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: cfg.csrfToken, songId, active: newActive }),
            });
            if (res.ok) {
                btn.dataset.active = newActive;
                const icon = btn.querySelector('i');
                if (newActive === '1') {
                    icon.className = 'bi bi-eye-fill text-success';
                    btn.title = 'Sichtbar – klicken zum Ausblenden';
                } else {
                    icon.className = 'bi bi-eye-slash text-muted';
                    btn.title = 'Ausgeblendet – klicken zum Anzeigen';
                }
            }
        } catch {}
    });
});
