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
    description: 'Populate song keywords from Dropbox file names',
)]
class PopulateSongKeywordsCommand extends Command
{
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

        // Clear existing data
        $io->section('Clearing existing data...');
        $this->entityManager->createQuery('DELETE FROM App\Entity\SongKeyword')->execute();

        // Process Noten folder
        $io->section('Processing Noten folder...');
        $this->processFolder('/Chorgemeinschaft Teutonia/Noten', 'Noten', $io);

        // Process Aktuelle Proben folder
        $io->section('Processing Aktuelle Proben folder...');
        $this->processFolder('/Chorgemeinschaft Teutonia/Aktuelle Proben', 'Aktuelle Proben', $io);

        $this->entityManager->flush();

        $io->success('Song keywords populated successfully!');

        return Command::SUCCESS;
    }

    private function processFolder(string $dropboxPath, string $folderName, SymfonyStyle $io): void
    {
        $files = $this->dropboxService->getFileStructure($dropboxPath, false);
        $count = 0;

        foreach ($files as $songFolderName => $folderData) {
            if ($songFolderName === '_root_files' || !isset($folderData['_type'])) {
                continue;
            }

            // Extract composer from folder name (first part before dash or entire name)
            $composer = $this->extractComposer($songFolderName);

            if (!$composer) {
                continue;
            }

            // Check if this song already exists
            $existingSong = $this->entityManager->getRepository(SongKeyword::class)
                ->findOneBy(['songName' => $songFolderName, 'folder' => $folderName]);

            if (!$existingSong) {
                $songKeyword = new SongKeyword();
                $songKeyword->setSongName($songFolderName);
                $songKeyword->setComposer($composer);
                $songKeyword->setFolder($folderName);
                $songKeyword->setKeywords([$composer]); // For now, just use composer as keyword

                $this->entityManager->persist($songKeyword);
                $count++;
            }
        }

        $io->text("Added $count songs from $folderName");
    }

    private function extractComposer(string $songName): ?string
    {
        // Try to extract composer name (part before dash)
        if (preg_match('/^([^-]+)\s*-/', $songName, $matches)) {
            return trim($matches[1]);
        }

        // If no dash, use first 2-3 words as composer
        $words = explode(' ', $songName);
        if (count($words) >= 2) {
            return implode(' ', array_slice($words, 0, min(2, count($words))));
        }

        return trim($songName);
    }
}
