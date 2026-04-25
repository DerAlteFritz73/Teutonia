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

    public function __construct(
        string $dropboxAccessToken,
        string $dropboxRefreshToken,
        string $dropboxAppKey,
        string $dropboxAppSecret
    ) {
        $this->refreshToken = $dropboxRefreshToken;
        $this->appKey = $dropboxAppKey;
        $this->appSecret = $dropboxAppSecret;
        $this->tokenCacheFile = sys_get_temp_dir() . '/dropbox_token_cache.json';

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
        $cacheFile = sys_get_temp_dir() . '/dropbox_cache_' . md5($folderPath) . '.json';
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
        foreach (glob(sys_get_temp_dir() . '/dropbox_cache_*.json') as $file) {
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
        $cacheFile = sys_get_temp_dir() . '/dropbox_cache_' . md5($basePath) . '.json';

        if (file_exists($cacheFile)) {
            $tree = json_decode(file_get_contents($cacheFile), true) ?: [];
        } else {
            // Warm the cache and try again
            $tree = $this->getFileStructure($basePath);
        }

        // Strip base prefix and split into path segments
        $relative = ltrim(str_replace($basePath, '', $folderPath), '/');
        $parts    = explode('/', $relative);

        // Navigate: first segment is a top-level key, subsequent ones are _subfolders
        $node = $tree;
        foreach ($parts as $i => $part) {
            if ($i === 0) {
                $node = $node[$part] ?? null;
            } else {
                $node = ($node['_subfolders'] ?? [])[$part] ?? null;
            }
            if ($node === null) {
                return [];
            }
        }

        return [
            'files'          => $node['_files'] ?? [],
            'hasSubfolders'  => !empty($node['_subfolders']),
        ];
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
     * Get or create a shared link for a file (SLOW - use getTemporaryLink instead!)
     */
    public function getSharedLink(string $path): ?string
    {
        try {
            // Try to get existing shared links
            $links = $this->client->listSharedLinks($path);

            if (!empty($links['links'])) {
                // Convert the link to a direct download link
                return str_replace('?dl=0', '?dl=1', $links['links'][0]['url']);
            }

            // Create a new shared link if none exists
            $result = $this->client->createSharedLinkWithSettings($path, [
                'requested_visibility' => 'public'
            ]);

            // Convert to direct download link
            return str_replace('?dl=0', '?dl=1', $result['url']);

        } catch (BadRequest $e) {
            // If link already exists, try to retrieve it again
            if (strpos($e->getMessage(), 'shared_link_already_exists') !== false) {
                try {
                    $links = $this->client->listSharedLinks($path);
                    if (!empty($links['links'])) {
                        return str_replace('?dl=0', '?dl=1', $links['links'][0]['url']);
                    }
                } catch (\Exception $e) {
                    error_log("Error getting shared link: " . $e->getMessage());
                }
            }
            return null;
        } catch (\Exception $e) {
            error_log("Error creating shared link for {$path}: " . $e->getMessage());
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
