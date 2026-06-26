/* score-sync.js — Mitlesen: moving cursor over PDF score while audio plays */
(function () {
    'use strict';

    /* ── DOM refs ──────────────────────────────────────────────────────── */
    const scoreCanvas   = document.getElementById('scoreCanvas');
    const cursorCanvas  = document.getElementById('cursorCanvas');
    const syncAudio     = document.getElementById('syncAudioPlayer');
    const wrapper       = document.getElementById('scoreCanvasWrapper');
    const syncModal     = document.getElementById('syncViewerModal');
    const prevBtn       = document.getElementById('syncPrevPage');
    const nextBtn       = document.getElementById('syncNextPage');
    const pageInfo      = document.getElementById('syncPageInfo');
    const viewerTitle   = document.getElementById('syncViewerTitle');
    const mitlesenBtn   = document.getElementById('audioMitlesenBtn');
    const editToggle    = document.getElementById('syncEditToggle');
    const anchorEditor  = document.getElementById('syncAnchorEditor');
    const anchorList    = document.getElementById('anchorList');
    const anchorCount   = document.getElementById('anchorCount');
    const saveBtn       = document.getElementById('syncSaveAnchors');
    const deleteAllBtn  = document.getElementById('syncDeleteAllAnchors');

    if (!syncModal || !scoreCanvas) return;

    const cfg   = window.PROBEN_CONFIG || {};
    const state = {
        pdfDoc:      null,
        currentPage: 1,
        totalPages:  0,
        anchors:     [],    // [{t, page, y}] sorted by t
        pdfPath:     null,
        audioPath:   null,
        songId:      null,
        editMode:    false,
        rafId:       null,
        renderTask:  null,
    };

    /* ── PDF.js lazy loader ────────────────────────────────────────────── */
    let pdfjsLib = null;
    function ensurePdfJs() {
        if (pdfjsLib) return Promise.resolve(pdfjsLib);
        return import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs')
            .then(function (mod) {
                pdfjsLib = mod;
                pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';
                return pdfjsLib;
            });
    }

    /* ── Page rendering ────────────────────────────────────────────────── */
    function renderPage(pageNum) {
        if (!state.pdfDoc) return Promise.resolve();
        if (pageNum < 1 || pageNum > state.totalPages) return Promise.resolve();

        if (state.renderTask) {
            state.renderTask.cancel && state.renderTask.cancel();
        }

        state.currentPage = pageNum;
        pageInfo.textContent = 'Seite ' + pageNum + ' / ' + state.totalPages;

        return state.pdfDoc.getPage(pageNum).then(function (page) {
            const availW  = wrapper.clientWidth || 800;
            const viewport = page.getViewport({ scale: 1 });
            const scale    = availW / viewport.width;
            const scaled   = page.getViewport({ scale: scale });

            scoreCanvas.width  = scaled.width;
            scoreCanvas.height = scaled.height;
            cursorCanvas.width  = scaled.width;
            cursorCanvas.height = scaled.height;

            const ctx = scoreCanvas.getContext('2d');
            ctx.clearRect(0, 0, scoreCanvas.width, scoreCanvas.height);

            const task = page.render({ canvasContext: ctx, viewport: scaled });
            state.renderTask = task;
            return task.promise.then(function () {
                state.renderTask = null;
                clearCursor();
            }).catch(function (e) {
                if (e && e.name !== 'RenderingCancelledException') console.error(e);
            });
        });
    }

    function clearCursor() {
        const ctx = cursorCanvas.getContext('2d');
        ctx.clearRect(0, 0, cursorCanvas.width, cursorCanvas.height);
    }

    function drawCursorAt(yRatio) {
        const ctx = cursorCanvas.getContext('2d');
        ctx.clearRect(0, 0, cursorCanvas.width, cursorCanvas.height);
        const y = Math.round(yRatio * cursorCanvas.height);
        ctx.save();
        ctx.strokeStyle = 'rgba(220, 53, 69, 0.8)';
        ctx.lineWidth   = 2;
        ctx.setLineDash([]);
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(cursorCanvas.width, y);
        ctx.stroke();
        // Small triangle indicator on the left
        ctx.fillStyle = 'rgba(220, 53, 69, 0.9)';
        ctx.beginPath();
        ctx.moveTo(0, y - 6);
        ctx.lineTo(10, y);
        ctx.lineTo(0, y + 6);
        ctx.fill();
        ctx.restore();

        // Keep cursor vertically centred in the visible wrapper area
        const target = y - wrapper.clientHeight / 2;
        wrapper.scrollTop = Math.max(0, target);
    }

    /* ── Anchor binary search + interpolation ───────────────────────────  */
    function upperBound(arr, t) {
        let lo = 0, hi = arr.length;
        while (lo < hi) {
            const mid = (lo + hi) >>> 1;
            if (arr[mid].t <= t) lo = mid + 1; else hi = mid;
        }
        return lo;
    }

    /* ── rAF cursor loop ───────────────────────────────────────────────── */
    function cursorTick() {
        state.rafId = requestAnimationFrame(cursorTick);
        if (!state.anchors.length) return;

        const t   = syncAudio.currentTime;
        const idx = upperBound(state.anchors, t) - 1;
        if (idx < 0) return;

        const a = state.anchors[idx];
        const b = state.anchors[idx + 1];

        if (a.page !== state.currentPage) {
            renderPage(a.page);
            return;
        }

        let y = a.y;
        if (b && b.page === a.page && b.t > a.t) {
            const frac = Math.min(1, (t - a.t) / (b.t - a.t));
            y = a.y + frac * (b.y - a.y);
        }

        drawCursorAt(y);
    }

    function startCursorLoop() {
        if (state.rafId) cancelAnimationFrame(state.rafId);
        state.rafId = requestAnimationFrame(cursorTick);
    }

    function stopCursorLoop() {
        if (state.rafId) { cancelAnimationFrame(state.rafId); state.rafId = null; }
        clearCursor();
    }

    /* ── Open sync viewer ──────────────────────────────────────────────── */
    function openSyncViewer(songId, audioPath, pdfPath, audioSrc, audioFileName) {
        state.songId    = songId;
        state.audioPath = audioPath;
        state.pdfPath   = pdfPath;
        state.anchors   = [];

        viewerTitle.textContent = audioFileName || '';

        // Fetch anchors (then open modal, then load PDF)
        fetch(cfg.syncUrl + '?pdf=' + encodeURIComponent(pdfPath) + '&audio=' + encodeURIComponent(audioPath))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!cfg.isAdmin && (!data.anchors || !data.anchors.length)) {
                    showToast('Für dieses Lied sind noch keine Sync-Daten vorhanden.');
                    return;
                }
                state.anchors = (data.anchors || []).slice().sort(function (a, b) { return a.t - b.t; });

                // Set up audio
                syncAudio.src         = audioSrc;
                syncAudio.currentTime = 0;
                syncAudio.playbackRate = 1.0;

                // Show modal
                bootstrap.Modal.getOrCreateInstance(syncModal).show();

                // Load and render PDF
                ensurePdfJs()
                    .then(function (lib) {
                        const url = cfg.viewUrl + '?path=' + encodeURIComponent(pdfPath);
                        return lib.getDocument({ url: url }).promise;
                    })
                    .then(function (doc) {
                        state.pdfDoc     = doc;
                        state.totalPages = doc.numPages;
                        state.currentPage = 1;
                        return renderPage(1);
                    })
                    .then(function () {
                        startCursorLoop();
                        updateAdminUI();
                    })
                    .catch(function (err) {
                        console.error('PDF load error:', err);
                        scoreCanvas.getContext('2d').fillText('PDF konnte nicht geladen werden.', 10, 30);
                    });
            })
            .catch(function () {
                showToast('Fehler beim Laden der Sync-Daten.');
            });
    }

    /* ── "Mitlesen" button click ──────────────────────────────────────── */
    if (mitlesenBtn) {
        mitlesenBtn.addEventListener('click', function () {
            const ps = window.PROBEN_STATE || {};
            const songId    = ps.currentSongId;
            const audioPath = ps.currentAudioPath;
            const audioSrc  = ps.currentAudioSrc;

            if (!audioPath || !audioSrc) {
                showToast('Keine Audio-Datei ausgewählt.');
                return;
            }

            // Find PDF path: look in cached file list for this song
            let pdfPath = null;
            const files = songId && ps.currentFilesBySongId && ps.currentFilesBySongId[songId];
            if (files) {
                const pdf = files.find(function (f) { return f.type === 'pdf'; });
                pdfPath = pdf ? pdf.path : null;
            }

            if (!pdfPath) {
                showToast('Keine Noten (PDF) für dieses Lied gefunden.');
                return;
            }

            const audioFileName = document.getElementById('audioFileName')
                ? document.getElementById('audioFileName').textContent : '';

            // Hide audio modal, open sync viewer
            const audioModal = bootstrap.Modal.getInstance(document.getElementById('audioPlayerModal'));
            if (audioModal) audioModal.hide();

            openSyncViewer(songId, audioPath, pdfPath, audioSrc, audioFileName);
        });
    }

    /* ── Page navigation ───────────────────────────────────────────────── */
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (state.currentPage > 1) renderPage(state.currentPage - 1);
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (state.currentPage < state.totalPages) renderPage(state.currentPage + 1);
        });
    }

    /* ── Sync modal lifecycle ──────────────────────────────────────────── */
    syncModal.addEventListener('hidden.bs.modal', function () {
        stopCursorLoop();
        syncAudio.pause();
        syncAudio.src     = '';
        state.pdfDoc      = null;
        state.currentPage = 1;
        state.totalPages  = 0;
        if (editToggle) setEditMode(false);
    });

    /* ── Sync viewer speed controls ───────────────────────────────────── */
    document.querySelectorAll('.sync-speed-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const speed = parseFloat(this.dataset.speed);
            syncAudio.playbackRate = speed;
            document.querySelectorAll('.sync-speed-btn').forEach(function (b) {
                const match = Math.abs(parseFloat(b.dataset.speed) - speed) < 0.01;
                b.classList.toggle('btn-primary',         match);
                b.classList.toggle('btn-outline-primary', !match && parseFloat(b.dataset.speed) === 1);
                b.classList.toggle('btn-outline-secondary', !match && parseFloat(b.dataset.speed) !== 1);
            });
        });
    });

    /* ── Admin: edit mode ──────────────────────────────────────────────── */
    function updateAdminUI() {
        if (!anchorEditor) return;
        if (cfg.isAdmin) {
            renderAnchorList();
        }
    }

    function setEditMode(on) {
        state.editMode = on;
        if (!editToggle) return;
        editToggle.classList.toggle('btn-warning', !on);
        editToggle.classList.toggle('btn-success', on);
        editToggle.innerHTML = on
            ? '<i class="bi bi-check-circle me-1"></i>Anker-Modus aktiv'
            : '<i class="bi bi-pencil-square me-1"></i>Anker bearbeiten';
        if (anchorEditor) anchorEditor.classList.toggle('d-none', !on);
        cursorCanvas.style.pointerEvents = on ? 'auto' : 'none';
        cursorCanvas.style.cursor        = on ? 'crosshair' : 'default';
    }

    if (editToggle) {
        editToggle.addEventListener('click', function () {
            setEditMode(!state.editMode);
        });
    }

    /* Canvas click in edit mode → place anchor */
    cursorCanvas.addEventListener('click', function (e) {
        if (!state.editMode) return;
        const rect = cursorCanvas.getBoundingClientRect();
        const y    = (e.clientY - rect.top) / rect.height;
        const t    = syncAudio.currentTime;
        state.anchors.push({ t: Math.round(t * 1000) / 1000, page: state.currentPage, y: Math.round(y * 10000) / 10000 });
        state.anchors.sort(function (a, b) { return a.t - b.t; });
        renderAnchorList();
    });

    function formatTime(s) {
        const m = Math.floor(s / 60);
        const sec = (s % 60).toFixed(1).padStart(4, '0');
        return m + ':' + sec;
    }

    function renderAnchorList() {
        if (!anchorList || !anchorCount) return;
        anchorCount.textContent = state.anchors.length;
        anchorList.innerHTML = state.anchors.map(function (a, i) {
            return '<span class="badge bg-secondary d-flex align-items-center gap-1" style="font-size:.75rem">'
                + 'S.' + a.page + ' @' + formatTime(a.t)
                + ' <button class="btn-close btn-close-white" style="font-size:.6rem" data-anchor-idx="' + i + '" aria-label="Löschen"></button>'
                + '</span>';
        }).join('');
    }

    if (anchorList) {
        anchorList.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-anchor-idx]');
            if (!btn) return;
            state.anchors.splice(parseInt(btn.dataset.anchorIdx, 10), 1);
            renderAnchorList();
        });
    }

    /* Save anchors */
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!state.songId) return;
            saveBtn.disabled = true;
            fetch('/admin/songs/' + state.songId + '/sync-anchors', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    _token: cfg.syncCsrfToken,
                    pdf:    state.pdfPath,
                    audio:  state.audioPath,
                    anchors: state.anchors,
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.error) throw new Error(j.error);
                showToast('Anker gespeichert (' + state.anchors.length + ')');
                setEditMode(false);
            })
            .catch(function (err) { alert(err.message || 'Fehler beim Speichern'); })
            .finally(function () { saveBtn.disabled = false; });
        });
    }

    /* Delete all anchors */
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function () {
            if (!confirm('Alle Anker für dieses Lied löschen?')) return;
            if (!state.songId) return;
            fetch('/admin/songs/' + state.songId + '/sync-anchors', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    _token: cfg.syncCsrfToken,
                    pdf:   state.pdfPath,
                    audio: state.audioPath,
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.error) throw new Error(j.error);
                state.anchors = [];
                renderAnchorList();
                clearCursor();
                showToast('Anker gelöscht.');
                setEditMode(false);
            })
            .catch(function (err) { alert(err.message || 'Fehler beim Löschen'); });
        });
    }

    /* ── Toast helper ──────────────────────────────────────────────────── */
    function showToast(msg) {
        const id   = 'sync-toast-' + Date.now();
        const html = '<div id="' + id + '" class="toast align-items-center text-bg-secondary border-0 position-fixed bottom-0 end-0 m-3" role="alert" style="z-index:9999">'
            + '<div class="d-flex"><div class="toast-body">' + msg + '</div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
        const el    = document.getElementById(id);
        const toast = new bootstrap.Toast(el, { delay: 3500 });
        toast.show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    }

}());
