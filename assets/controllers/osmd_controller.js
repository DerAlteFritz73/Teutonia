import { Controller } from '@hotwired/stimulus';

/*
 * OSMD (OpenSheetMusicDisplay) proof-of-concept controller.
 *
 * Renders a MusicXML file into a scrollable staff view, plays it back with a
 * soundfont (audio is synthesised from the notation, so the cursor and sound
 * stay perfectly in sync), auto-scrolls to follow the cursor, and lets the
 * user highlight a single staff/voice in colour.
 *
 *   <div data-controller="osmd" data-osmd-url-value="/poc/score.musicxml">
 *     <button data-osmd-target="playBtn" data-action="osmd#togglePlay">…</button>
 *     <button data-osmd-target="stopBtn" data-action="osmd#stop">…</button>
 *     <div data-osmd-target="controls"></div>
 *     <div data-osmd-target="status"></div>
 *     <div data-osmd-target="container"></div>
 *   </div>
 */
export default class extends Controller {
    static targets = ['container', 'controls', 'status', 'playBtn', 'stopBtn', 'playhead', 'speed', 'speedLabel', 'bpm', 'scroll', 'viewer'];
    static values = {
        url: String,
        audioByVoice: { type: Object, default: {} },
        tuttiUrl: String,
        // Highlight the whole current measure instead of just the current note.
        // Set data-osmd-measure-highlight-value="true" to switch.
        measureHighlight: { type: Boolean, default: false },
    };

    async connect() {
        this.colors = ['#d6336c', '#1c7ed6', '#2f9e44', '#e8590c', '#7048e8', '#0ca678'];
        this.activeStaff = null;
        this.tuttiActive = false;
        this.playing = false;
        // Initialise zoom/landscape up front: the default Tutti selection below
        // runs fitAllStavesZoom(), which reads this.zoom.
        this.zoom = 1;
        this.landscape = false;

        try {
            // The vendored OSMD bundle exposes the namespace as a single default export.
            const OSMD = (await import('opensheetmusicdisplay')).default;
            this.osmd = new OSMD.OpenSheetMusicDisplay(this.containerTarget, {
                // Render the whole piece as one long horizontal staff line so
                // playback can scroll smoothly sideways (no per-system jumps).
                autoResize: true,
                backend: 'svg',
                drawTitle: true,
                renderSingleHorizontalStaffline: true,
                // We scroll manually (see startAutoScroll) to keep the cursor
                // centred; OSMD's built-in followCursor only snaps it to the
                // container edge, which fights with smooth centring.
                followCursor: false,
                // Keep OSMD's own cursor invisible (alpha 0) — it spans the whole
                // system; we draw our own highlight box limited to the selected
                // voice's staff (see updatePlayhead). The element still provides
                // the current note's position.
                cursorsOptions: [{ type: 0, color: '#1c7ed6', alpha: 0, follow: false }],
            });

            this.setStatus('Lade Partitur…');
            await this.osmd.load(await this.fetchScore(this.urlValue));
            this.osmd.render();
            this.buildStaffButtons();
            this.readTempo();

            // Playback uses the real per-voice MP3 recordings (and the Tutti
            // full mix). The selected voice's recording is played and the cursor
            // is driven from its playback time (see syncCursorToAudio).
            this.playbackRate = 1;
            this.audioEl = new Audio();
            this.audioEl.preload = 'auto';
            this.audioEl.preservesPitch = true; // keep pitch when changing speed
            this.audioEl.addEventListener('ended', () => this.onAudioEnded());
            // Default to the Tutti mix so the play button works without a
            // voice selected. Songs without a Tutti mix (only per-voice tracks)
            // fall back to the first staff that has a recording, so Abspielen
            // still plays something instead of being a no-op.
            if (this.tuttiUrlValue) this.toggleTutti(this.tuttiBtn);
            else this.selectDefaultVoice();
            this.enablePlayback();
            this.updateSpeedLabel();
            this.setStatus('');

            this.setupPinchZoom();
        } catch (e) {
            this.setStatus('Fehler beim Laden der Partitur: ' + e.message);
            console.error('[osmd]', e);
        }
    }

