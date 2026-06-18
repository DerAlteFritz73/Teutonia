<?php

namespace App\Command;

use App\Service\DropboxService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:refresh-dropbox-cache',
    description: 'Re-fetch the Dropbox folder structure cache (Noten + Aktuelle Proben) without creating songs',
)]
class RefreshDropboxCacheCommand extends Command
{
    private const FOLDERS = [
        // Whole-tree cache read by the member file browser (getFilesForFolder).
        '/Chorgemeinschaft Teutonia',
        '/Chorgemeinschaft Teutonia/Noten',
        '/Chorgemeinschaft Teutonia/Aktuelle Proben',
    ];

    public function __construct(private DropboxService $dropboxService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Refreshing Dropbox structure cache');

        // Drop the stale structure_*.json files, then rebuild each one with a
        // fresh fetch (useCache=false also rewrites the cache file).
        $this->dropboxService->clearFileCache();

        foreach (self::FOLDERS as $folder) {
            try {
                $tree    = $this->dropboxService->getFileStructure($folder, false);
                $entries = count($tree['_subfolders'] ?? []);
                $io->text(sprintf('Refreshed %s (%d top-level folders)', $folder, $entries));
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to refresh %s: %s', $folder, $e->getMessage()));
                return Command::FAILURE;
            }
        }

        $io->success('Dropbox cache refreshed.');

        return Command::SUCCESS;
    }
}
