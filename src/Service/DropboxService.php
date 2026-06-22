<?php

namespace App\Service;

use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;

class DropboxService
{
    private Client $client;
    private const ALLOWED_EXTENSIONS = ['pdf', 'mp3', 'mp4', 'wav', 'ogg', 'flac', 'm4a', 'aac', 'wma', 'webm', 'avi', 'mov', 'mkv'];
    private const EXCLUDED_FOLDERS = ['-= Scans - Originale =-'];

    private string $refreshToken;
    private string $appKey;
    private string $appSecret;
    private string $tokenCacheFile;

    /** Per-request memo of the whole "/Chorgemeinschaft Teutonia" structure tree. */
    private ?array $structureTree = null;

    public function __construct(
        string $dropboxAccessToken,
        string $dropboxRefreshToken,
        string $dropboxAppKey,
        string $dropboxAppSecret,
        string $projectDir
    ) {
        $this->refreshToken = $dropboxRefreshToken;
        $this->appKey = $dropboxAppKey;
        $this->appSecret = $dropboxAppSecret;
        $cacheDir = $projectDir . '/var/cache/dropbox';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $this->tokenCacheFile = $cacheDir . '/token_cache.json';

        // Get a valid access token (will refresh if needed)
        $accessToken = $this->getValidAccessToken($dropboxAccessToken);
        $this->client = new Client($accessToken);
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    private function getValidAccessToken(string $initialToken): string
    {
        // Check if we have a cached token
        $cachedToken = $this->getTokenFromCache();

        if ($cachedToken) {
            error_log("Dropbox: Using cached access token (expires in " .
                ($cachedToken['expires_at'] - time()) . " seconds)");
            return $cachedToken['access_token'];
        }

        // No valid cached token — always refresh using the refresh token.
        // The initial access token from .env is a short-lived token that expires
        // after 4 hours and cannot be relied upon as a fallback.
        return $this->refreshAccessToken();
    }

    /**
     * Get token from cache if it exists and is not expired
     */
    private function getTokenFromCache(): ?array
    {
        if (!file_exists($this->tokenCacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents($this->tokenCacheFile), true);

        if (!$cache || !isset($cache['access_token'], $cache['expires_at'])) {
            return null;
        }

        // Check if token is expired (with 5-minute buffer)
        if ($cache['expires_at'] - 300 < time()) {
            error_log("Dropbox: Cached token expired, will refresh");
            return null;
        }

        return $cache;
    }

    /**
     * Save access token to cache
     */
    private function saveTokenToCache(string $accessToken, int $expiresAt): void
    {
        $cache = [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
            'refreshed_at' => time()
        ];

        file_put_contents($this->tokenCacheFile, json_encode($cache));
        error_log("Dropbox: Saved new access token to cache (expires at " .
            date('Y-m-d H:i:s', $expiresAt) . ")");
    }

    /**
     * Refresh the access token using the refresh token
     */
    private function refreshAccessToken(): string
    {
        error_log("Dropbox: Refreshing access token...");

        $ch = curl_init('https://api.dropbox.com/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->appKey,
                'client_secret' => $this->appSecret
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Dropbox: Token refresh failed with HTTP $httpCode: $response");
            throw new \RuntimeException("Failed to refresh Dropbox access token: HTTP $httpCode");
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'], $data['expires_in'])) {
            error_log("Dropbox: Invalid refresh response: $response");
            throw new \RuntimeException("Invalid response from Dropbox token refresh");
        }

        $accessToken = $data['access_token'];
        $expiresAt = time() + $data['expires_in'];

        // Save to cache
        $this->saveTokenToCache($accessToken, $expiresAt);

        // Update the client with the new token
        $this->client = new Client($accessToken);

        error_log("Dropbox: Successfully refreshed access token");

        return $accessToken;
    }

    /**
     * Get the file structure from a Dropbox folder
     *
     * @param string $folderPath Path to the folder in Dropbox
     * @param bool $useCache Whether to use cached results (default: true)
     * @return array Nested array of folders and files with their links
     */
    public function getFileStructure(string $folderPath, bool $useCache = true): array
    {
        // Use cache to avoid re-fetching on every page load
        $cacheDir = dirname($this->tokenCacheFile);
        $cacheFile = $cacheDir . '/structure_' . md5($folderPath) . '.json';
        $cacheMaxAge = 3600; // 1 hour

        if ($useCache && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheMaxAge) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                error_log("Dropbox: Using cached file structure (age: " . (time() - filemtime($cacheFile)) . "s)");
                return $cached;
            }
        }

        try {
            error_log("Dropbox: Fetching fresh file structure from {$folderPath}");
            $entries = $this->listFolderRecursive($folderPath);
            $tree = $this->buildFileTree($entries, $folderPath);

            // Save to cache
            file_put_contents($cacheFile, json_encode($tree));
            error_log("Dropbox: Cached " . count($entries) . " entries");

            return $tree;
        } catch (\Exception $e) {
            // Check if it's an authentication error
            if ($this->isAuthError($e)) {
                error_log("Dropbox: Authentication error detected, refreshing token and retrying...");
                $this->refreshAccessToken();

                // Retry once after token refresh
                try {
                    $entries = $this->listFolderRecursive($folderPath);
                    $tree = $this->buildFileTree($entries, $folderPath);
                    file_put_contents($cacheFile, json_encode($tree));
                    error_log("Dropbox: Successfully fetched after token refresh");
                    return $tree;
                } catch (\Exception $retryError) {
                    error_log("Dropbox error after retry: " . $retryError->getMessage());
                    return [];
                }
            }
            // Log error and return empty array
            error_log("Dropbox error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * List all files in a folder recursively
     */
    private function listFolderRecursive(string $path): array
    {
        $allEntries = [];
        $result = $this->client->listFolder($path, true);

        $allEntries = array_merge($allEntries, $result['entries']);

        // Handle pagination if there are more results
        while (isset($result['has_more']) && $result['has_more']) {
            $result = $this->client->listFolderContinue($result['cursor']);
            $allEntries = array_merge($allEntries, $result['entries']);
        }

        return $allEntries;
    }

    /**
     * Build a nested tree structure from flat list of entries
     */
    private function buildFileTree(array $entries, string $basePath): array
    {
        $tree = [];

        foreach ($entries as $entry) {
            // Only process files, not folders
            if ($entry['.tag'] !== 'file') {
                continue;
            }

            // Check if file has an allowed extension
            if (!$this->isAllowedFile($entry['name'])) {
                continue;
            }

            // Get the relative path from base folder
            $relativePath = ltrim(str_replace($basePath, '', $entry['path_display']), '/');
            $pathParts = explode('/', $relativePath);
            $fileName = array_pop($pathParts);

            // Skip files inside excluded folders
            foreach ($pathParts as $part) {
                if (in_array($part, self::EXCLUDED_FOLDERS)) {
                    continue 2;
                }
            }

            // Navigate through the tree structure
            $current = &$tree;
            foreach ($pathParts as $folder) {
                if (!isset($current[$folder])) {
                    $current[$folder] = [
                        '_type' => 'folder',
                        '_files' => [],
                        '_subfolders' => []
                    ];
                }
                $current = &$current[$folder]['_subfolders'];
            }

            // Add the file (WITHOUT creating shared links - too slow!)
            if (!empty($pathParts)) {
                // Navigate to the correct folder
                $current = &$tree;
                foreach ($pathParts as $folder) {
                    if (!isset($current[$folder])) {
                        $current[$folder] = [
                            '_type' => 'folder',
                            '_files' => [],
                            '_subfolders' => []
                        ];
                    }
                    // Move into the subfolders of this folder (except for the last one)
                    if ($folder !== end($pathParts)) {
                        $current = &$current[$folder]['_subfolders'];
                    } else {
                        $current = &$current[$folder];
                    }
                }
                // Add file to the current folder's _files array
                $current['_files'][] = [
                    'name' => $fileName,
                    'path' => $entry['path_display'],
                    'size' => $entry['size'] ?? 0,
                    'link' => null, // We'll generate links on-demand
                    'type' => $this->getFileType($fileName)
                ];
            } else {
                // File is in the root of the base folder
                if (!isset($tree['_root_files'])) {
                    $tree['_root_files'] = [];
                }
                $tree['_root_files'][] = [
                    'name' => $fileName,
                    'path' => $entry['path_display'],
                    'size' => $entry['size'] ?? 0,
                    'link' => null, // We'll generate links on-demand
                    'type' => $this->getFileType($fileName)
                ];
            }
        }

        // Sort the tree alphabetically
        $tree = $this->sortTree($tree);

        return $tree;
    }

    /**
     * Sort the tree structure alphabetically (folders and files)
     */
    private function sortTree(array $tree): array
    {
        // Sort root files if they exist
        if (isset($tree['_root_files'])) {
            usort($tree['_root_files'], function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
        }

        // Separate folders from special keys
        $folders = [];
        $special = [];

        foreach ($tree as $key => $value) {
            if ($key === '_root_files' || !is_array($value) || !isset($value['_type'])) {
                $special[$key] = $value;
            } else {
                $folders[$key] = $value;
            }
        }

        // Sort folders alphabetically by key
        uksort($folders, 'strcasecmp');

        // Sort files and subfolders within each folder
        foreach ($folders as $folderName => &$folderData) {
            // Sort files in this folder
            if (isset($folderData['_files'])) {
                usort($folderData['_files'], function($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
            }

            // Recursively sort subfolders
            if (isset($folderData['_subfolders']) && !empty($folderData['_subfolders'])) {
                $folderData['_subfolders'] = $this->sortTree($folderData['_subfolders']);
            }
        }

        // Merge back: special keys first, then sorted folders
        return array_merge($special, $folders);
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

        return 'video';
    }

    /**
     * Delete all Dropbox file-structure cache files so the next request fetches
     * fresh data from Dropbox (e.g. after files are renamed or moved).
     */
    public function clearFileCache(): void
    {
        $cacheDir = dirname($this->tokenCacheFile);
        foreach (glob($cacheDir . '/structure_*.json') as $file) {
            @unlink($file);
        }
        error_log('Dropbox: file structure cache cleared');
    }

    /**
     * Get the list of files for a specific song folder path, read from local cache.
     * Falls back to a live Dropbox call if the cache is missing.
     *
     * @param string $folderPath Full Dropbox path, e.g. /Chorgemeinschaft Teutonia/Noten/Cohen - Hallelujah
     * @return array  Array of file entries with keys: name, path, size, type
     */
    public function getFilesForFolder(string $folderPath): array
    {
        $basePath  = '/Chorgemeinschaft Teutonia';
        $cacheDir  = dirname($this->tokenCacheFile);
        $cacheFile = $cacheDir . '/structure_' . md5($basePath) . '.json';

        // Fast path: serve from the whole-tree cache when it is already present.
        if (file_exists($cacheFile)) {
            $tree = json_decode(file_get_contents($cacheFile), true) ?: [];

            $relative = ltrim(str_replace($basePath, '', $folderPath), '/');
            $parts    = $relative === '' ? [] : explode('/', $relative);

            // Navigate: first segment is a top-level key, subsequent ones are _subfolders
            $node = $tree;
            foreach ($parts as $i => $part) {
                $node = $i === 0
                    ? ($node[$part] ?? null)
                    : (($node['_subfolders'] ?? [])[$part] ?? null);
                if ($node === null) {
                    break;
                }
            }

            if ($node !== null) {
                return [
                    'files'         => $node['_files'] ?? [],
                    'hasSubfolders' => !empty($node['_subfolders']),
                ];
            }
            // Path not in the cached tree → fall through to a direct fetch.
        }

        // Fallback: fetch just this one folder directly. This avoids re-downloading
        // the entire "/Chorgemeinschaft Teutonia" tree on every cold-cache request —
        // which caused timeouts/rate-limits when the member page loaded many songs at once.
        return $this->listFolderContents($folderPath);
    }

    /**
     * Recursively count PDF and audio files under a folder, reading ONLY from the
     * pre-built whole-tree cache (no Dropbox API calls — safe to call many times at
     * render time, e.g. to show a parent song's combined count across all its Sätze).
     * Returns ['pdf' => int, 'audio' => int]; zeros when the folder isn't cached.
     *
     * @return array{pdf: int, audio: int}
     */
    public function getRecursiveCounts(string $folderPath): array
    {
        $basePath = '/Chorgemeinschaft Teutonia';
        // Build (and cache) the whole-tree structure once per request. getFileStructure
        // serves the on-disk cache when present and only re-fetches when it is missing
        // or stale, so a page with many parent songs triggers at most one Dropbox call.
        if ($this->structureTree === null) {
            $this->structureTree = $this->getFileStructure($basePath) ?: [];
        }
        $tree = $this->structureTree;

        $relative = ltrim(str_replace($basePath, '', $folderPath), '/');
        $parts    = $relative === '' ? [] : explode('/', $relative);

        $node = $tree;
        foreach ($parts as $i => $part) {
            $node = $i === 0 ? ($node[$part] ?? null) : (($node['_subfolders'] ?? [])[$part] ?? null);
            if ($node === null) {
                return ['pdf' => 0, 'audio' => 0];
            }
        }

        return $this->countTreeFiles($node);
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
     * List a single Dropbox folder's immediate files (+ whether it has subfolders),
     * returning the same shape as the cached tree nodes used by getFilesForFolder().
     *
     * @return array{files: list<array{name:string,path:string,size:int,link:null,type:string}>, hasSubfolders: bool}
     */
    private function listFolderContents(string $folderPath): array
    {
        $fetch = function () use ($folderPath): array {
            $files         = [];
            $hasSubfolders = false;

            $collect = function (array $entries) use (&$files, &$hasSubfolders): void {
                foreach ($entries as $entry) {
                    $tag = $entry['.tag'] ?? '';
                    if ($tag === 'folder') {
                        $hasSubfolders = true;
                    } elseif ($tag === 'file') {
                        $files[] = [
                            'name' => $entry['name'],
                            'path' => $entry['path_display'],
                            'size' => $entry['size'] ?? 0,
                            'link' => null,
                            'type' => $this->getFileType($entry['name']),
                        ];
                    }
                }
            };

            $result = $this->client->listFolder($folderPath, false);
            $collect($result['entries'] ?? []);
            while ($result['has_more'] ?? false) {
                $result = $this->client->listFolderContinue($result['cursor']);
                $collect($result['entries'] ?? []);
            }

            usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            return ['files' => $files, 'hasSubfolders' => $hasSubfolders];
        };

        try {
            return $fetch();
        } catch (\Exception $e) {
            if ($this->isAuthError($e)) {
                error_log('Dropbox: auth error listing folder, refreshing token…');
                $this->refreshAccessToken();
                try {
                    return $fetch();
                } catch (\Exception $retry) {
                    error_log("Error listing folder $folderPath after retry: " . $retry->getMessage());
                }
            } else {
                error_log("Error listing folder $folderPath: " . $e->getMessage());
            }
            return ['files' => [], 'hasSubfolders' => false];
        }
    }

    /**
     * List the immediate subfolder names inside a Dropbox path (non-recursive, fast).
     *
     * @return string[] Folder names (not full paths)
     */
    public function listSubfolders(string $path): array
    {
        $fetch = function () use ($path): array {
            $result  = $this->client->listFolder($path, false);
            $folders = [];
            foreach ($result['entries'] as $entry) {
                if ($entry['.tag'] === 'folder') {
                    $folders[] = $entry['name'];
                }
            }
            while ($result['has_more'] ?? false) {
                $result = $this->client->listFolderContinue($result['cursor']);
                foreach ($result['entries'] as $entry) {
                    if ($entry['.tag'] === 'folder') {
                        $folders[] = $entry['name'];
                    }
                }
            }
            return $folders;
        };

        try {
            return $fetch();
        } catch (\Exception $e) {
            if ($this->isAuthError($e)) {
                error_log('Dropbox: auth error listing subfolders, refreshing token…');
                $this->refreshAccessToken();
                return $fetch(); // throws on second failure — let the caller handle it
            }
            throw $e;
        }
    }

    /**
     * Get a temporary download link for a file (valid for 4 hours)
     * This is much faster than creating shared links!
     */
    public function getTemporaryLink(string $path): ?string
    {
        try {
            // getTemporaryLink() returns a string directly, not an array
            $link = $this->client->getTemporaryLink($path);
            return $link;
        } catch (\Exception $e) {
            // Check if it's an authentication error
            if ($this->isAuthError($e)) {
                error_log("Dropbox: Authentication error getting link, refreshing token and retrying...");
                $this->refreshAccessToken();

                // Retry once after token refresh
                try {
                    return $this->client->getTemporaryLink($path);
                } catch (\Exception $retryError) {
                    error_log("Error getting temporary link after retry for {$path}: " . $retryError->getMessage());
                    return null;
                }
            }
            error_log("Error getting temporary link for {$path}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if an exception is an authentication error
     */
    private function isAuthError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'invalid_access_token') !== false
            || strpos($message, 'expired_access_token') !== false
            || strpos($message, '401 unauthorized') !== false
            || (strpos($message, '401') !== false && strpos($message, 'unauthorized') !== false);
    }

    /**
     * Find the first audio file in a Dropbox folder and return its duration as "M:SS".
     * Uses Dropbox media_info metadata (works for all audio formats).
     * Falls back to MP3 binary parsing when metadata is unavailable.
     */
    public function getFirstAudioDuration(string $folderPath): ?string
    {
        try {
            $result = $this->client->rpcEndpointRequest('files/list_folder', [
                'path'               => $folderPath,
                'recursive'          => false,
                'include_media_info' => true,
            ]);

            $audioPath = null;
            $audioSize = 0;
            $metaSeconds = null;

            foreach (($result['entries'] ?? []) as $entry) {
                if ($entry['.tag'] !== 'file') {
                    continue;
                }
                $ext = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['mp3', 'mp4', 'm4a', 'wav', 'ogg', 'flac', 'aac', 'wma'], true)) {
                    continue;
                }

                $audioPath = $entry['path_lower'];
                $audioSize = $entry['size'];

                // Prefer Dropbox media metadata duration
                $duration = $entry['media_info']['metadata']['duration'] ?? null;
                if ($duration !== null) {
                    $metaSeconds = (int) round($duration / 1000);
                }
                break;
            }

            if ($audioPath === null) {
                return null;
            }

            if ($metaSeconds !== null && $metaSeconds > 0) {
                return sprintf('%d:%02d', intdiv($metaSeconds, 60), $metaSeconds % 60);
            }

            // Fallback: parse MP3 binary header
            $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
            if ($ext !== 'mp3') {
                return null;
            }

            $tempLink = $this->getTemporaryLink($audioPath);
            if ($tempLink === null) {
                return null;
            }

            $ctx = stream_context_create(['http' => [
                'header' => "Range: bytes=0-131071\r\n",
                'timeout' => 10,
            ]]);
            $data = @file_get_contents($tempLink, false, $ctx);
            if ($data === false || strlen($data) < 4) {
                return null;
            }

            $seconds = $this->parseMp3DurationSeconds($data, $audioSize);
            if ($seconds === null || $seconds <= 0) {
                return null;
            }

            return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
        } catch (\Exception $e) {
            return null;
        }
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
     * Copy a file from one location to another in Dropbox
     */
    public function copyFile(string $fromPath, string $toPath): bool
    {
        try {
            $this->client->copy($fromPath, $toPath);
            error_log("Dropbox: Copied $fromPath to $toPath");
            return true;
        } catch (\Exception $e) {
            if ($this->isAuthError($e)) {
                error_log('Dropbox: auth error copying file, refreshing token…');
                $this->refreshAccessToken();
                try {
                    $this->client->copy($fromPath, $toPath);
                    error_log("Dropbox: Successfully copied after token refresh");
                    return true;
                } catch (\Exception $retryError) {
                    error_log("Error copying file after retry: " . $retryError->getMessage());
                    return false;
                }
            }
            error_log("Error copying $fromPath to $toPath: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file from Dropbox
     */
    public function deleteFile(string $path): bool
    {
        try {
            $this->client->delete($path);
            error_log("Dropbox: Deleted $path");
            return true;
        } catch (BadRequest $e) {
            // File doesn't exist, which is fine
            if (strpos($e->getMessage(), 'not_found') !== false) {
                error_log("Dropbox: File not found (already deleted): $path");
                return true;
            }
            error_log("Error deleting $path: " . ($e->dropboxCode ?? $e->getMessage() ?: (string) $e->response->getBody()));
            return false;
        } catch (\Exception $e) {
            if ($this->isAuthError($e)) {
                error_log('Dropbox: auth error deleting file, refreshing token…');
                $this->refreshAccessToken();
                try {
                    $this->client->delete($path);
                    error_log("Dropbox: Successfully deleted after token refresh");
                    return true;
                } catch (\Exception $retryError) {
                    error_log("Error deleting file after retry: " . $retryError->getMessage());
                    return false;
                }
            }
            error_log("Error deleting $path: " . $e->getMessage());
            return false;
        }
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