    // Fetch the score and hand OSMD the right kind of content. We fetch the
    // bytes ourselves (instead of letting OSMD fetch the URL) because the real
    // Dropbox proxy URL (/api/dropbox/view?path=…) carries no .mxl/.musicxml
    // extension for OSMD to sniff. We detect a compressed MXL by its zip magic
    // ("PK") and pass it as a binary string; otherwise decoded MusicXML text.
    async fetchScore(url) {
        const resp = await fetch(url);
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const bytes = new Uint8Array(await resp.arrayBuffer());
        if (bytes[0] === 0x50 && bytes[1] === 0x4B) {
            let bin = '';
            for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
            return bin;
        }
        return new TextDecoder('utf-8').decode(bytes);
    }

    disconnect() {
        this.stopAutoScroll();
        this.teardownPinchZoom();
        if (this.landscape) this.exitLandscape();
        try {
            this.audioEl?.pause();
        } catch (e) { /* ignore */ }
        if (this.osmd) {
            this.osmd.clear();
            this.osmd = null;
        }
    }

    // ---- Zoom (pinch on mobile) -----------------------------------------

    // Two-finger pinch on the score scroller changes OSMD's zoom. During the
    // gesture we show a cheap CSS-transform preview; on release we commit the new
    // zoom with a single re-render so the scroll area gets the correct extent.
    setupPinchZoom() {
        if (!this.hasScrollTarget) return;
        this._onTouchStart = this.onTouchStart.bind(this);
        this._onTouchMove = this.onTouchMove.bind(this);
        this._onTouchEnd = this.onTouchEnd.bind(this);
        const el = this.scrollTarget;
        el.addEventListener('touchstart', this._onTouchStart, { passive: false });
        el.addEventListener('touchmove', this._onTouchMove, { passive: false });
        el.addEventListener('touchend', this._onTouchEnd);
        el.addEventListener('touchcancel', this._onTouchEnd);
    }

    teardownPinchZoom() {
        if (!this.hasScrollTarget || !this._onTouchStart) return;
        const el = this.scrollTarget;
        el.removeEventListener('touchstart', this._onTouchStart);
        el.removeEventListener('touchmove', this._onTouchMove);
        el.removeEventListener('touchend', this._onTouchEnd);
        el.removeEventListener('touchcancel', this._onTouchEnd);
    }

    pinchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.hypot(dx, dy);
    }

    onTouchStart(e) {
        if (e.touches.length !== 2) return;
        e.preventDefault();
        this._pinch = { startDist: this.pinchDistance(e.touches), baseZoom: this.zoom, target: this.zoom };
    }

    onTouchMove(e) {
        if (!this._pinch || e.touches.length !== 2) return;
        e.preventDefault();
        const ratio = this.pinchDistance(e.touches) / this._pinch.startDist;
        const target = Math.min(3, Math.max(0.5, this._pinch.baseZoom * ratio));
        this._pinch.target = target;
        // Live preview relative to the already-rendered zoom (pivot at centre).
        this.containerTarget.style.transformOrigin = '50% 50%';
        this.containerTarget.style.transform = 'scale(' + (target / this._pinch.baseZoom) + ')';
    }

    onTouchEnd() {
        if (!this._pinch) return;
        const target = this._pinch.target;
        this._pinch = null;
        this.containerTarget.style.transform = '';
        this.containerTarget.style.transformOrigin = '';
        if (Math.abs(target - this.zoom) > 0.01) this.applyZoom(target);
    }

    applyZoom(z) {
        this.zoom = z;
        if (!this.osmd) return;
        this.osmd.Zoom = z;
        this.osmd.render();
        if (this.playing) this.osmd.cursor.show();
    }

    // ---- Landscape (rotate the scrolling score to full-screen) ----------

    get isLandscape() {
        return !!this.landscape;
    }

    toggleLandscape() {
        this.landscape ? this.exitLandscape() : this.enterLandscape();
    }

    enterLandscape() {
        if (!this.hasViewerTarget) return;
        this.landscape = true;
        // Scale the score up so the single horizontal staff fills the rotated
        // view. Measure and re-render *before* rotating, so getBoundingClientRect
        // still reports the un-rotated height. Remember the prior zoom to restore.
        this.preLandscapeZoom = this.zoom;
        this.fitLandscapeZoom();
        this.viewerTarget.classList.add('osmd-landscape');
        document.body.classList.add('osmd-landscape-active');
        // Enter/exit button visibility is driven by the .osmd-landscape class (see app.css).
    }

    exitLandscape() {
        this.landscape = false;
        if (this.hasViewerTarget) this.viewerTarget.classList.remove('osmd-landscape');
        document.body.classList.remove('osmd-landscape-active');
        // Restore the zoom we had before entering landscape.
        if (this.preLandscapeZoom != null && Math.abs(this.preLandscapeZoom - this.zoom) > 0.01) {
            this.applyZoom(this.preLandscapeZoom);
        }
        this.preLandscapeZoom = null;
    }

    // Pick a zoom so the staff's rendered height roughly matches the viewport
    // width — which becomes the on-screen cross-axis once the view is rotated
    // 90° — so the score fills the screen instead of sitting in a thin strip.
    fitLandscapeZoom() {
        if (!this.osmd) return;
        const svg = this.containerTarget.querySelector('svg');
        const current = svg ? svg.getBoundingClientRect().height : 0;
        if (!current) return;
        const target = window.innerWidth * 0.85; // leave room for the controls bar + margin
        const z = Math.min(4, Math.max(0.5, this.zoom * (target / current)));
        if (Math.abs(z - this.zoom) > 0.05) this.applyZoom(z);
    }

    // Pick a zoom so the whole stack of staves (all voices) fits in the scroll
    // viewport's height — used by Tutti, where every part should be visible at
    // once. In landscape the SVG is rotated 90°, so its rendered height is the
    // screen bounding box's *width* (the axes swap).
    fitAllStavesZoom() {
        if (!this.osmd) return;
        const scrollEl = this.scrollContainer();
        const svg = this.containerTarget.querySelector('svg');
        if (!scrollEl || !svg) return;
        const rect = svg.getBoundingClientRect();
        const rendered = this.isLandscape ? rect.width : rect.height; // the score's own height
        const avail = scrollEl.clientHeight; // local height, unaffected by the rotation
        if (!rendered || !avail) return;
        const z = Math.min(4, Math.max(0.25, this.zoom * (avail * 0.96 / rendered)));
        if (Math.abs(z - this.zoom) > 0.02) this.applyZoom(z);
    }

    setStatus(msg) {
        if (!this.hasStatusTarget) return;
        this.statusTarget.textContent = msg;
        this.statusTarget.classList.toggle('d-none', !msg);
    }

    // ---- Playback -------------------------------------------------------

    enablePlayback() {
        if (this.hasPlayBtnTarget) this.playBtnTarget.disabled = false;
        if (this.hasStopBtnTarget) this.stopBtnTarget.disabled = false;
    }

    // True once an MP3 (a voice or the Tutti mix) is selected.
    get mp3Mode() {
        return !!this.currentAudioUrl;
    }

    async togglePlay() {
        if (!this.currentAudioUrl) return; // nothing selected and no Tutti mix
        await this.toggleMp3();
    }

    stop() {
        if (!this.audioEl) return;
        this.audioEl.pause();
        this.audioEl.currentTime = 0;
        this.onMp3Stopped();
    }

    // ---- MP3 (per-voice recording) playback -----------------------------

    async toggleMp3() {
        if (this.audioEl.paused) {
            this.osmd.cursor.show();
            try { await this.audioEl.play(); } catch (e) { return; }
            this.playing = true;
            this.showPlayhead(true);
            this.startAutoScroll();
        } else {
            this.audioEl.pause();
            this.playing = false;
            this.stopAutoScroll();
        }
        this.setPlayLabel();
    }

    onAudioEnded() {
        this.audioEl.currentTime = 0;
        this.onMp3Stopped();
    }

    onMp3Stopped() {
        this.playing = false;
        this.stopAutoScroll();
        this.showPlayhead(false);
        this.osmd.cursor.reset();
        this.setPlayLabel();
    }

    // Point the audio element at the given recording URL (or clear it).
    selectAudio(url) {
        url = url || null;
        if (url === this.currentAudioUrl) return;
        // Switching source: stop whatever is currently playing.
        if (this.playing) this.stop();
        this.currentAudioUrl = url;
        if (this.audioEl) {
            this.audioEl.src = url || '';
            this.audioEl.playbackRate = this.playbackRate; // re-apply across sources
        }
    }

    // Change playback speed (the cursor follows automatically — it's driven by
    // the audio's currentTime, which advances at the playback rate).
    changeSpeed() {
        this.playbackRate = parseFloat(this.speedTarget.value) || 1;
        if (this.audioEl) this.audioEl.playbackRate = this.playbackRate;
        this.updateSpeedLabel();
    }

    // Set the playback tempo directly from the editable BPM field. We can't
    // re-render the audio, so a new BPM maps to a playback rate (newBpm / score
    // BPM), clamped to the same 0.5–1.5× range as the slider.
    changeBpm() {
        if (!this.hasBpmTarget || !this.bpm) return;
        const entered = parseFloat(this.bpmTarget.value);
        if (!entered || entered <= 0) { this.updateSpeedLabel(); return; }
        const rate = Math.min(1.5, Math.max(0.5, entered / this.bpm));
        this.playbackRate = rate;
        if (this.audioEl) this.audioEl.playbackRate = rate;
        if (this.hasSpeedTarget) this.speedTarget.value = rate;
        // Reflect any clamping back into the field (e.g. 999 → max).
        this.bpmTarget.value = Math.round(this.bpm * rate);
        this.updateSpeedLabel();
    }

    // Show the current multiplier, and mirror the resulting tempo into the BPM
    // field (unless the user is mid-edit in it).
    updateSpeedLabel() {
        if (this.hasSpeedLabelTarget) {
            this.speedLabelTarget.textContent = `${this.playbackRate.toFixed(2).replace('.', ',')}×`;
        }
        if (this.hasBpmTarget && document.activeElement !== this.bpmTarget) {
            this.bpmTarget.value = Math.round((this.bpm || 0) * this.playbackRate);
        }
    }

    audioForStaff(staffIndex) {
        if (staffIndex === null) return null;
        const code = this.voiceCode(staffIndex);
        let url = this.audioByVoiceValue[code];
        // "M" is a combined Männer (men's) recording covering both Tenor and
        // Bass; use it for those staves when no dedicated T/B track exists.
        if (!url && (code === 'T' || code === 'B')) url = this.audioByVoiceValue['M'];
        // Otherwise fall back to the Tutti/combined mix for songs that have no
        // split per-voice tracks (e.g. a canon), so selecting a single staff
        // still plays (just highlighting that part).
        return url || this.tuttiUrlValue || null;
    }

    // Map a staff to its voice code, preferring the staff label's initial
    // (Sopran/Alt/Tenor/Bass/Männer → S/A/T/B/M), falling back to stacking order.
    voiceCode(staffIndex) {
        const initial = (this.staffLabels()[staffIndex] || '').trim()[0]?.toUpperCase();
        if (['S', 'A', 'T', 'B', 'M'].includes(initial)) return initial;
        return ['S', 'A', 'T', 'B'][staffIndex];
    }

    // Score tempo in BPM (quarter notes / minute). The MP3s are rendered from
    // the MusicXML at this tempo, so the cursor is driven by elapsed audio time
    // × tempo — the same scheduling the synth used. (Driving off audioEl.duration
    // instead is unreliable: browsers misreport MP3 duration, which made the
    // cursor lag.)
    readTempo() {
        const sheet = this.osmd?.Sheet;
        const bpm = sheet && sheet.HasBPMInfo ? sheet.DefaultStartTempoInBpm : null;
        this.bpm = bpm && bpm > 0 ? bpm : 120;
        // Bound the BPM field to the same 0.5–1.5× window as the speed slider.
        if (this.hasBpmTarget) {
            this.bpmTarget.min = Math.round(this.bpm * 0.5);
            this.bpmTarget.max = Math.round(this.bpm * 1.5);
        }
    }

    // Advance/rewind the cursor to match the audio position. A whole note lasts
    // 240/bpm seconds, so the playback position (whole notes) at time t is
    // t·bpm/240. We compare against the ENROLLED timestamp — the unrolled
    // playback position that follows repeats/voltas — not the source timestamp,
    // which jumps backwards at a repeat and would desync the cursor.
    syncCursorToAudio() {
        const a = this.audioEl;
        if (!a || !this.bpm) return;
        const target = a.currentTime * this.bpm / 240;
        const cur = this.osmd.cursor;
        if (!cur.Iterator.EndReached && cur.Iterator.CurrentEnrolledTimestamp.RealValue > target + 1e-6) {
            cur.reset(); // playback moved backwards (e.g. user seek)
        }
        while (!cur.Iterator.EndReached && cur.Iterator.CurrentEnrolledTimestamp.RealValue < target - 1e-9) {
            cur.next();
        }
    }

    showPlayhead(visible) {
        if (this.hasPlayheadTarget) this.playheadTarget.classList.toggle('d-none', !visible);
    }

    // ---- Auto-scroll (keep the playback cursor centred horizontally) ----

    // Nearest horizontally-scrollable ancestor of the rendered score.
    scrollContainer() {
        if (this._scrollEl) return this._scrollEl;
        let el = this.containerTarget.parentElement;
        while (el && el !== document.body) {
            const ox = getComputedStyle(el).overflowX;
            if (ox === 'auto' || ox === 'scroll') { this._scrollEl = el; break; }
            el = el.parentElement;
        }
        this._scrollEl = this._scrollEl || this.containerTarget.parentElement;
        return this._scrollEl;
    }

    startAutoScroll() {
        const scrollEl = this.scrollContainer();
        if (!scrollEl || this.autoScrollRaf) return;
        const step = () => {
            if (!this.playing) { this.autoScrollRaf = null; return; }
            if (this.mp3Mode) this.syncCursorToAudio();
            this.followByThreshold(scrollEl);
            this.updatePlayhead(scrollEl);
            this.autoScrollRaf = requestAnimationFrame(step);
        };
        this.autoScrollRaf = requestAnimationFrame(step);
    }

    stopAutoScroll() {
        if (this.autoScrollRaf) {
            cancelAnimationFrame(this.autoScrollRaf);
            this.autoScrollRaf = null;
        }
    }

    // Let the staff stay still (readable) while the cursor moves right across
    // it. Re-position the scroll so the cursor sits at 25% whenever it either
    // passes 75% (normal forward drift) or jumps left of 10% (a repeat/volta
    // jumping back to an earlier measure) — otherwise the repeated bars stay
    // off-screen and the cursor pins to the left edge.
    followByThreshold(scrollEl) {
        const cursorEl = this.osmd?.cursor?.cursorElement;
        if (!cursorEl || cursorEl.offsetParent === null) return;
        const cr = cursorEl.getBoundingClientRect();
        const sr = scrollEl.getBoundingClientRect();
        // The view is rotated 90° in landscape, so the score's horizontal scroll
        // axis runs along the screen's Y. Lengths are preserved by the rotation,
        // so the same clientWidth thresholds apply — only the measured axis swaps.
        const cursorX = this.isLandscape ? (cr.top - sr.top) : (cr.left - sr.left);
        if (cursorX > scrollEl.clientWidth * 0.1 && cursorX <= scrollEl.clientWidth * 0.75) return;
        const target = scrollEl.scrollLeft + (cursorX - scrollEl.clientWidth * 0.25);
        const max = scrollEl.scrollWidth - scrollEl.clientWidth;
        scrollEl.scrollLeft = Math.max(0, Math.min(target, max));
    }

    // Draw the highlight box (full viewport height) over the current note, or
    // over the whole current measure when measureHighlight is enabled. Falls
    // back to the note width if measure geometry isn't available.
    updatePlayhead(scrollEl) {
        if (!this.hasPlayheadTarget) return;
        const cursorEl = this.osmd?.cursor?.cursorElement;
        if (!cursorEl || cursorEl.offsetParent === null) return;
        const viewRect = scrollEl.getBoundingClientRect();
        const ph = this.playheadTarget;

        const measureBand = this.measureHighlightValue ? this.measureXBand(scrollEl, viewRect) : null;
        const band = measureBand || (() => {
            const r = cursorEl.getBoundingClientRect();
            // In landscape the scroll axis is the screen's Y (see followByThreshold);
            // the playhead lives inside the rotated wrapper, so positioning it with
            // these local-axis values keeps it aligned over the cursor.
            return this.isLandscape
                ? { left: r.top - viewRect.top, width: Math.max(r.height, 8) }
                : { left: r.left - viewRect.left, width: Math.max(r.width, 8) };
        })();
        // Clamp to the visible viewport.
        const left = Math.max(0, band.left);
        const right = Math.min(scrollEl.clientWidth, band.left + band.width);
        ph.style.left = left + 'px';
        ph.style.width = Math.max(0, right - left) + 'px';
        ph.style.top = '0px';
        ph.style.height = '100%';
    }

    // The current measure's horizontal extent (left, width) in viewport pixels,
    // from its graphical PositionAndShape. CurrentMeasureIndex is the written
    // measure (correct even mid-repeat, since the same bars are replayed).
    measureXBand(scrollEl, viewRect) {
        const mi = this.osmd?.cursor?.Iterator?.CurrentMeasureIndex;
        const svg = this.containerTarget.querySelector('svg');
        const page = this.osmd?.GraphicSheet?.MusicPages?.[0];
        const gm = this.osmd?.GraphicSheet?.MeasureList?.[mi]?.[0];
        const pageW = page?.PositionAndShape?.Size?.width;
        if (typeof mi !== 'number' || !svg || !gm || !pageW) return null;
        const svgRect = svg.getBoundingClientRect();
        const k = svgRect.width / pageW;
        const ps = gm.PositionAndShape;
        return {
            left: (svgRect.left - viewRect.left) + ps.AbsolutePosition.x * k,
            width: ps.Size.width * k,
        };
    }

    // A staff's vertical range in rendered SVG pixels (relative to the SVG top),
    // taken from its StaffLine. The unit→pixel factor is derived from the SVG's
    // own rendered height vs the page height in OSMD units, so it stays correct
    // regardless of zoom/autoResize (a fixed unitInPixels would drift per staff).
    staffSvgRange(staffIndex, svgRect) {
        const page = this.osmd?.GraphicSheet?.MusicPages?.[0];
        const staffLine = page?.MusicSystems?.[0]?.StaffLines?.[staffIndex];
        const pageH = page?.PositionAndShape?.Size?.height;
        if (!staffLine || !pageH) return null;
        const k = svgRect.height / pageH; // px per OSMD unit
        const ps = staffLine.PositionAndShape;
        return { y: ps.AbsolutePosition.y * k, h: ps.Size.height * k };
    }

    setPlayLabel() {
        if (!this.hasPlayBtnTarget) return;
        this.playBtnTarget.innerHTML = this.playing
            ? '<i class="bi bi-pause-fill"></i> Pause'
            : '<i class="bi bi-play-fill"></i> Abspielen';
    }

    // ---- Staff colouring ------------------------------------------------

    staffCount() {
        const ml = this.osmd?.GraphicSheet?.MeasureList;
        return ml && ml.length ? ml[0].length : 0;
    }

    // Map each staff index to a human label using the instrument/part names.
    staffLabels() {
        const labels = [];
        const instruments = this.osmd?.Sheet?.Instruments || [];
        instruments.forEach((inst) => {
            const name = (inst.NameLabel && inst.NameLabel.text) || inst.Name || 'Stimme';
            const n = inst.Staves ? inst.Staves.length : 1;
            for (let s = 0; s < n; s++) {
                labels.push(n > 1 ? `${name} ${s + 1}` : name);
            }
        });
        const count = this.staffCount();
        for (let i = labels.length; i < count; i++) labels.push(`Notenzeile ${i + 1}`);
        return labels.slice(0, count);
    }

    buildStaffButtons() {
        if (!this.hasControlsTarget) return;
        this.controlsTarget.innerHTML = '';

        this.staffLabels().forEach((label, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary';
            btn.textContent = label;
            btn.style.borderColor = this.colors[idx % this.colors.length];
            btn.addEventListener('click', () => this.toggleStaff(idx, btn));
            this.controlsTarget.appendChild(btn);
        });

        // Tutti = the full-ensemble mix; it has no matching score staff, so it
        // only swaps the audio (no colouring, full-height playhead).
        if (this.tuttiUrlValue) {
            const tutti = document.createElement('button');
            tutti.type = 'button';
            tutti.className = 'btn btn-sm btn-outline-dark';
            tutti.textContent = 'Tutti';
            tutti.addEventListener('click', () => this.toggleTutti(tutti));
            this.controlsTarget.appendChild(tutti);
            this.tuttiBtn = tutti;
        }

        const reset = document.createElement('button');
        reset.type = 'button';
        reset.className = 'btn btn-sm btn-link';
        reset.textContent = 'Zurücksetzen';
        reset.addEventListener('click', () => this.resetColors());
        this.controlsTarget.appendChild(reset);
    }

    eachNoteOfStaff(staffIndex, cb) {
        const ml = this.osmd.GraphicSheet.MeasureList;
        for (let m = 0; m < ml.length; m++) {
            const measure = ml[m][staffIndex];
            if (!measure) continue;
            for (const se of measure.staffEntries) {
                for (const gve of se.graphicalVoiceEntries) {
                    for (const note of gve.notes) {
                        if (note.sourceNote) cb(note.sourceNote, gve);
                    }
                }
            }
        }
    }

    paintStaff(staffIndex, color) {
        this.eachNoteOfStaff(staffIndex, (note, gve) => {
            note.NoteheadColor = color;
            if (gve.parentVoiceEntry) gve.parentVoiceEntry.StemColor = color;
        });
    }

    resetColorsInternal() {
        const count = this.staffCount();
        for (let s = 0; s < count; s++) this.paintStaff(s, '#000000');
    }

    toggleStaff(staffIndex, btn) {
        const turningOn = this.activeStaff !== staffIndex;
        this.resetColorsInternal();

        if (turningOn) {
            this.paintStaff(staffIndex, this.colors[staffIndex % this.colors.length]);
            this.activeStaff = staffIndex;
        } else {
            this.activeStaff = null;
        }
        this.tuttiActive = false;
        this.selectAudio(this.audioForStaff(this.activeStaff));

        // A single part renders at the default zoom. The Tutti view zooms out to
        // fit every staff at once; restore 1× when focusing one part so it isn't
        // shown shrunken. applyZoom() re-renders (also committing the colouring).
        if (turningOn && Math.abs(this.zoom - 1) > 0.01) {
            this.applyZoom(1);
        } else {
            this.osmd.render();
        }
        this.controlsTarget.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
        if (turningOn) {
            btn.classList.add('active');
            this.scrollStaffIntoCenter(staffIndex);
        }
    }

    // No Tutti mix available: auto-select the first staff that has its own
    // recording, so the play button works on open instead of doing nothing.
    selectDefaultVoice() {
        const buttons = this.controlsTarget.querySelectorAll('button');
        const count = this.staffCount();
        for (let i = 0; i < count; i++) {
            if (this.audioForStaff(i) && buttons[i]) {
                this.toggleStaff(i, buttons[i]);
                return;
            }
        }
    }

    // Tutti: play the full-ensemble mix with no staff colouring/centring.
    toggleTutti(btn) {
        const turningOn = !this.tuttiActive;
        const hadColour = this.activeStaff !== null;
        this.resetColorsInternal();
        this.activeStaff = null;
        this.tuttiActive = turningOn;
        if (hadColour) this.osmd.render(); // clear previous staff colouring
        this.selectAudio(turningOn ? this.tuttiUrlValue : null);
        this.controlsTarget.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
        if (turningOn) {
            btn.classList.add('active');
            // Zoom so every voice is visible at once, then show the whole score.
            this.fitAllStavesZoom();
            this.scrollContainer()?.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    // Vertically centre the given staff in the scroll viewport. All parts are
    // stacked in one horizontal staff line, so a staff's Y is constant across
    // the piece; we read it from the staff's StaffLine (see staffSvgRange).
    scrollStaffIntoCenter(staffIndex) {
        const scrollEl = this.scrollContainer();
        const svg = this.containerTarget.querySelector('svg');
        if (!scrollEl || !svg) return;
        const svgRect = svg.getBoundingClientRect();
        const r = this.staffSvgRange(staffIndex, svgRect);
        if (!r) return;

        const viewRect = scrollEl.getBoundingClientRect();
        const centerContentY = (svgRect.top - viewRect.top) + (r.y + r.h / 2) + scrollEl.scrollTop;
        const target = centerContentY - scrollEl.clientHeight / 2;
        const max = scrollEl.scrollHeight - scrollEl.clientHeight;
        scrollEl.scrollTo({ top: Math.max(0, Math.min(target, max)), behavior: 'smooth' });
    }

    resetColors() {
        this.resetColorsInternal();
        this.activeStaff = null;
        this.tuttiActive = false;
        this.selectAudio(null);
        this.osmd.render();
        this.controlsTarget.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
    }
}
