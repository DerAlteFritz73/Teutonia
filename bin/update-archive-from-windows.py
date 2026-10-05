#!/usr/bin/env python3
"""Bring the website's song folders up to date from the Windows work folder.

Joël-Marie edits the sheet music in a OneDrive folder on Windows; the website
serves its own copy from ~/dropbox-archive/Chorgemeinschaft Teutonia (see
compose.yaml). The song folders in the Windows "-= Noten =-" folder carry the
same names as the website's "Noten" folders, so they are paired by name:

    -= Noten =-/<song>/…  →  Noten/<song>/…
                          →  Aktuelle Proben/<song>/…  (only if that copy exists)

Windows is the master:
  - a file the website has is replaced when the Windows copy is newer and different;
  - a file the website lacks is copied if it's a type the website shows (PDF,
    audio, video, MusicXML) — new song folders included;
  - in song folders that exist on both sides, website files that are no longer
    on Windows (deleted or renamed there) are removed. Exception: files directly
    in a song folder are kept if removing them would leave the song with nothing
    to show; that's reported instead.

Nothing is deleted for good: replaced and removed files go to ".Papierkorb".
Song folders that exist only on the website are left alone, and the Windows
folder is only read.

Usage: bin/update-archive-from-windows.py [--dry-run]
"""

import argparse
import os
import shutil
import sys
import time
import unicodedata

ARCHIVE = os.path.expanduser('~/dropbox-archive/Chorgemeinschaft Teutonia')
WINDOWS_NOTEN = '/mnt/c/Users/Joel/OneDrive/Documents/-= Musique =-/Teutonia/-= Noten =-'
TRASH = os.path.join(ARCHIVE, '.Papierkorb')

# File types the website lists (ArchiveService::ALLOWED_EXTENSIONS). Only these
# are copied as new files; Finale files etc. stay on Windows.
WEBSITE_EXTENSIONS = {'pdf', 'mp3', 'mp4', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma',
                      'webm', 'avi', 'mov', 'mkv', 'mxl', 'musicxml'}
IGNORED_FILES = {'desktop.ini', 'thumbs.db'}
# Seconds of mtime difference treated as "the same time" (filesystem rounding).
MTIME_SLACK = 2


def norm(path: str) -> str:
    return unicodedata.normalize('NFC', path)


def is_shown(name: str) -> bool:
    return name.rsplit('.', 1)[-1].lower() in WEBSITE_EXTENSIONS


def website_files(folder: str):
    """Non-hidden files below an archive folder, relative to it."""
    for d, dirs, files in os.walk(folder):
        dirs[:] = [x for x in dirs if not x.startswith('.')]
        for f in files:
            if not f.startswith('.'):
                yield os.path.relpath(os.path.join(d, f), folder)


def to_trash(path: str, trash_dir: str) -> None:
    old = os.path.join(trash_dir, os.path.relpath(path, ARCHIVE))
    os.makedirs(os.path.dirname(old), exist_ok=True)
    shutil.move(path, old)


def remove_empty_dirs(folder: str) -> None:
    for d, _, _ in sorted(os.walk(folder), key=lambda w: -len(w[0])):
        if d != folder and not os.listdir(d):
            os.rmdir(d)


def windows_files():
    """Paths relative to WINDOWS_NOTEN, skipping hidden, Office lock and system files."""
    for d, dirs, files in os.walk(WINDOWS_NOTEN):
        dirs[:] = sorted(x for x in dirs if not x.startswith('.'))
        for f in sorted(files):
            if f.startswith(('.', '~$')) or f.lower() in IGNORED_FILES:
                continue
            yield os.path.relpath(os.path.join(d, f), WINDOWS_NOTEN)


def same_content(a: str, b: str) -> bool:
    with open(a, 'rb') as fa, open(b, 'rb') as fb:
        while True:
            ca, cb = fa.read(1 << 20), fb.read(1 << 20)
            if ca != cb:
                return False
            if not ca:
                return True


