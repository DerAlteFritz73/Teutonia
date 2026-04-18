<?php

namespace App\Command;

use App\Service\GoogleCalendarService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-calendar',
    description: 'Google-Kalender-Termine abrufen und im Cache speichern',
)]
class SyncCalendarCommand extends Command
{
    public function __construct(
        private GoogleCalendarService $calendarService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $events = $this->calendarService->refreshCache();

        if (empty($events)) {
            $io->warning('Keine Termine abgerufen (API-Key fehlt oder Fehler beim Abrufen).');
            return Command::SUCCESS;
        }

        $io->success(sprintf('%d Termine erfolgreich im Cache gespeichert.', count($events)));

        return Command::SUCCESS;
    }
}
