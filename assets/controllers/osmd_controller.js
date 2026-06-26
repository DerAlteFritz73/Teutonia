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
    static targets = ['container', 'controls', 'status', 'playBtn', 'stopBtn'];
    static values = { url: String, playback: { type: Boolean, default: true } };

    async connect() {
        this.colors = ['#d6336c', '#1c7ed6', '#2f9e44', '#e8590c', '#7048e8', '#0ca678'];
        this.activeStaff = null;
        this.playing = false;

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
                // A clearly visible highlight box marking the current position.
                cursorsOptions: [{ type: 0, color: '#1c7ed6', alpha: 0.45, follow: false }],
            });

            this.setStatus('Lade Partitur…');
            await this.osmd.load(this.urlValue);
            this.osmd.render();
            this.buildStaffButtons();

            // Score-driven playback: synthesise audio from the notes themselves.
            if (this.playbackValue) {
                this.setStatus('Lade Klänge…');
                const { default: AudioPlayer } = await import('osmd-audio-player');
                this.audioPlayer = new AudioPlayer();
                await this.audioPlayer.loadScore(this.osmd);
                // play() resolves as soon as the scheduler starts (not when
                // playback ends), so we track real play/pause/stop transitions
                // via the player's state-change events instead.
                this.audioPlayer.on('state-change', (state) => this.onPlaybackState(state));
                this.enablePlayback();
            }
            this.setStatus('');
        } catch (e) {
            this.setStatus('Fehler beim Laden der Partitur: ' + e.message);
            console.error('[osmd]', e);
        }
    }

    disconnect() {
        this.stopAutoScroll();
        try {
            this.audioPlayer?.stop();
        } catch (e) { /* ignore */ }
        if (this.osmd) {
            this.osmd.clear();
            this.osmd = null;
        }
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

    async togglePlay() {
        if (!this.audioPlayer) return;
        if (this.playing) {
            await this.audioPlayer.pause();
        } else {
            await this.audioPlayer.play();
        }
        // play/pause label and auto-scroll are driven by onPlaybackState().
    }

    async stop() {
        if (!this.audioPlayer) return;
        await this.audioPlayer.stop();
    }

    // Reacts to the audio player's PLAYING / PAUSED / STOPPED transitions.
    onPlaybackState(state) {
        this.playing = state === 'PLAYING';
        if (this.playing) {
            this.startAutoScroll();
        } else {
            this.stopAutoScroll();
        }
        this.setPlayLabel();
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
            this.centerCursor(scrollEl);
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

    // Ease the scroll position so the cursor sits in the horizontal middle.
    centerCursor(scrollEl) {
        const cursorEl = this.osmd?.cursor?.cursorElement;
        if (!cursorEl || cursorEl.offsetParent === null) return;
        const cursorRect = cursorEl.getBoundingClientRect();
        const viewRect = scrollEl.getBoundingClientRect();
        const cursorMid = (cursorRect.left - viewRect.left) + scrollEl.scrollLeft + cursorRect.width / 2;
        const target = cursorMid - scrollEl.clientWidth / 2;
        const max = scrollEl.scrollWidth - scrollEl.clientWidth;
        const clamped = Math.max(0, Math.min(target, max));
        // Ease toward the target to smooth out the cursor's discrete jumps.
        const delta = clamped - scrollEl.scrollLeft;
        if (Math.abs(delta) < 0.5) return;
        scrollEl.scrollLeft += delta * 0.15;
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

        this.rerender();
        this.controlsTarget.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
        if (turningOn) {
            btn.classList.add('active');
            this.scrollStaffIntoCenter(staffIndex);
        }
    }

    // Re-render the score, then re-point the audio player at the freshly
    // created cursor. OSMD's render() replaces the cursor object every time,
    // so without this the audio player keeps advancing the old (detached)
    // cursor after a part is selected — which silently breaks the moving
    // highlight and the playback auto-scroll.
    rerender() {
        this.osmd.render();
        if (this.audioPlayer && this.osmd.cursor) {
            this.audioPlayer.cursor = this.osmd.cursor;
        }
    }

    // Vertically centre the given staff in the scroll viewport. All parts are
    // stacked in one horizontal staff line, so a staff's Y is constant across
    // the piece; we read it from the first measure and convert OSMD units to
    // pixels (unitInPixels = 10, scaled by zoom).
    scrollStaffIntoCenter(staffIndex) {
        const scrollEl = this.scrollContainer();
        const svg = this.containerTarget.querySelector('svg');
        const gm = this.osmd?.GraphicSheet?.MeasureList?.[0]?.[staffIndex];
        if (!scrollEl || !svg || !gm) return;

        const unit = 10 * (this.osmd.zoom || 1);
        const ps = gm.PositionAndShape;
        const centerInSvg = (ps.AbsolutePosition.y + ps.Size.height / 2) * unit;

        // Map the intrinsic SVG coordinate to on-screen pixels (handles any
        // CSS/viewBox scaling), then to scroll-content coordinates.
        const svgRect = svg.getBoundingClientRect();
        const intrinsicH = svg.height?.baseVal?.value || svgRect.height;
        const scale = svgRect.height / intrinsicH;
        const viewRect = scrollEl.getBoundingClientRect();
        const centerContentY = (svgRect.top - viewRect.top) + centerInSvg * scale + scrollEl.scrollTop;

        const target = centerContentY - scrollEl.clientHeight / 2;
        const max = scrollEl.scrollHeight - scrollEl.clientHeight;
        scrollEl.scrollTo({ top: Math.max(0, Math.min(target, max)), behavior: 'smooth' });
    }

    resetColors() {
        this.resetColorsInternal();
        this.activeStaff = null;
        this.rerender();
        this.controlsTarget.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
    }
}
