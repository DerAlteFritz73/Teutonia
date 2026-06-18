<?php

namespace App\Command;

use App\Entity\SongKeyword;
use App\Service\DropboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-song-keywords',
    description: 'Create missing song entries from Dropbox folders ("[title] #[composer]" naming)',
)]
class PopulateSongKeywordsCommand extends Command
{
    private const NOTEN_PATH = '/Chorgemeinschaft Teutonia/Noten';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DropboxService $dropboxService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Populating Song Keywords from Dropbox');

        // Preload existing songs once so we never create a duplicate of a song
        // that already exists — either by Dropbox path or by normalised title.
        $linkedPaths   = []; // lowercased dropbox path => true
        $existingNorms = []; // normalised song title => true
        foreach ($this->entityManager->getRepository(SongKeyword::class)->findAll() as $song) {
            if ($song->getDropboxlink()) {
                $linkedPaths[mb_strtolower(rtrim($song->getDropboxlink(), '/'))] = true;
            }
            if ($song->getAktuelleDropboxlink()) {
                $linkedPaths[mb_strtolower(rtrim($song->getAktuelleDropboxlink(), '/'))] = true;
            }
            $existingNorms[$this->normalize($song->getSongName())] = true;
        }

        // Only the Noten folder is a source of songs. "Aktuelle Proben" is a
        // working copy managed solely by the "Aktuell in Proben" checkbox on the
        // songs page (AktuelleProbenSyncService), so it is never imported here.
        $io->section('Processing Noten folder...');
        $this->processFolder(self::NOTEN_PATH, 'Notenverzeichnis', $linkedPaths, $existingNorms, $io);

        $this->entityManager->flush();

        $io->success('Song keywords populated successfully!');

        return Command::SUCCESS;
    }

    /**
     * @param array<string,bool> $linkedPaths   shared across calls, updated in place
     * @param array<string,bool> $existingNorms shared across calls, updated in place
     */
    private function processFolder(
        string $dropboxPath,
        string $folderLabel,
        array &$linkedPaths,
        array &$existingNorms,
        SymfonyStyle $io
    ): void {
        $files = $this->dropboxService->getFileStructure($dropboxPath, false);
        $count = 0;

        foreach ($files as $folderName => $folderData) {
            if (str_starts_with((string) $folderName, '_') || !isset($folderData['_type'])) {
                continue;
            }

            [$title, $composer] = $this->parseFolderName($folderName);
            $path = $dropboxPath . '/' . $folderName;

            // Skip folders already linked to a song, or whose title already exists.
            if (isset($linkedPaths[mb_strtolower($path)])) {
                continue;
            }
            $norm = $this->normalize($title);
            if (isset($existingNorms[$norm])) {
                continue;
            }

            $song = new SongKeyword();
            $song->setSongName($title);
            $song->setComposer($composer);
            $song->setFolder($folderLabel);
            $song->setKeywords($composer !== null ? [$composer] : []);
            $song->setDropboxlink($path);
            $this->entityManager->persist($song);

            // Register immediately so duplicates inside this run are skipped too.
            $linkedPaths[mb_strtolower($path)] = true;
            $existingNorms[$norm]              = true;
            $count++;
        }

        $io->text("Added $count songs from $folderLabel");
    }

    /**
     * Split "[title] #[composer]" into [title, composer|null].
     * Folders without " #" are treated as a bare title.
     *
     * @return array{0:string,1:?string}
     */
    private function parseFolderName(string $folderName): array
    {
        if (str_contains($folderName, ' #')) {
            [$titlePart, $composerPart] = explode(' #', $folderName, 2);
            $composer = trim($composerPart);
            return [trim($titlePart), $composer !== '' ? $composer : null];
        }

        return [trim($folderName), null];
    }

    /**
     * Mirror of AdminController::normalizeForSync — "/" and "," are treated as
     * equivalent (Dropbox replaces "/" with "," in folder names) so titles match
     * regardless of which character is used.
     */
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(['/', ','], ' ', $s);
        $map = [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'à' => 'a', 'â' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'ê' => 'e', 'é' => 'e', 'ë' => 'e',
            'ì' => 'i', 'î' => 'i', 'í' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ô' => 'o', 'ó' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ù' => 'u', 'û' => 'u', 'ú' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            "\xc2\x85" => '', "\xc2\x92" => '', "\xe2\x80\x99" => '',
        ];
        $s = str_replace(array_keys($map), array_values($map), $s);
        $s = preg_replace('/[^\p{L}\p{N} ]/u', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
