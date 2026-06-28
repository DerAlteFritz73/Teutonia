(function () {
    'use strict';

    /* ── Module-level state shared with score-sync.js ─────────────────── */
    window.PROBEN_STATE = {
        currentSongId:    null,
        currentAudioPath: null,
        currentAudioSrc:  null,
        currentFilesBySongId: {},   // songId → files array (cache from last prefetch/load)
    };

    /* ── Helpers ──────────────────────────────────────────────────────── */
    function formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return Math.round(bytes * 100) / 100 + ' ' + units[i];
    }

    /* Canonical escaper from base.html.twig. */
    const escHtml = window.escapeHtml;

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

    /* ── Partitur (scrolling score playback) ─────────────────────────────
       When a song folder contains a MusicXML score (type 'score'), the
       per-voice MP3s feed a scrolling OSMD view instead of being offered as
       individual recordings. Files are cached by folder path so the button
       can resolve the score + voice recordings on click.                   */
    const PARTITUR_CACHE = {};

    function buildFilesHtml(files, dropboxPath) {
        const score = files.find(function (f) { return f.type === 'score'; });
        if (!score) {
            return files.map(fileItemHtml).join('');
        }
        PARTITUR_CACHE[dropboxPath] = files;
        // The voice/Tutti MP3s drive the Partitur — don't also offer them (or
        // the score file itself) as individual rows. Keep PDFs and anything else.
        const rest = files.filter(function (f) { return f.type !== 'score' && f.type !== 'audio'; });
        const btn =
            '<div class="list-group-item d-flex flex-wrap align-items-center gap-2">' +
                '<button type="button" class="btn btn-primary btn-sm open-partitur" data-dropbox-path="' + escHtml(dropboxPath) + '">' +
                    '<i class="bi bi-music-note-list me-1"></i>Partitur abspielen' +
                '</button>' +
                '<small class="text-muted">Scrollende Noten mit Stimmen-Wiedergabe zum Mitsingen</small>' +
            '</div>';
        return btn + rest.map(fileItemHtml).join('');
    }

    function dropboxLink(path) {
        const cfg = window.PROBEN_CONFIG || {};
        return fetch(cfg.linkUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: path })
        }).then(function (r) { return r.json(); }).then(function (d) { return d.link || null; });
    }

    function openPartitur(e) {
        e.preventDefault();
        const btn   = e.currentTarget;
        const cfg   = window.PROBEN_CONFIG || {};
        const files = PARTITUR_CACHE[btn.dataset.dropboxPath];
        if (!files) return;
        const score = files.find(function (f) { return f.type === 'score'; });
        if (!score) return;

        // Map voice recordings: filenames start with 1-S / 2-A / 3-T / 4-B; a
        // "Tutti …" file is the full-ensemble mix (no matching score staff).
        const voicePath = {};
        let tuttiPath = null;
        files.filter(function (f) { return f.type === 'audio'; }).forEach(function (f) {
            const m = f.name.match(/^\s*[1-4]\s*-?\s*([SATB])/i);
            if (m) { voicePath[m[1].toUpperCase()] = f.path; }
            else if (/^\s*tutti/i.test(f.name)) { tuttiPath = f.path; }
        });

        const item      = btn.closest('.accordion-item');
        const titleSpan = item && item.querySelector('.accordion-btn-files .flex-grow-1');
        // The song name is the first text node of the title span (the <small>
        // composer follows). Fall back to the full span text if needed.
        const rawTitle  = titleSpan
            ? ((titleSpan.childNodes[0] && titleSpan.childNodes[0].textContent) || titleSpan.textContent)
            : 'Partitur';
        const title = rawTitle.trim().replace(/\s+/g, ' ') || 'Partitur';

        btn.disabled = true;
        Promise.all(
            Object.keys(voicePath).map(function (c) {
                return dropboxLink(voicePath[c]).then(function (l) { return [c, l]; });
            }).concat(tuttiPath ? [dropboxLink(tuttiPath).then(function (l) { return ['__tutti', l]; })] : [])
        ).then(function (pairs) {
            const audioByVoice = {};
            let tuttiUrl = '';
            pairs.forEach(function (p) {
                if (!p || !p[1]) return;
                if (p[0] === '__tutti') tuttiUrl = p[1];
                else audioByVoice[p[0]] = p[1];
            });
            const scoreUrl = cfg.viewUrl + '?path=' + encodeURIComponent(score.path);
            showPartitur(scoreUrl, audioByVoice, tuttiUrl, title);
        }).catch(function () {
            alert('Partitur konnte nicht geladen werden.');
        }).finally(function () {
            btn.disabled = false;
        });
    }

    function osmdScaffold(scoreUrl, audioByVoice, tuttiUrl) {
        const wrap = document.createElement('div');
        wrap.setAttribute('data-controller', 'osmd');
        wrap.setAttribute('data-osmd-url-value', scoreUrl);
        wrap.setAttribute('data-osmd-audio-by-voice-value', JSON.stringify(audioByVoice || {}));
        wrap.setAttribute('data-osmd-tutti-url-value', tuttiUrl || '');
        wrap.innerHTML =
            '<div class="d-flex flex-wrap align-items-center gap-2 mb-3">' +
                '<button type="button" class="btn btn-sm btn-primary" disabled data-osmd-target="playBtn" data-action="osmd#togglePlay"><i class="bi bi-play-fill"></i> Abspielen</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" disabled data-osmd-target="stopBtn" data-action="osmd#stop"><i class="bi bi-stop-fill"></i> Stop</button>' +
                '<label class="text-muted small mb-0"><i class="bi bi-speedometer2"></i> Tempo</label>' +
                '<input type="range" class="form-range" style="width:120px" min="0.5" max="1.5" step="0.05" value="1" data-osmd-target="speed" data-action="input->osmd#changeSpeed">' +
                '<span class="small text-muted" style="min-width:9em" data-osmd-target="speedLabel">1,00×</span>' +
                '<span class="text-muted small d-none d-sm-inline">Stimme:</span>' +
                '<span class="d-flex flex-wrap gap-2" data-osmd-target="controls"></span>' +
            '</div>' +
            '<div class="alert alert-warning small d-none" data-osmd-target="status"></div>' +
            '<div class="position-relative">' +
                '<div class="border rounded bg-white p-2" style="overflow:auto;max-height:62vh;white-space:nowrap;"><div data-osmd-target="container"></div></div>' +
                '<div data-osmd-target="playhead" class="position-absolute top-0 d-none" style="background:rgba(232,89,12,0.30);border-radius:3px;pointer-events:none;z-index:5;"></div>' +
            '</div>';
        return wrap;
    }

    function showPartitur(scoreUrl, audioByVoice, tuttiUrl, title) {
        const modalEl = document.getElementById('partiturModal');
        if (!modalEl) return;
        const titleEl = document.getElementById('partiturTitle');
        if (titleEl) titleEl.textContent = title || 'Partitur';
        const body = document.getElementById('partiturBody');
        // Build the OSMD view only once the modal is visible, so the score
        // renders at the correct width (autoResize reads the container size).
        modalEl.addEventListener('shown.bs.modal', function build() {
            modalEl.removeEventListener('shown.bs.modal', build);
            body.innerHTML = '';
            body.appendChild(osmdScaffold(scoreUrl, audioByVoice, tuttiUrl));
        });
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // Clear the OSMD controller (Stimulus disconnect → stops audio, frees the
    // render) whenever the Partitur modal is closed.
    (function () {
        const modalEl = document.getElementById('partiturModal');
        if (modalEl && !modalEl.dataset.bound) {
            modalEl.dataset.bound = '1';
            modalEl.addEventListener('hidden.bs.modal', function () {
                const body = document.getElementById('partiturBody');
                if (body) body.innerHTML = '';
            });
        }
    }());

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
        root.querySelectorAll('.open-partitur').forEach(function (btn) {
            btn.addEventListener('click', openPartitur);
        });
        root.querySelectorAll('.dropbox-file-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const cfg      = window.PROBEN_CONFIG || {};
                const filePath = this.dataset.filePath;
                const fileName = this.dataset.fileName;
                const fileType = this.dataset.fileType;
                const spinner  = this.querySelector('.spinner-border');

                // Identify the parent song-files-container to grab songId
                const container = this.closest('.song-files-container');
                const songId    = container ? (container.dataset.songId || null) : null;

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
                        // Download through the proxy too, so the saved/printed copy carries the Etikett stamp.
                        document.getElementById('pdfDownloadLink').href      = cfg.viewUrl + '?path=' + encodeURIComponent(filePath) + '&dl=1';
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

                        // Store context for the Mitlesen button in score-sync.js
                        const state = window.PROBEN_STATE;
                        state.currentSongId    = songId;
                        state.currentAudioPath = filePath;
                        state.currentAudioSrc  = data.link;
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
                // Don't overwrite a parent's server-rendered sum across its Sätze.
                if (badgeSpan && !btn.dataset.serverCounts) badgeSpan.innerHTML = badges;

                if (container && !container.dataset.loaded) {
                    container.dataset.loaded = '1';
                    if (files.length === 0) {
                        const hasMovements = container.dataset.hasMovements || data.hasSubfolders
                            || !!(panel && panel.querySelector('[id$="-movements"]'));
                        if (hasMovements) {
                            container.classList.add('d-none');
                            container.innerHTML = '';
                        } else {
                            container.innerHTML = '<p class="text-muted p-3">Keine Dateien gefunden.</p>';
                        }
                    } else {
                        // Cache files so score-sync.js can find the PDF path
                        const songId = container.dataset.songId;
                        if (songId) (window.PROBEN_STATE || {}).currentFilesBySongId && (window.PROBEN_STATE.currentFilesBySongId[songId] = files);
                        container.innerHTML = buildFilesHtml(files, dropboxPath);
                        attachFileHandlers(container);
                    }
                }
            })
            .catch(function (err) {
                // Background prefetch only — the lazy loader (show.bs.collapse)
                // re-fetches and shows a visible error if the user opens the panel.
                console.warn('Dropbox prefetch failed for', dropboxPath, err);
            });
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
                        const hasMovements = container.dataset.hasMovements || data.hasSubfolders
                            || !!panel.querySelector('[id$="-movements"]');
                        if (hasMovements) {
                            container.classList.add('d-none');
                            container.innerHTML = '';
                        } else {
                            container.innerHTML = '<p class="text-muted p-3">Keine Dateien gefunden.</p>';
                        }
                        return;
                    }

                    container.innerHTML = buildFilesHtml(files, dropboxPath);

                    // Cache files for score-sync.js
                    const songId = container.dataset.songId;
                    if (songId && window.PROBEN_STATE) window.PROBEN_STATE.currentFilesBySongId[songId] = files;

                    const header   = panel.previousElementSibling;
                    const btn      = header && header.querySelector('[data-dropbox-path]');
                    if (btn) {
                        const badgeSpan = btn.querySelector('.file-count-badges');
                        const pdfs   = files.filter(f => f.type === 'pdf').length;
                        const audios = files.filter(f => f.type === 'audio').length;
                        let badges = '';
                        if (pdfs)   badges += `<span class="badge bg-danger" title="PDF-Dateien"><i class="bi bi-file-pdf"></i> ${pdfs}</span>`;
                        if (audios) badges += `<span class="badge bg-success" title="Audio-Dateien"><i class="bi bi-music-note"></i> ${audios}</span>`;
                        // Don't overwrite a parent's server-rendered sum across its Sätze.
                if (badgeSpan && !btn.dataset.serverCounts) badgeSpan.innerHTML = badges;
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
    const noResult = document.getElementById('notenNoResults');
    if (!input) return;

    input.addEventListener('input', function () {
        const terms = input.value.toLowerCase().split(/\s+/).filter(Boolean);
        const items = document.querySelectorAll('#allFilesAccordion > .accordion-item');
        let visible = 0;

        items.forEach(function (item) {
            const btn  = item.querySelector('.accordion-button');
            // Include the Etikett explicitly: for admins it is rendered outside the
            // accordion button (inline editor), so it isn't part of btn.textContent.
            const name = ((btn ? btn.textContent : '') + ' ' + (item.dataset.etikett || '')).trim().toLowerCase();
            const show = terms.length === 0 || terms.every(function (t) { return name.includes(t); });
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        noResult.classList.toggle('d-none', visible > 0 || terms.length === 0);
    });
}());
