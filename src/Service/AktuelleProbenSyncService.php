<?php

namespace App\Service;

use App\Entity\SongKeyword;
use App\Repository\SongKeywordRepository;
use Doctrine\ORM\EntityManagerInterface;

class AktuelleProbenSyncService
{
    private const AKTUELLE_PROBEN_BASE = '/Chorgemeinschaft Teutonia/Aktuelle Proben';

    public function __construct(
        private DropboxService $dropboxService,
        private EntityManagerInterface $entityManager,
        private SongKeywordRepository $songRepository,
    ) {
    }

    /**
     * Push a song to the "Aktuelle Proben" folder on Dropbox
     */
    public function pushSongToAktuelleProben(SongKeyword $song): bool
    {
        if (!$song->getDropboxlink()) {
            error_log("Cannot push song {$song->getId()}: no dropboxlink");
            return false;
        }

        $sourcePath = $song->getDropboxlink();
        $songName = $song->getSongName();
        $targetFolder = self::AKTUELLE_PROBEN_BASE . '/' . $songName;

        // Get all files from the source folder
        $files = $this->dropboxService->listFolderFiles($sourcePath);

        if (empty($files)) {
            error_log("No files found in source folder: $sourcePath");
            return false;
        }

        $allSuccess = true;
        foreach ($files as $file) {
            $fromPath = $file['path'];
            $toPath = $targetFolder . '/' . $file['name'];

            if (!$this->dropboxService->copyFile($fromPath, $toPath)) {
                error_log("Failed to copy file: $fromPath to $toPath");
                $allSuccess = false;
            }
        }

        if ($allSuccess) {
            $song->setIsAktuelleProben(true);
            $song->setAktuelleDropboxlink($targetFolder);
            $this->entityManager->persist($song);
            $this->entityManager->flush();
            error_log("Song {$song->getId()} pushed to Aktuelle Proben");
        }

        return $allSuccess;
    }

    /**
     * Remove a song from the "Aktuelle Proben" folder on Dropbox
     */
    public function removeSongFromAktuelleProben(SongKeyword $song): bool
    {
        if (!$song->getAktuelleDropboxlink()) {
            // Already not in Aktuelle Proben
            $song->setIsAktuelleProben(false);
            $this->entityManager->persist($song);
            $this->entityManager->flush();
            return true;
        }

        $folderPath = $song->getAktuelleDropboxlink();
        $files = $this->dropboxService->listFolderFiles($folderPath);

        $allSuccess = true;
        foreach ($files as $file) {
            if (!$this->dropboxService->deleteFile($file['path'])) {
                error_log("Failed to delete file: {$file['path']}");
                $allSuccess = false;
            }
        }

        if ($allSuccess) {
            $song->setIsAktuelleProben(false);
            $song->setAktuelleDropboxlink(null);
            $this->entityManager->persist($song);
            $this->entityManager->flush();
            error_log("Song {$song->getId()} removed from Aktuelle Proben");
        }

        return $allSuccess;
    }

    /**
     * Synchronize all songs: push those marked as aktuelle, remove those not marked
     */
    public function syncAllAktuelleProben(): void
    {
        $allSongs = $this->songRepository->findAll();

        foreach ($allSongs as $song) {
            if ($song->isIsAktuelleProben()) {
                // Should be in Aktuelle Proben
                if (!$song->getAktuelleDropboxlink()) {
                    // Not yet pushed
                    $this->pushSongToAktuelleProben($song);
                }
            } else {
                // Should NOT be in Aktuelle Proben
                if ($song->getAktuelleDropboxlink()) {
                    // Currently in Aktuelle Proben, remove it
                    $this->removeSongFromAktuelleProben($song);
                }
            }
        }
    }
}
