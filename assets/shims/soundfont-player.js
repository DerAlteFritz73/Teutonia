// Interop shim: the vendored `soundfont-player` bundle only has a default export,
// but `osmd-audio-player` does `import * as Soundfont from 'soundfont-player'` and
// calls `Soundfont.instrument(...)`. Re-export the default's members as named
// exports so the namespace import works.
import Soundfont from '../vendor/soundfont-player/soundfont-player.index.js';

export default Soundfont;
export const instrument = Soundfont.instrument;
export const nameToUrl = Soundfont.nameToUrl;
export const noteToMidi = Soundfont.noteToMidi;
