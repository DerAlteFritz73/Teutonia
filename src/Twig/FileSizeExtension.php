<?php

namespace App\Twig;

use App\Service\DropboxService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FileSizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_bytes', [$this, 'formatBytes']),
            new TwigFilter('count_all_files', [$this, 'countAllFiles']),
            new TwigFilter('count_pdf_files', [$this, 'countPdfFiles']),
            new TwigFilter('count_audio_files', [$this, 'countAudioFiles']),
        ];
    }

    public function formatBytes(int $bytes): string
    {
        return DropboxService::formatFileSize($bytes);
    }

    /**
     * Recursively count all files in a folder structure including subfolders
     */
    public function countAllFiles(array $folderData): int
    {
        $count = 0;

        // Count direct files
        if (isset($folderData['_files']) && is_array($folderData['_files'])) {
            $count += count($folderData['_files']);
        }

        // Recursively count files in subfolders
        if (isset($folderData['_subfolders']) && is_array($folderData['_subfolders'])) {
            foreach ($folderData['_subfolders'] as $subfolder) {
                $count += $this->countAllFiles($subfolder);
            }
        }

        return $count;
    }

    /**
     * Recursively count PDF files in a folder structure including subfolders
     */
    public function countPdfFiles(array $folderData): int
    {
        return $this->countFilesByType($folderData, 'pdf');
    }

    /**
     * Recursively count audio files in a folder structure including subfolders
     */
    public function countAudioFiles(array $folderData): int
    {
        return $this->countFilesByType($folderData, 'audio');
    }

    /**
     * Recursively count files by type in a folder structure including subfolders
     */
    private function countFilesByType(array $folderData, string $type): int
    {
        $count = 0;

        // Count direct files of the specified type
        if (isset($folderData['_files']) && is_array($folderData['_files'])) {
            foreach ($folderData['_files'] as $file) {
                if (isset($file['type']) && $file['type'] === $type) {
                    $count++;
                }
            }
        }

        // Recursively count files in subfolders
        if (isset($folderData['_subfolders']) && is_array($folderData['_subfolders'])) {
            foreach ($folderData['_subfolders'] as $subfolder) {
                $count += $this->countFilesByType($subfolder, $type);
            }
        }

        return $count;
    }
}
