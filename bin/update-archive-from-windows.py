#!/usr/bin/env python3
"""Copy newer versions of the website's song files from the Windows work folder.

The website serves files from ~/dropbox-archive/Chorgemeinschaft Teutonia (see
compose.yaml). Joël-Marie edits the originals in a OneDrive folder on Windows,
which is organised differently (other folder names, no Noten/Aktuelle Proben
split). For every file the website has, this finds the file with the same name
in the Windows folder and, when the Windows copy is newer and different,
replaces the website's copy.

One-way and conservative:
  - The Windows folder is only read, never changed.
  - Only files the website already has are updated; nothing is added or deleted.
  - When a name exists several times on Windows, the copy whose folders resemble
    the website's folders wins; if that's unclear, the file is skipped and reported.
  - Replaced files are moved to the archive's hidden ".Papierkorb" folder.

Usage: bin/update-archive-from-windows.py [--dry-run]
"""

import argparse
import difflib
import os
import re
import shutil
import sys
import time
import unicodedata
from collections import defaultdict

ARCHIVE = os.path.expanduser('~/dropbox-archive/Chorgemeinschaft Teutonia')
WINDOWS = '/mnt/c/Users/Joel/OneDrive/Documents/-= Musique =-/Teutonia'
TRASH = os.path.join(ARCHIVE, '.Papierkorb')

# A Windows match must share at least this much with one of the archive file's
# folder names (0..1, see folder_similarity), so a generic name like "Kyrie.mxl"
# never pulls in a file from a different song.
MIN_SIMILARITY = 0.6
# Seconds of mtime difference treated as "the same time" (FAT/NTFS rounding).
MTIME_SLACK = 2


def norm(name: str) -> str:
    return unicodedata.normalize('NFC', name).casefold().strip()


def folder_key(name: str) -> str:
    """Folder name reduced to its title: no "#composer", order number or punctuation."""
    name = norm(name).split('#')[0]
    name = re.sub(r'^\s*\d+\s*[-.]?\s*', '', name)
    return re.sub(r'[^\w]+', ' ', name).strip()


def folder_similarity(archive_dirs: list[str], windows_dirs: list[str]) -> float:
    """Best match between any archive folder name and any Windows folder name."""
    best = 0.0
    for a in map(folder_key, archive_dirs):
        for w in map(folder_key, windows_dirs):
            if a and w:
                best = max(best, difflib.SequenceMatcher(None, a, w).ratio())
    return best


def path_similarity(archive_dirs: list[str], windows_rel: str) -> float:
    """Tie-breaker: how alike the whole folder paths are (ignoring Noten/Aktuelle Proben)."""
    if archive_dirs and archive_dirs[0] in ('Noten', 'Aktuelle Proben'):
        archive_dirs = archive_dirs[1:]
    a = ' / '.join(map(folder_key, archive_dirs))
    w = ' / '.join(map(folder_key, windows_rel.split(os.sep)[:-1]))
    return difflib.SequenceMatcher(None, a, w).ratio()


def identity(windows_rel: str) -> tuple[int, int]:
    """Copies with the same size and modification time count as the same file."""
    st = os.stat(os.path.join(WINDOWS, windows_rel))
    return st.st_size, int(st.st_mtime)


def files_under(root: str, skip_hidden: bool):
    for d, dirs, files in os.walk(root):
        if skip_hidden:
            dirs[:] = [x for x in dirs if not x.startswith('.')]
        for f in files:
            yield os.path.relpath(os.path.join(d, f), root)


def same_content(a: str, b: str) -> bool:
    with open(a, 'rb') as fa, open(b, 'rb') as fb:
        while True:
            ca, cb = fa.read(1 << 20), fb.read(1 << 20)
            if ca != cb:
                return False
            if not ca:
                return True


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.split('\n\n')[0])
    parser.add_argument('--dry-run', action='store_true', help='only report what would be updated')
    args = parser.parse_args()

    if not os.path.isdir(WINDOWS):
        print(f'Windows folder not found: {WINDOWS}', file=sys.stderr)
        return 1

    by_name = defaultdict(list)
    for rel in files_under(WINDOWS, skip_hidden=False):
        by_name[norm(os.path.basename(rel))].append(rel)

    stamp = time.strftime('%Y-%m-%d_%H%M%S')
    updated, skipped, failed = [], [], []

    for rel in sorted(files_under(ARCHIVE, skip_hidden=True)):
        candidates = by_name.get(norm(os.path.basename(rel)))
        if not candidates:
            continue

        archive_dirs = rel.split(os.sep)[:-1]
        scored = sorted(
            ((folder_similarity(archive_dirs, c.split(os.sep)[:-1]), path_similarity(archive_dirs, c), c)
             for c in candidates),
            reverse=True,
        )
        score, _, match = scored[0]
        if score < MIN_SIMILARITY:
            continue
        tied = [c for s, p, c in scored if (s, p) == scored[0][:2]]
        if len(tied) > 1 and len({identity(c) for c in tied}) > 1:
            skipped.append(f'{rel}  (several different Windows copies, unclear which one)')
            continue

        src = os.path.join(WINDOWS, match)
        dst = os.path.join(ARCHIVE, rel)
        try:
            s_src, s_dst = os.stat(src), os.stat(dst)
            if s_src.st_mtime <= s_dst.st_mtime + MTIME_SLACK:
                continue
            # Reading a OneDrive online-only file downloads it, so compare content
            # only when the size can't already tell the files apart.
            if s_src.st_size == s_dst.st_size and same_content(src, dst):
                continue
            updated.append(f'{rel}  <-  {match}')
            if args.dry_run:
                continue

            tmp = os.path.join(os.path.dirname(dst), f'.{os.path.basename(dst)}.updating')
            shutil.copy2(src, tmp)
            old = os.path.join(TRASH, f'{stamp} update', rel)
            os.makedirs(os.path.dirname(old), exist_ok=True)
            shutil.move(dst, old)
            os.replace(tmp, dst)
        except OSError as e:
            # Typically an online-only file while OneDrive isn't running; retried next run.
            failed.append(f'{rel}: {e}')
            if updated and updated[-1].startswith(rel + '  <-'):
                updated.pop()

    verb = 'Would update' if args.dry_run else 'Updated'
    print(f'{time.strftime("%Y-%m-%d %H:%M")}  {verb} {len(updated)} file(s), '
          f'skipped {len(skipped)}, failed {len(failed)}.')
    for line in updated:
        print(f'  {verb.lower()}: {line}')
    for line in skipped:
        print(f'  skipped: {line}')
    for line in failed:
        print(f'  FAILED:  {line}')
    if updated and not args.dry_run:
        print(f'  Previous versions: {os.path.join(TRASH, stamp + " update")}')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
