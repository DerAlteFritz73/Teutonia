<?php

namespace App\Service;

use App\Entity\SongKeyword;
use Doctrine\ORM\EntityManagerInterface;

class AktuelleProbenSyncService
{
    private const AKTUELLE_PROBEN_BASE = '/Chorgemeinschaft Teutonia/Aktuelle Proben';

    public function __construct(
        private DropboxService $dropboxService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Mark a song as "Aktuell in Proben".
     *
     * The boolean flag is what drives the member "Aktuelle Proben" page, so it is
     * always set (the toggle never fails on Dropbox issues). As a best effort we
     * also copy the song's folder into the dedicated "Aktuelle Proben" Dropbox
     * folder — but that needs the `files.content.write` scope; when it isn't
     * available the member page simply falls back to the song's Noten folder
     * (`aktuelleDropboxlink ?? dropboxlink`).
     */
    public function pushSongToAktuelleProben(SongKeyword $song): bool
    {
        if (!$song->getDropboxlink()) {
            error_log("Cannot push song {$song->getId()}: no dropboxlink");
            return false;
        }

        $song->setIsAktuelleProben(true);

        // Best-effort copy (mirrors the source folder name so the "[title] #[composer]"
        // convention is kept). Only attempt it if there is no copy yet.
        if (!$song->getAktuelleDropboxlink()) {
            $sourcePath   = rtrim($song->getDropboxlink(), '/');
            $folderName   = substr($sourcePath, strrpos($sourcePath, '/') + 1);
            $targetFolder = self::AKTUELLE_PROBEN_BASE . '/' . $folderName;
            if ($this->dropboxService->copyFile($sourcePath, $targetFolder)) {
                $song->setAktuelleDropboxlink($targetFolder);
            }
        }

        $this->entityManager->persist($song);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Unmark a song as "Aktuell in Proben".
     *
     * Clears the flag unconditionally (so unchecking always works). As a best
     * effort it also deletes the dedicated "Aktuelle Proben" Dropbox copy; if
     * that isn't possible (e.g. missing write scope) the link is left in place
     * and the orphaned folder can be cleaned up later — the flag being off is
     * enough to hide the song from the member page.
     */
    public function removeSongFromAktuelleProben(SongKeyword $song): bool
    {
        $song->setIsAktuelleProben(false);

        $folderPath = $song->getAktuelleDropboxlink();
        if ($folderPath && $this->dropboxService->deleteFile($folderPath)) {
            $song->setAktuelleDropboxlink(null);
        }

        $this->entityManager->persist($song);
        $this->entityManager->flush();

        return true;
    }
}
