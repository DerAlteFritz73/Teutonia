(function () {
    'use strict';

    /* ── Helpers ──────────────────────────────────────────────────────── */
    function formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return Math.round(bytes * 100) / 100 + ' ' + units[i];
    }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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
            if (Math.abs(s - speed) < 0.01) {
                btn.classList.add('btn-primary');
            } else if (s === 1.0) {
                btn.classList.add('btn-outline-primary');
            } else {
                btn.classList.add('btn-outline-secondary');
            }
        });
    }

    function attachFileHandlers(root) {
        root.querySelectorAll('.dropbox-file-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const cfg      = window.PROBEN_CONFIG || {};
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
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('pdfViewerModal')).show();
                    } else if (fileType === 'audio') {
                        const audioPlayer = document.getElementById('audioPlayer');
                        const speedSlider = document.getElementById('speedSlider');
                        const speedValue  = document.getElementById('speedValue');
                        document.getElementById('audioFileName').textContent  = fileName;
                        audioPlayer.src                  = data.link;
                        document.getElementById('audioDownloadLink').href     = data.link;
                        document.getElementById('audioDownloadLink').download = fileName;
                        speedSlider.value        = 1.0;
                        audioPlayer.playbackRate = 1.0;
                        speedValue.textContent   = '1.0';
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

    /* ── Shared: pre-fetch files for one accordion button ───────────────
       Fetches files, populates the badge counts and pre-fills the
       container so opening the accordion is instant.                     */
    function prefetchAccordionBtn(btn) {
        const cfg         = window.PROBEN_CONFIG || {};
        const dropboxPath = btn.dataset.dropboxPath;
        const badgeSpan   = btn.querySelector('.file-count-badges');
        const targetId    = btn.dataset.bsTarget;
        const panel       = targetId && document.querySelector(targetId);
        const container   = panel && panel.querySelector('.song-files-container');

        fetch(cfg.filesUrl + '?path=' + encodeURIComponent(dropboxPath))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const files  = data.files || [];
                const pdfs   = files.filter(function (f) { return f.type === 'pdf'; }).length;
                const audios = files.filter(function (f) { return f.type === 'audio'; }).length;
                let badges = '';
                if (pdfs)   badges += '<span class="badge bg-danger" title="PDF-Dateien"><i class="bi bi-file-pdf"></i> ' + pdfs + '</span>';
                if (audios) badges += '<span class="badge bg-success" title="Audio-Dateien"><i class="bi bi-music-note"></i> ' + audios + '</span>';
                if (badgeSpan) badgeSpan.innerHTML = badges;

                if (container && !container.dataset.loaded) {
                    container.dataset.loaded = '1';
                    if (files.length === 0) {
                        container.innerHTML = '<p class="text-muted p-3">Keine Dateien gefunden.</p>';
                    } else {
                        container.innerHTML = files.map(fileItemHtml).join('');
                        attachFileHandlers(container);
                    }
                }
            })
            .catch(function () { /* silently ignore */ });
    }

    /* ── Aktuelle Proben: eager pre-fetch (few songs) ────────────────── */
    (function () {
        const cfg = window.PROBEN_CONFIG || {};
        if (!cfg.filesUrl) return;
        document.querySelectorAll('#probenAccordion .accordion-btn-files[data-dropbox-path]').forEach(prefetchAccordionBtn);
    }());

    /* ── Noten und Aufnahmen: IntersectionObserver pre-fetch ───────────
       Fetches counts just before each row scrolls into view so the list
       is not hammered all at once (can be hundreds of songs).            */
    (function () {
        const cfg = window.PROBEN_CONFIG || {};
        if (!cfg.filesUrl) return;

        const items = document.querySelectorAll('#allFilesAccordion .accordion-item');
        if (!items.length) return;

        if (!('IntersectionObserver' in window)) {
            // Fallback: eager-fetch if IntersectionObserver not supported
            items.forEach(function (item) {
                const btn = item.querySelector('.accordion-btn-files[data-dropbox-path]');
                if (btn) prefetchAccordionBtn(btn);
            });
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                const btn = entry.target.querySelector('.accordion-btn-files[data-dropbox-path]');
                if (btn) prefetchAccordionBtn(btn);
            });
        }, { rootMargin: '300px' });

        items.forEach(function (item) { observer.observe(item); });
    }());

    /* ── Lazy-load files when an accordion opens ─────────────────────────
       Uses event delegation on `document` so it survives Turbo body
       replacements without accumulating duplicate listeners.
       The window flag ensures it is registered only once per session.    */
    if (!window._probenFileLoaderAttached) {
        window._probenFileLoaderAttached = true;

        document.addEventListener('show.bs.collapse', function (e) {
            const panel     = e.target;
            const container = panel.querySelector('.song-files-container');
            if (!container || container.dataset.loaded) return;

            const cfg         = window.PROBEN_CONFIG || {};
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

                    container.innerHTML = files.map(fileItemHtml).join('');

                    const header   = panel.previousElementSibling;
                    const btn      = header && header.querySelector('[data-dropbox-path]');
                    if (btn) {
                        const badgeSpan = btn.querySelector('.file-count-badges');
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
    }

    /* ── Audio / speed controls ──────────────────────────────────────────
       Runs directly: member-proben.js is at the bottom of the template,
       so all DOM elements are already in place when this executes.       */
    (function () {
        const audioPlayer  = document.getElementById('audioPlayer');
        if (!audioPlayer) return;

        const speedSlider  = document.getElementById('speedSlider');
        const speedValue   = document.getElementById('speedValue');
        const audioPlayBtn = document.getElementById('audioPlayBtn');

        speedSlider.addEventListener('input', function () {
            const speed = parseFloat(this.value);
            audioPlayer.playbackRate = speed;
            speedValue.textContent   = speed.toFixed(2);
            updateSpeedPresets(speed);
        });

        document.querySelectorAll('.speed-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const speed = parseFloat(this.dataset.speed);
                speedSlider.value        = speed;
                audioPlayer.playbackRate = speed;
                speedValue.textContent   = speed.toFixed(2);
                updateSpeedPresets(speed);
            });
        });

        audioPlayBtn.addEventListener('click', function () {
            audioPlayer.paused ? audioPlayer.play() : audioPlayer.pause();
        });
        audioPlayer.addEventListener('play', function () {
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
    }());

}());

/* ── Noten search filter ─────────────────────────────────────────────── */
(function () {
    'use strict';
    const input    = document.getElementById('notenSearch');
    const btn      = document.getElementById('notenSearchBtn');
    const noResult = document.getElementById('notenNoResults');
    if (!input) return;

    function doSearch() {
        const q     = input.value.trim().toLowerCase();
        const items = document.querySelectorAll('#allFilesAccordion .accordion-item');
        let visible = 0;

        items.forEach(function (item) {
            const accordionBtn = item.querySelector('.accordion-button');
            const name = accordionBtn ? accordionBtn.textContent.trim().toLowerCase() : '';
            const show = !q || name.includes(q);
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        noResult.classList.toggle('d-none', visible > 0 || !q);
    }

    if (btn) { btn.addEventListener('click', doSearch); }
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
    });
}());