def install(src: str, dst: str, trash_dir: str | None) -> None:
    """Copy src to dst via a temp file, so the website never serves a half-written file."""
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    tmp = os.path.join(os.path.dirname(dst), f'.{os.path.basename(dst)}.updating')
    try:
        shutil.copy2(src, tmp)
        if trash_dir is not None:
            to_trash(dst, trash_dir)
        os.replace(tmp, dst)
    finally:
        if os.path.exists(tmp):
            os.remove(tmp)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--dry-run', action='store_true', help='only report what would change')
    args = parser.parse_args()

    if not os.path.isdir(WINDOWS_NOTEN):
        print(f'Windows folder not found: {WINDOWS_NOTEN}', file=sys.stderr)
        return 1

    trash_dir = os.path.join(TRASH, time.strftime('%Y-%m-%d_%H%M%S') + ' update')
    updated, added, removed, kept, failed = [], [], [], [], []

    # Website song folder names by normalised name, so "ä" spelt either way pairs up.
    website_songs = {norm(x): x for x in os.listdir(os.path.join(ARCHIVE, 'Noten'))}
    windows_rels = set()

    for rel in windows_files():
        windows_rels.add(norm(rel))
        song, _, rest = rel.partition(os.sep)
        song = website_songs.get(norm(song), song)
        src = os.path.join(WINDOWS_NOTEN, rel)
        targets = [os.path.join(ARCHIVE, 'Noten', song, rest)]
        if os.path.isdir(os.path.join(ARCHIVE, 'Aktuelle Proben', song)):
            targets.append(os.path.join(ARCHIVE, 'Aktuelle Proben', song, rest))

        for dst in targets:
            label = os.path.relpath(dst, ARCHIVE)
            try:
                if os.path.exists(dst):
                    s_src, s_dst = os.stat(src), os.stat(dst)
                    if s_src.st_mtime <= s_dst.st_mtime + MTIME_SLACK:
                        continue
                    # Reading a OneDrive online-only file downloads it, so compare
                    # content only when the size can't already tell them apart.
                    if s_src.st_size == s_dst.st_size and same_content(src, dst):
                        continue
                    if not args.dry_run:
                        install(src, dst, trash_dir)
                    updated.append(label)
                elif is_shown(rel):
                    if not args.dry_run:
                        install(src, dst, None)
                    added.append(label)
            except OSError as e:
                # Typically an online-only file while OneDrive isn't running; retried next run.
                failed.append(f'{label}: {e}')

    # Remove what is no longer on Windows, in song folders present on both sides.
    windows_songs = {r.split(os.sep)[0] for r in windows_rels}
    for key, song in sorted(website_songs.items()):
        if key not in windows_songs:
            continue
        for base in ('Noten', 'Aktuelle Proben'):
            folder = os.path.join(ARCHIVE, base, song)
            if not os.path.isdir(folder):
                continue
            gone = [r for r in website_files(folder) if norm(os.path.join(key, r)) not in windows_rels]
            top_gone = [r for r in gone if os.sep not in r]
            top_left = [r for r in website_files(folder)
                        if os.sep not in r and r not in top_gone and is_shown(r)]
            top_new = [r for r in windows_rels
                       if r.startswith(key + os.sep) and r.count(os.sep) == 1 and is_shown(r)]
            if any(map(is_shown, top_gone)) and not top_left and not top_new:
                kept += [f'{base}/{song}/{r}' for r in top_gone]
                gone = [r for r in gone if r not in top_gone]
            for r in gone:
                path = os.path.join(folder, r)
                try:
                    if not args.dry_run:
                        to_trash(path, trash_dir)
                    removed.append(os.path.relpath(path, ARCHIVE))
                except OSError as e:
                    failed.append(f'{os.path.relpath(path, ARCHIVE)}: {e}')
            if not args.dry_run:
                remove_empty_dirs(folder)

    prefix = 'Would ' if args.dry_run else ''
    print(f'{time.strftime("%Y-%m-%d %H:%M")}  {prefix}update {len(updated)}, '
          f'{prefix.lower()}add {len(added)}, {prefix.lower()}remove {len(removed)}, '
          f'failed {len(failed)}.')
    for line in updated:
        print(f'  updated: {line}')
    for line in added:
        print(f'  added:   {line}')
    for line in removed:
        print(f'  removed: {line}')
    for line in kept:
        print(f'  kept:    {line}  (not on Windows, but the song would have nothing left to show)')
    for line in failed:
        print(f'  FAILED:  {line}')
    if (updated or removed) and not args.dry_run:
        print(f'  Previous versions: {trash_dir}')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
