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
        // How long a fermata holds its note, as a multiple of the note's notated
        // value (2 = held twice as long — the common notation-software default).
        // The recordings render fermatas, so the cursor must dwell to match; tune
        // per song with data-osmd-fermata-stretch-value if a hold sounds off.
        fermataStretch: { type: Number, default: 2 },
    };

    async connect() {
        this.colors = ['#d6336c', '#1c7ed6', '#2f9e44', '#e8590c', '#7048e8', '#0ca678'];
        this.activeStaff = null;
        this.tuttiActive = false;
        this.playing = false;
        // Immersive view state: `landscape` = full-screen overlay active;
        // `rotated` = the 90° CSS turn is applied (portrait phone only).
        this.landscape = false;
        this.rotated = false;
        // Initialise zoom up front: the default Tutti selection below runs
        // fitAllStavesZoom(), which reads this.zoom.
        this.zoom = 1;

        try {
            // The vendored OSMD bundle exposes the namespace as a single default export.
            const OSMD = (await import('opensheetmusicdisplay')).default;
            this.OSMDns = OSMD; // kept for enum lookups (e.g. ArticulationEnum)
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

            // On phones, open straight into the rotated full-screen view: the
            // score is wide and landscape uses the screen far better, and the
            // controls bar then rides as a fixed strip (always visible). Entering
            // before the default selection below lets its fit-zoom use the
            // landscape geometry. Desktop/tablet stay in portrait.
            if (this.isMobilePhone) this.enterLandscape();

            // Playback uses the real per-voice MP3 recordings (and the Tutti
            // full mix). The selected voice's recording is played and the cursor
            // is driven from its playback time (see syncCursorToAudio).
            this.playbackRate = 1;
            this.audioEl = new Audio();
            this.audioEl.preload = 'auto';
            this.audioEl.preservesPitch = true; // keep pitch when changing speed
            this.audioEl.addEventListener('ended', () => this.onAudioEnded());
            // Each track's duration calibrates the real tempo (the notated score
            // tempo is often a placeholder that doesn't match the recording).
            this.audioEl.addEventListener('loadedmetadata', () => this.calibrateTempoFromAudio());
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

    // ---- Immersive full-screen view (follows the device's real orientation) --

    // True only while the score is actually CSS-rotated 90° (portrait phone). The
    // playhead/auto-scroll math swaps its measured axis on this, so it must NOT be
    // set for the un-rotated natural-landscape view (a phone already held sideways).
    get isLandscape() {
        return !!this.rotated;
    }

    // The device's real orientation. Used to pick the immersive sub-mode: a
    // portrait phone gets the 90° CSS rotation (so the wide score fills the
    // screen); a phone already held in landscape is shown upright. matchMedia is
    // the most compatible signal across iOS Safari / Android Chrome / desktop.
    get isPortrait() {
        const mq = window.matchMedia('(orientation: portrait)');
        return typeof mq.matches === 'boolean' ? mq.matches : window.innerHeight >= window.innerWidth;
    }

    // A phone (not a tablet/desktop): touch input and a small short side. Using
    // the smaller viewport dimension separates phones (~<500px short side) from
    // tablets (~768px) regardless of how the device is currently held.
    get isMobilePhone() {
        return window.matchMedia('(pointer: coarse)').matches
            && Math.min(window.innerWidth, window.innerHeight) < 600;
    }

    toggleLandscape() {
        this.landscape ? this.exitLandscape() : this.enterLandscape();
    }

    // Enter the immersive full-screen view. The presentation then follows the
    // device's real orientation (see applyOrientationMode) and re-evaluates
    // whenever the device is turned.
    enterLandscape() {
        if (!this.hasViewerTarget) return;
        this.landscape = true;
        // Remember the prior zoom to restore on exit.
        this.preLandscapeZoom = this.zoom;
        this.viewerTarget.classList.add('osmd-immersive');
        document.body.classList.add('osmd-landscape-active');
        this.applyOrientationMode();
        this.listenOrientation();
        // Enter/exit button visibility is driven by the .osmd-immersive class (see app.css).
    }

    // Pick the immersive sub-mode from the device's *physical* orientation:
    //   • portrait phone  → rotate the wide score 90° so it fills the screen
    //   • landscape phone → show it upright (natural fill, no CSS rotation)
    // Called on entry and on every orientation change, so turning the device
    // swaps modes and refits. The zoom is fitted while the rotation is removed, so
    // getBoundingClientRect reports the true un-rotated staff height first.
    applyOrientationMode() {
        if (!this.landscape || !this.hasViewerTarget) return;
        const rotate = this.isPortrait;
        this.viewerTarget.classList.remove('osmd-rotated');
        this.rotated = false;
        if (rotate) {
            this.fitLandscapeZoom();
            this.viewerTarget.classList.add('osmd-rotated');
            this.rotated = true;
        } else {
            this.fitNaturalZoom();
        }
    }

    exitLandscape() {
        this.landscape = false;
        this.rotated = false;
        this.unlistenOrientation();
        if (this.hasViewerTarget) this.viewerTarget.classList.remove('osmd-immersive', 'osmd-rotated');
        document.body.classList.remove('osmd-landscape-active');
        // Restore the zoom we had before entering the immersive view.
        if (this.preLandscapeZoom != null && Math.abs(this.preLandscapeZoom - this.zoom) > 0.01) {
            this.applyZoom(this.preLandscapeZoom);
        }
        this.preLandscapeZoom = null;
    }

    // React to the device being physically rotated while immersive: re-pick the
    // sub-mode and refit. Listening to matchMedia('(orientation: …)') change is the
    // cross-browser-reliable signal; orientationchange is kept as a fallback for
    // engines that don't fire the media-query change. Debounced because the
    // viewport dimensions settle a beat after the event fires.
    listenOrientation() {
        if (this._orientMq) return;
        this._orientMq = window.matchMedia('(orientation: portrait)');
        this._orientHandler = () => {
            clearTimeout(this._orientTimer);
            this._orientTimer = setTimeout(() => this.applyOrientationMode(), 150);
        };
        if (this._orientMq.addEventListener) this._orientMq.addEventListener('change', this._orientHandler);
        else if (this._orientMq.addListener) this._orientMq.addListener(this._orientHandler); // older Safari
        window.addEventListener('orientationchange', this._orientHandler);
    }

    unlistenOrientation() {
        clearTimeout(this._orientTimer);
        if (!this._orientMq) return;
        if (this._orientMq.removeEventListener) this._orientMq.removeEventListener('change', this._orientHandler);
        else if (this._orientMq.removeListener) this._orientMq.removeListener(this._orientHandler);
        window.removeEventListener('orientationchange', this._orientHandler);
        this._orientMq = null;
        this._orientHandler = null;
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

    // Natural landscape (phone already held sideways, no CSS rotation): fit the
    // staff height into the full-screen viewport height, leaving the controls
    // strip its room. No axis swap here — the score is drawn the normal way up.
    fitNaturalZoom() {
        if (!this.osmd) return;
        const svg = this.containerTarget.querySelector('svg');
        const current = svg ? svg.getBoundingClientRect().height : 0;
        if (!current) return;
        const target = window.innerHeight * 0.72; // fill most of the height below the controls bar
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
        if (!url) {
            // Divisi staff (e.g. "S2") with only an undivided "S" recording: fall
            // back to the base voice. "M" is a combined Männer (men's) recording
            // covering Tenor and Bass when no dedicated T/B track exists.
            const base = code[0];
            url = this.audioByVoiceValue[base];
            if (!url && (base === 'T' || base === 'B')) url = this.audioByVoiceValue['M'];
        }
        // Otherwise fall back to the Tutti/combined mix for songs that have no
        // split per-voice tracks (e.g. a canon), so selecting a single staff
        // still plays (just highlighting that part).
        return url || this.tuttiUrlValue || null;
    }

    // Map a staff to its voice code, preferring the staff label's initial
    // (Sopran/Alt/Tenor/Bass/Männer → S/A/T/B/M) plus any divisi number on the
    // label (Sopran 2 → S2, matching a "2 - S2 - …" recording), falling back to
    // stacking order.
    voiceCode(staffIndex) {
        const label   = (this.staffLabels()[staffIndex] || '').trim();
        const initial = label[0]?.toUpperCase();
        if (['S', 'A', 'T', 'B', 'M'].includes(initial)) {
            const div = label.match(/(\d+)\s*$/);
            return div ? initial + div[1] : initial;
        }
        return ['S', 'A', 'T', 'B'][staffIndex];
    }

    // Establish the playback tempo (BPM = quarter notes / minute), used to drive
    // the cursor from elapsed audio time. The score's notated <sound tempo> is
    // often just a placeholder (e.g. 120) that does NOT match the real recording,
    // so we treat it only as a starting estimate and calibrate against the actual
    // audio duration once a track loads (see calibrateTempoFromAudio).
    readTempo() {
        const sheet = this.osmd?.Sheet;
        const bpm = sheet && sheet.HasBPMInfo ? sheet.DefaultStartTempoInBpm : null;
        this.bpm = bpm && bpm > 0 ? bpm : 120;
        // Notated source length in whole notes (no repeats expanded).
        this.scoreWholeNotes = sheet?.SheetEndTimestamp?.RealValue || 0;
        this.hasRepeats = (sheet?.Repetitions?.length || 0) > 0;
        // Actual played length in whole notes with repeats/voltas unrolled — what
        // the recording really contains. This (not the source length) is the basis
        // for tempo calibration, so a repeated piece calibrates correctly instead
        // of running at the notated placeholder tempo.
        this.enrolledWholeNotes = this.computeEnrolledLength();
        this.scanFermatas();
        this.applyBpmBounds();
    }

    // Walk a cursor to the end to measure the unrolled playback length in whole
    // notes (the last enrolled timestamp). The cursor iterator follows repeats and
    // voltas, so this equals the recording's musical length even for repeated
    // pieces. Runs once at load, before playback resets the cursor.
    computeEnrolledLength() {
        const cur = this.osmd?.cursor;
        if (!cur) return this.scoreWholeNotes;
        try {
            cur.reset();
            let last = 0, steps = 0;
            while (!cur.Iterator.EndReached && steps < 100000) {
                last = cur.Iterator.CurrentEnrolledTimestamp.RealValue;
                cur.next();
                steps++;
            }
            cur.reset();
            return last || this.scoreWholeNotes;
        } catch (e) {
            return this.scoreWholeNotes;
        }
    }

    // The ArticulationEnum values that mark a fermata, read from the OSMD
    // namespace when exposed, else the OSMD 2.0.0 literals.
    fermataEnums() {
        const A = this.OSMDns?.ArticulationEnum;
        if (A && typeof A.fermata === 'number') {
            const out = [A.fermata];
            if (typeof A.invertedfermata === 'number') out.push(A.invertedfermata);
            return out;
        }
        return [10, 11]; // ArticulationEnum.fermata / invertedfermata
    }

    // Scan the source score for fermata holds. A fermata makes the performer —
    // and the rendered recording — sustain a note well past its notated value,
    // which a constant-tempo cursor races straight through. We record each
    // fermata's musical start (whole notes) and the held note's notated length so
    // the time map (effectivePos) can stretch that span. Fermatas sounding on the
    // same beat in several voices collapse to one (a fermata holds the whole
    // ensemble). Skipped when the piece repeats: source≠enrolled positions then,
    // and fermata+repeat is rare — fall back to the plain constant-tempo map.
    scanFermatas() {
        this.fermatas = [];
        this.fermataStretch = this.fermataStretchValue > 1 ? this.fermataStretchValue : 2;
        // Base the effective (time-map) length on the unrolled playback length, so
        // repeated pieces calibrate against what the recording actually plays.
        this.effectiveWholeNotes = this.enrolledWholeNotes || this.scoreWholeNotes;
        if (this.hasRepeats) return;

        const measures = this.osmd?.Sheet?.SourceMeasures || [];
        const ferm = this.fermataEnums();
        const byStart = new Map(); // start(whole notes) → longest held note length there
        for (const m of measures) {
            const mAbs = m.AbsoluteTimestamp?.RealValue || 0;
            for (const c of (m.VerticalSourceStaffEntryContainers || [])) {
                for (const se of (c.StaffEntries || [])) {
                    if (!se) continue;
                    for (const ve of (se.VoiceEntries || [])) {
                        if (!(ve.Articulations || []).some((a) => ferm.includes(a.articulationEnum))) continue;
                        const start = mAbs + (ve.Timestamp?.RealValue || 0);
                        const len = Math.max(0, ...(ve.Notes || []).map((n) => n.Length?.RealValue || 0));
                        if (len <= 0) continue;
                        if (!(byStart.get(start) >= len)) byStart.set(start, len);
                    }
                }
            }
        }
        this.fermatas = [...byStart.entries()]
            .map(([start, len]) => ({ start, len }))
            .sort((a, b) => a.start - b.start);
        const extra = this.fermatas.reduce((s, f) => s + f.len * (this.fermataStretch - 1), 0);
        this.effectiveWholeNotes = (this.enrolledWholeNotes || this.scoreWholeNotes) + extra;
    }

    // Map a real musical position (whole notes) to "effective" whole notes, where
    // each fermata span [start, start+len] is stretched by fermataStretch. The
    // cursor↔audio time map runs in this effective space, so wall-clock time spent
    // crossing a fermata matches the recording and the cursor stays in sync.
    effectivePos(p) {
        const f = this.fermatas;
        if (!f || !f.length) return p;
        const k = this.fermataStretch - 1;
        let e = p;
        for (const fm of f) {
            if (p <= fm.start) break; // sorted by start
            e += Math.min(p - fm.start, fm.len) * k;
        }
        return e;
    }

    // Bound the BPM field to the same 0.5–1.5× window as the speed slider.
    applyBpmBounds() {
        if (this.hasBpmTarget) {
            this.bpmTarget.min = Math.round(this.bpm * 0.5);
            this.bpmTarget.max = Math.round(this.bpm * 1.5);
        }
    }

    // Derive the real tempo from the loaded track's duration vs the *played*
    // length (bpm = wholeNotes·240/seconds), so the cursor stays in sync even when
    // the score's notated tempo is a placeholder that doesn't match the recording.
    // effectiveWholeNotes is the unrolled (repeats/voltas expanded) length plus any
    // fermata stretch — the same space syncCursorToAudio measures the cursor in —
    // so this now calibrates repeated pieces too, which previously ran at the
    // notated placeholder tempo and drifted badly.
    calibrateTempoFromAudio() {
        const dur = this.audioEl?.duration;
        const len = this.effectiveWholeNotes || this.enrolledWholeNotes || this.scoreWholeNotes;
        if (!len || !dur || !isFinite(dur)) return;
        const bpm = len * 240 / dur;
        if (!(bpm > 0) || !isFinite(bpm)) return;
        this.bpm = bpm;
        this.applyBpmBounds();
        this.updateSpeedLabel();
    }

    // Advance/rewind the cursor to match the audio position. A whole note lasts
    // 240/bpm seconds, so the playback position (whole notes) at time t is
    // t·bpm/240. We compare against the ENROLLED timestamp — the unrolled
    // playback position that follows repeats/voltas — not the source timestamp,
    // which jumps backwards at a repeat and would desync the cursor.
    syncCursorToAudio() {
        const a = this.audioEl;
        if (!a || !this.bpm) return;
        // Target is in effective (fermata-stretched) whole notes, and we compare
        // the cursor's position mapped into the same space, so the cursor dwells
        // through a held fermata instead of running ahead of the recording.
        const target = a.currentTime * this.bpm / 240;
        const cur = this.osmd.cursor;
        const pos = () => this.effectivePos(cur.Iterator.CurrentEnrolledTimestamp.RealValue);
        if (!cur.Iterator.EndReached && pos() > target + 1e-6) {
            cur.reset(); // playback moved backwards (e.g. user seek)
        }
        while (!cur.Iterator.EndReached && pos() < target - 1e-9) {
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

    // A staff's centre and height as fractions of the page height, from its
    // StaffLine's PositionAndShape. Pure OSMD units, so it's orientation-
    // independent — the same numbers in portrait and the rotated landscape view.
    staffBand(staffIndex) {
        const page = this.osmd?.GraphicSheet?.MusicPages?.[0];
        const staffLine = page?.MusicSystems?.[0]?.StaffLines?.[staffIndex];
        const pageH = page?.PositionAndShape?.Size?.height;
        if (!staffLine || !pageH) return null;
        const ps = staffLine.PositionAndShape;
        return {
            center: (ps.AbsolutePosition.y + ps.Size.height / 2) / pageH,
            height: ps.Size.height / pageH,
        };
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

        // A focused part renders at the default 100% zoom (the Tutti view zooms
        // out to fit every staff at once; restore 1× when focusing one part), then
        // gets vertically centred below. applyZoom() re-renders — also committing
        // the colouring.
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

    // Vertically centre the given staff in the scroll viewport. The staff stack
    // is the scroller's LOCAL vertical axis in both orientations — scrollTop /
    // scrollHeight / clientHeight are unaffected by the landscape CSS rotation —
    // so we map the staff's fractional position straight onto the scroll extent.
    scrollStaffIntoCenter(staffIndex) {
        const scrollEl = this.scrollContainer();
        const band = this.staffBand(staffIndex);
        if (!scrollEl || !band) return;
        const center = band.center * scrollEl.scrollHeight;
        const target = center - scrollEl.clientHeight / 2;
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
