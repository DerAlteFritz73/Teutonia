document.addEventListener('turbo:load', function () {
    const cfg        = window.PROBEN_CONFIG || {};
    const pdfModal   = new bootstrap.Modal(document.getElementById('pdfViewerModal'));
    const audioModal = new bootstrap.Modal(document.getElementById('audioPlayerModal'));
    const audioPlayer   = document.getElementById('audioPlayer');
    const speedSlider   = document.getElementById('speedSlider');
    const speedValue    = document.getElementById('speedValue');
    const speedPresets  = document.querySelectorAll('.speed-preset');
    const audioPlayBtn  = document.getElementById('audioPlayBtn');

    // ── Speed controls ────────────────────────────────────────────────────────
    speedSlider.addEventListener('input', function () {
        const speed = parseFloat(this.value);
        audioPlayer.playbackRate = speed;
        speedValue.textContent   = speed.toFixed(2);
        updateSpeedPresets(speed);
    });
    speedPresets.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const speed = parseFloat(this.dataset.speed);
            speedSlider.value        = speed;
            audioPlayer.playbackRate = speed;
            speedValue.textContent   = speed.toFixed(2);
            updateSpeedPresets(speed);
        });
    });
    function updateSpeedPresets(speed) {
        speedPresets.forEach(function (btn) {
            const s = parseFloat(btn.dataset.speed);
            btn.classList.remove('btn-primary', 'btn-outline-primary', 'btn-outline-secondary');
            if (Math.abs(s - speed) < 0.01) {
                btn.classList.add('btn-primary');
            } else if (s === 1.0) {
                btn.classList.add('btn-outline-primary');
            } else {
                btn.classList.add('btn-outline-secondary');
            }
        });
    }

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
    document.getElementById('audioPlayerModal').addEventListener('hidden.bs.modal', function () {
        audioPlayer.pause();
        audioPlayer.currentTime = 0;
    });

    // ── Lazy-load files when an accordion opens ───────────────────────────────
    document.querySelectorAll('.accordion-collapse').forEach(function (panel) {
        panel.addEventListener('show.bs.collapse', function () {
            const container = this.querySelector('.song-files-container');
            if (!container || container.dataset.loaded) return;

            const dropboxPath = container.dataset.dropboxPath;
            if (!dropboxPath) {
                container.innerHTML = '<p class="text-muted p-3">Kein Dropbox-Pfad hinterlegt.</p>';
                return;
            }

            fetch(cfg.filesUrl + '?path=' + encodeURIComponent(dropboxPath))
                .then(r => r.json())
                .then(data => {
                    container.dataset.loaded = '1';
                    const files = data.files || [];

                    if (files.length === 0) {
                        container.innerHTML = '<p class="text-muted p-3">Keine Dateien gefunden.</p>';
                        return;
                    }

                    container.innerHTML = files.map(file => fileItemHtml(file)).join('');

                    const btn = panel.previousElementSibling.querySelector('[data-dropbox-path]');
                    if (btn) {
                        const badgeSpan = btn.querySelector('.ms-auto');
                        const pdfs   = files.filter(f => f.type === 'pdf').length;
                        const audios = files.filter(f => f.type === 'audio').length;
                        let badges = '';
                        if (pdfs)   badges += `<span class="badge bg-danger" title="PDF-Dateien"><i class="bi bi-file-pdf"></i> ${pdfs}</span>`;
                        if (audios) badges += `<span class="badge bg-success" title="Audio-Dateien"><i class="bi bi-music-note"></i> ${audios}</span>`;
                        if (badgeSpan) badgeSpan.innerHTML = badges;
                    }

                    attachFileHandlers(container);
                })
                .catch(() => {
                    container.innerHTML = '<p class="text-danger p-3">Fehler beim Laden der Dateien.</p>';
                });
        });
    });

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

    function formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return Math.round(bytes * 100) / 100 + ' ' + units[i];
    }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── File click handler ────────────────────────────────────────────────────
    function attachFileHandlers(root) {
        root.querySelectorAll('.dropbox-file-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
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
                    if (!data.link) {
                        alert('Fehler beim Laden der Datei: ' + (data.error || 'Unbekannter Fehler'));
                        return;
                    }
                    if (fileType === 'pdf') {
                        document.getElementById('pdfFileName').textContent   = fileName;
                        document.getElementById('pdfViewerFrame').src        = cfg.viewUrl + '?path=' + encodeURIComponent(filePath);
                        document.getElementById('pdfDownloadLink').href      = data.link;
                        document.getElementById('pdfDownloadLink').download  = fileName;
                        pdfModal.show();
                    } else if (fileType === 'audio') {
                        document.getElementById('audioFileName').textContent = fileName;
                        audioPlayer.src = data.link;
                        document.getElementById('audioDownloadLink').href    = data.link;
                        document.getElementById('audioDownloadLink').download = fileName;
                        speedSlider.value = 1.0;
                        audioPlayer.playbackRate = 1.0;
                        speedValue.textContent   = '1.0';
                        updateSpeedPresets(1.0);
                        audioModal.show();
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

    // Auto-load files for the first open accordion
    document.querySelectorAll('.accordion-collapse.show').forEach(function (panel) {
        panel.dispatchEvent(new Event('show.bs.collapse'));
    });
});
