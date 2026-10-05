<?php

namespace App\Service;

/**
 * Read (and, for "Aktuelle Proben", write) the choir's song folders in the local
 * file archive mounted at $archiveDir.
 *
 * Paths handed in and out are archive paths like
 * "/Chorgemeinschaft Teutonia/Noten/Cohen - Hallelujah", relative to $archiveDir.
 * Stored paths may differ from the real folder names in case or spacing, so every
 * path segment is resolved leniently (see resolve()).
 */
class ArchiveService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'mp3', 'mp4', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma', 'webm', 'avi', 'mov', 'mkv', 'mxl', 'musicxml'];
    private const EXCLUDED_FOLDERS = ['-= Scans - Originale =-'];

    /** Every path the app may touch lives below this folder. */
    public const BASE_PATH = '/Chorgemeinschaft Teutonia';

    /** Removed "Aktuelle Proben" copies are moved here instead of being deleted outright. */
    private const TRASH_PATH = self::BASE_PATH . '/.Papierkorb';

    private string $archiveDir;

    /** Per-request memo of the whole BASE_PATH structure tree. */
    private ?array $structureTree = null;

    public function __construct(string $archiveDir)
    {
        $this->archiveDir = rtrim($archiveDir, '/');
    }

    /**
     * True when the archive folder is mounted and readable.
     * Lets pages show a notice instead of silently empty file lists.
     */
    public function isAvailable(): bool
    {
        return is_dir($this->archiveDir . self::BASE_PATH) && is_readable($this->archiveDir . self::BASE_PATH);
    }

    /**
     * Map an archive path to the real filesystem path, or null when it doesn't
     * exist or points outside BASE_PATH.
     *
     * Each segment is matched exactly first, then case- and whitespace-insensitively:
     * stored paths sometimes drift from the real folder name only by spacing — e.g. a
     * multi-movement song stored as ".../1 - Bumerang" whose actual subfolder is
     * "1-Bumerang" — or by case.
     */
    private function resolve(string $path): ?string
    {
        $parts = array_values(array_filter(explode('/', $path), fn ($p) => $p !== ''));
        if (in_array('..', $parts, true) || in_array('.', $parts, true)) {
            return null;
        }

        $current = $this->archiveDir;
        foreach ($parts as $part) {
            $exact = $current . '/' . $part;
            if (file_exists($exact)) {
                $current = $exact;
                continue;
            }
            if (!is_dir($current)) {
                return null;
            }
            $match = $this->matchName(scandir($current) ?: [], $part);
            if ($match === null) {
                return null;
            }
            $current .= '/' . $match;
        }

        $real = realpath($current);
        $base = realpath($this->archiveDir . self::BASE_PATH);
        if ($real === false || $base === false || ($real !== $base && !str_starts_with($real, $base . '/'))) {
            return null;
        }

        return $current;
    }

    /** @param string[] $names */
    private function matchName(array $names, string $segment): ?string
    {
        $normalize = static fn (string $s): string => mb_strtolower(preg_replace('/\s+/', '', $s) ?? '');
        $target    = $normalize($segment);
        foreach ($names as $name) {
            if ($name !== '.' && $name !== '..' && $normalize($name) === $target) {
                return $name;
            }
        }
        return null;
    }

    /** Archive path for a real filesystem path below $archiveDir. */
    private function toArchivePath(string $realPath): string
    {
        return substr($realPath, strlen($this->archiveDir));
    }

    /**
     * Real path of an existing file below BASE_PATH (null when missing), for
     * streaming it to the browser.
     */
    public function getLocalPath(string $path): ?string
    {
        $real = $this->resolve($path);
        return $real !== null && is_file($real) ? $real : null;
    }

    /**
     * Folder tree below $folderPath: folder name => ['_type' => 'folder',
     * '_files' => [...], '_subfolders' => [...]], plus '_root_files' for files
     * directly in $folderPath. Only allowed media files are listed and folders
     * without any are left out.
     */
    public function getFileStructure(string $folderPath): array
    {
        $dir = $this->resolve($folderPath);
        if ($dir === null || !is_dir($dir)) {
            return [];
        }

        $node = $this->scanFolder($dir);
        $tree = $node['_subfolders'];
        if (!empty($node['_files'])) {
            $tree = ['_root_files' => $node['_files']] + $tree;
        }
        return $tree;
    }

    /**
     * Recursively scan one folder into a tree node. Hidden entries (the trash,
     * OS metadata) and EXCLUDED_FOLDERS are skipped.
     *
     * @return array{_type: string, _files: list<array>, _subfolders: array<string,array>}
     */
    private function scanFolder(string $dir): array
    {
        $files      = [];
        $subfolders = [];

        foreach (scandir($dir) ?: [] as $name) {
            if ($name[0] === '.') {
                continue;
            }
            $full = $dir . '/' . $name;
            if (is_dir($full)) {
                if (in_array($name, self::EXCLUDED_FOLDERS, true)) {
                    continue;
                }
                $sub = $this->scanFolder($full);
                if (!empty($sub['_files']) || !empty($sub['_subfolders'])) {
                    $subfolders[$name] = $sub;
                }
            } elseif ($this->isAllowedFile($name)) {
                $files[] = $this->fileEntry($full, $name);
            }
        }

        usort($files, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        uksort($subfolders, 'strcasecmp');

        return ['_type' => 'folder', '_files' => $files, '_subfolders' => $subfolders];
    }

    /** @return array{name:string,path:string,size:int,link:null,type:string} */
    private function fileEntry(string $full, string $name): array
    {
        return [
            'name' => $name,
            'path' => $this->toArchivePath($full),
            'size' => (int) @filesize($full),
            'link' => null,
            'type' => $this->getFileType($name),
        ];
    }

    /**
     * Check if a file has an allowed extension
     */
    private function isAllowedFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::ALLOWED_EXTENSIONS);
    }

    /**
     * Get file type (pdf, audio, or video)
     */
    private function getFileType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return 'pdf';
        }

        $audioExtensions = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma'];
        if (in_array($extension, $audioExtensions)) {
            return 'audio';
        }

        // MusicXML score — drives the scrolling Partitur playback view.
        if (in_array($extension, ['mxl', 'musicxml'])) {
            return 'score';
        }

        return 'video';
    }

    /**
     * Files directly in a song folder, plus whether it has (non-empty) subfolders.
     *
     * @param string $folderPath Archive path, e.g. /Chorgemeinschaft Teutonia/Noten/Cohen - Hallelujah
     * @return array{files: list<array{name:string,path:string,size:int,link:null,type:string}>, hasSubfolders: bool}
     */
    public function getFilesForFolder(string $folderPath): array
    {
        $dir = $this->resolve($folderPath);
        if ($dir === null || !is_dir($dir)) {
            return ['files' => [], 'hasSubfolders' => false];
        }

        $node = $this->scanFolder($dir);
        return [
            'files'         => $node['_files'],
            'hasSubfolders' => !empty($node['_subfolders']),
        ];
    }

    /**
     * Recursively count PDF and audio files under a folder (e.g. to show a parent
     * song's combined count across all its Sätze).
     *
     * @return array{pdf: int, audio: int}
     */
    public function getRecursiveCounts(string $folderPath): array
    {
        $node = $this->findTreeNode($folderPath);
        return $node === null ? ['pdf' => 0, 'audio' => 0] : $this->countTreeFiles($node);
    }

    /**
     * Count PDF/audio files directly in a folder (NOT its subfolders). Used for a
     * parent song's own files, which are then added to each child song's
     * recursive count.
     *
     * @return array{pdf: int, audio: int}
     */
    public function getImmediateCounts(string $folderPath): array
    {
        $node = $this->findTreeNode($folderPath);
        if ($node === null) {
            return ['pdf' => 0, 'audio' => 0];
        }
        $pdf = 0;
        $audio = 0;
        foreach ($node['_files'] ?? [] as $f) {
            $type = $f['type'] ?? '';
            if ($type === 'pdf') {
                $pdf++;
            } elseif ($type === 'audio') {
                $audio++;
            }
        }
        return ['pdf' => $pdf, 'audio' => $audio];
    }

    /**
     * Navigate the whole BASE_PATH tree to the node for $folderPath. The tree is
     * scanned once per request, so a page with many songs reads the disk once.
     */
    private function findTreeNode(string $folderPath): ?array
    {
        if ($this->structureTree === null) {
            $this->structureTree = $this->getFileStructure(self::BASE_PATH);
        }

        $relative = ltrim(str_replace(self::BASE_PATH, '', $folderPath), '/');
        $parts    = $relative === '' ? [] : explode('/', $relative);

        $node = $this->structureTree;
        foreach ($parts as $i => $part) {
            $level = $i === 0 ? $node : ($node['_subfolders'] ?? []);
            $node  = $this->matchFolderSegment($level, $part);
            if ($node === null) {
                return null;
            }
        }
        return $node;
    }

    /**
     * Resolve one path segment against a level of the structure tree (a map of
     * folder-name => node), exactly or case-/whitespace-insensitively like resolve().
     *
     * @param array<string,mixed> $level
     */
    private function matchFolderSegment(array $level, string $segment): ?array
    {
        if (isset($level[$segment]) && is_array($level[$segment])) {
            return $level[$segment];
        }

        $names = array_filter(
            array_map('strval', array_keys($level)),
            fn ($n) => $n !== '_files' && $n !== '_subfolders' && $n !== '_root_files'
        );
        $match = $this->matchName($names, $segment);

        return $match !== null && is_array($level[$match]) ? $level[$match] : null;
    }

    /**
     * True when the folder holds any media files or subfolders with some. Returns
     * false for missing folders (treated as empty) — callers can then fall back to
     * a canonical folder.
     */
    public function folderHasContent(string $folderPath): bool
    {
        $node = $this->findTreeNode($folderPath);

        return $node !== null && (!empty($node['_files']) || !empty($node['_subfolders']));
    }

    /** @return array{pdf: int, audio: int} */
    private function countTreeFiles(array $node): array
    {
        $pdf = 0;
        $audio = 0;
        foreach ($node['_files'] ?? [] as $f) {
            $type = $f['type'] ?? '';
            if ($type === 'pdf') {
                $pdf++;
            } elseif ($type === 'audio') {
                $audio++;
            }
        }
        foreach ($node['_subfolders'] ?? [] as $sub) {
            $c = $this->countTreeFiles($sub);
            $pdf   += $c['pdf'];
            $audio += $c['audio'];
        }
        return ['pdf' => $pdf, 'audio' => $audio];
    }

    /**
     * List the immediate subfolder names inside an archive path (non-recursive).
     *
     * @return string[] Folder names (not full paths)
     * @throws \RuntimeException when the folder doesn't exist
     */
    public function listSubfolders(string $path): array
    {
        $dir = $this->resolve($path);
        if ($dir === null || !is_dir($dir)) {
            throw new \RuntimeException("Ordner nicht gefunden: $path");
        }

        $folders = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name[0] !== '.' && is_dir($dir . '/' . $name)) {
                $folders[] = $name;
            }
        }
        return $folders;
    }

    /**
     * Find the first audio file in a folder and return its duration as "M:SS".
     * Only MP3s can be measured (from their frame headers); other formats give null.
     */
    public function getFirstAudioDuration(string $folderPath): ?string
    {
        $audio = null;
        foreach ($this->getFilesForFolder($folderPath)['files'] as $file) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3', 'mp4', 'm4a', 'wav', 'ogg', 'flac', 'aac', 'wma'], true)) {
                $audio = $file;
                break;
            }
        }

        if ($audio === null || strtolower(pathinfo($audio['name'], PATHINFO_EXTENSION)) !== 'mp3') {
            return null;
        }

        $local = $this->getLocalPath($audio['path']);
        $data  = $local !== null ? @file_get_contents($local, false, null, 0, 131072) : false;
        if ($data === false || strlen($data) < 4) {
            return null;
        }

        $seconds = $this->parseMp3DurationSeconds($data, $audio['size']);
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function parseMp3DurationSeconds(string $data, int $fileSize): ?int
    {
        $len    = strlen($data);
        $offset = 0;

        // Skip ID3v2 tag
        if ($len >= 10 && substr($data, 0, 3) === 'ID3') {
            $tagSize = ((ord($data[6]) & 0x7F) << 21)
                     | ((ord($data[7]) & 0x7F) << 14)
                     | ((ord($data[8]) & 0x7F) << 7)
                     |  (ord($data[9]) & 0x7F);
            $offset = 10 + $tagSize;
            if ((ord($data[5]) & 0x10) !== 0) {
                $offset += 10; // footer present
            }
        }

        // Bitrate tables (kbps): index → [MPEG1-L3, MPEG2-L3]
        $bitrates = [
            3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0],
            2 => [0, 8,  16, 24, 32, 40, 48, 56, 64,  80,  96,  112, 128, 144, 160, 0],
        ];
        $sampleRates = [
            3 => [44100, 48000, 32000],
            2 => [22050, 24000, 16000],
            0 => [11025, 12000, 8000],
        ];

        for ($i = $offset; $i < $len - 3; $i++) {
            if (ord($data[$i]) !== 0xFF || (ord($data[$i + 1]) & 0xE0) !== 0xE0) {
                continue;
            }

            $b1 = ord($data[$i + 1]);
            $b2 = ord($data[$i + 2]);
            $b3 = ord($data[$i + 3]);

            $version     = ($b1 >> 3) & 0x03;
            $layer       = ($b1 >> 1) & 0x03;
            $bitrateIdx  = ($b2 >> 4) & 0x0F;
            $srIdx       = ($b2 >> 2) & 0x03;
            $channelMode = ($b3 >> 6) & 0x03;

            if ($layer !== 1) {
                continue; // only Layer III
            }
            if ($bitrateIdx === 0 || $bitrateIdx === 0x0F) {
                continue;
            }
            if ($srIdx === 3) {
                continue;
            }

            $bitrate    = ($bitrates[$version] ?? [])[$bitrateIdx] ?? 0;
            $sampleRate = ($sampleRates[$version] ?? [])[$srIdx]   ?? 0;
            if ($bitrate === 0 || $sampleRate === 0) {
                continue;
            }

            // Side-info size for Xing header detection
            $sideInfo = ($version === 3)
                ? ($channelMode === 3 ? 17 : 32)
                : ($channelMode === 3 ? 9  : 17);

            $xingOff = $i + 4 + $sideInfo;
            if ($xingOff + 12 < $len) {
                $tag = substr($data, $xingOff, 4);
                if ($tag === 'Xing' || $tag === 'Info') {
                    $flags = unpack('N', substr($data, $xingOff + 4, 4))[1];
                    if ($flags & 0x01) {
                        $numFrames       = unpack('N', substr($data, $xingOff + 8, 4))[1];
                        $samplesPerFrame = ($version === 3) ? 1152 : 576;
                        return (int) round($numFrames * $samplesPerFrame / $sampleRate);
                    }
                }

                $vbriOff = $i + 4 + 32;
                if ($vbriOff + 18 < $len && substr($data, $vbriOff, 4) === 'VBRI') {
                    $numFrames       = unpack('N', substr($data, $vbriOff + 14, 4))[1];
                    $samplesPerFrame = ($version === 3) ? 1152 : 576;
                    return (int) round($numFrames * $samplesPerFrame / $sampleRate);
                }
            }

            // CBR: estimate from total file size
            $audioBytes = max(1, $fileSize - $offset);
            return (int) round($audioBytes * 8 / ($bitrate * 1000));
        }

        return null;
    }

    /**
     * Copy a folder (recursively) to a new archive path. Fails when the source is
     * missing or the target already exists.
     */
    public function copyFolder(string $fromPath, string $toPath): bool
    {
        $source    = $this->resolve($fromPath);
        $targetDir = $this->resolve(dirname($toPath));
        if ($source === null || !is_dir($source) || $targetDir === null || !is_dir($targetDir)) {
            error_log("Archive: cannot copy $fromPath to $toPath (source or target folder missing)");
            return false;
        }
        $target = $targetDir . '/' . basename($toPath);
        if (file_exists($target)) {
            error_log("Archive: cannot copy $fromPath to $toPath (target exists)");
            return false;
        }

        try {
            $this->copyRecursive($source, $target);
        } catch (\RuntimeException $e) {
            error_log("Archive: error copying $fromPath to $toPath: " . $e->getMessage());
            return false;
        }
        $this->structureTree = null;
        return true;
    }

    private function copyRecursive(string $source, string $target): void
    {
        if (!@mkdir($target, 0775)) {
            throw new \RuntimeException("could not create $target");
        }
        foreach (scandir($source) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $from = $source . '/' . $name;
            $to   = $target . '/' . $name;
            if (is_dir($from)) {
                $this->copyRecursive($from, $to);
            } elseif (!@copy($from, $to)) {
                throw new \RuntimeException("could not copy $from");
            }
        }
    }

    /**
     * Remove a folder or file by moving it into the archive's trash folder
     * (".Papierkorb", hidden from all listings), so nothing is lost for good.
     * A path that doesn't exist counts as already removed.
     */
    public function moveToTrash(string $path): bool
    {
        $source = $this->resolve($path);
        if ($source === null) {
            error_log("Archive: not found (already removed): $path");
            return true;
        }
        if ($source === realpath($this->archiveDir . self::BASE_PATH)) {
            return false;
        }

        $trash = $this->archiveDir . self::TRASH_PATH;
        if (!is_dir($trash) && !@mkdir($trash, 0775)) {
            error_log("Archive: could not create trash folder $trash");
            return false;
        }

        $target = $trash . '/' . date('Y-m-d_His') . ' ' . basename($source);
        if (!@rename($source, $target)) {
            error_log("Archive: could not move $path to trash");
            return false;
        }
        $this->structureTree = null;
        return true;
    }

    /**
     * Format file size in human-readable format
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
