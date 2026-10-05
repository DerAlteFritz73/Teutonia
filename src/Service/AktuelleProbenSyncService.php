<?php

namespace App\Service;

use App\Entity\SongKeyword;
use Doctrine\ORM\EntityManagerInterface;

class AktuelleProbenSyncService
{
    private const AKTUELLE_PROBEN_BASE = '/Chorgemeinschaft Teutonia/Aktuelle Proben';

    public function __construct(
        private ArchiveService $archive,
        private EntityManagerInterface $entityManager,
        private string $appEnv,
    ) {
    }

    /**
     * Mark a song as "Aktuell in Proben".
     *
     * The boolean flag is what drives the member "Aktuelle Proben" page, so it is
     * always set (the toggle never fails on archive issues). As a best effort we
     * also copy the song's folder into the dedicated "Aktuelle Proben" archive
     * folder; when that fails (e.g. the folder isn't writable) the member page
     * simply falls back to the song's Noten folder
     * (`aktuelleArchivePath ?? archivePath`).
     */
    public function pushSongToAktuelleProben(SongKeyword $song): bool
    {
        $song->setIsAktuelleProben(true);

        // Best-effort copy into the dedicated "Aktuelle Proben" archive folder
        // (mirrors the source folder name so the "[title] #[composer]" convention is
        // kept). Only possible when the song already has a Noten folder in the archive
        // and hasn't been copied yet; a song without one yet is still flagged, and
        // the member page falls back to the Noten folder later.
        //
        // Writes are prod-only: dev reads the same archive as prod, so copying here
        // would collide with prod. On dev the flag is still set and the member page
        // falls back to the Noten folder.
        if ($this->appEnv === 'prod' && $song->getArchivePath() && !$song->getAktuelleArchivePath()) {
            $sourcePath   = rtrim($song->getArchivePath(), '/');
            $folderName   = substr($sourcePath, strrpos($sourcePath, '/') + 1);
            $targetFolder = self::AKTUELLE_PROBEN_BASE . '/' . $folderName;
            if ($this->archive->copyFolder($sourcePath, $targetFolder)) {
                $song->setAktuelleArchivePath($targetFolder);
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
     * effort it also moves the dedicated "Aktuelle Proben" archive copy to the
     * archive's trash; if that isn't possible (e.g. not writable) the path is left in place
     * and the orphaned folder can be cleaned up later — the flag being off is
     * enough to hide the song from the member page.
     */
    public function removeSongFromAktuelleProben(SongKeyword $song): bool
    {
        $song->setIsAktuelleProben(false);

        // Deletes are prod-only for the same reason as the copy above — dev shares
        // prod's archive, so a delete here would remove the folder prod relies on.
        $folderPath = $song->getAktuelleArchivePath();
        if ($this->appEnv === 'prod' && $folderPath && $this->archive->moveToTrash($folderPath)) {
            $song->setAktuelleArchivePath(null);
        }

        $this->entityManager->persist($song);
        $this->entityManager->flush();

        return true;
    }
}
