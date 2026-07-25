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
    public function __construct(private DropboxService $dropboxService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Refreshing Dropbox structure cache');

        try {
            // Drop the stale structure_*.json files, then rebuild each one with a
            // fresh fetch (useCache=false also rewrites the cache file).
            $counts = $this->dropboxService->refreshFileCache();
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to refresh Dropbox cache: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        foreach ($counts as $folder => $entries) {
            $io->text(sprintf('Refreshed %s (%d top-level entries)', $folder, $entries));
        }

        $io->success('Dropbox cache refreshed.');

        return Command::SUCCESS;
    }
}
