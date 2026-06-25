<?php

namespace App\Command;

use App\Entity\SongKeyword;
use App\Service\DropboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-durations',
    description: 'Fill in the cached playing time for songs from Dropbox audio (or a YouTube link)',
)]
class PopulateDurationsCommand extends Command
{
    // Choir pieces top out around a quarter of an hour; anything longer is a
    // bad estimate from the MP3 size-based fallback, so we drop it rather than
    // cache a nonsense playing time.
    private const MAX_PLAUSIBLE_SECONDS = 20 * 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DropboxService $dropboxService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Recompute even when a duration is already cached');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $io->title('Populating song durations' . ($force ? ' (force recompute)' : ''));

        $songs = $this->entityManager->getRepository(SongKeyword::class)->findBy([], ['songName' => 'ASC']);

        $filled = $skipped = $failed = 0;
        foreach ($songs as $song) {
            if (!$force && !empty($song->getDuration())) {
                $skipped++;
                continue;
            }

            $duration = $this->computeDuration($song);
            if ($duration !== null) {
                $song->setDuration($duration);
                $this->entityManager->flush();
                $filled++;
                $io->writeln(sprintf('  <info>%s</info>  %s', $duration, (string) $song->getSongName()));
            } else {
                $failed++;
                $io->writeln(sprintf('  <comment>--:--</comment>  %s', (string) $song->getSongName()));
            }
        }

        $io->success(sprintf('Done. Filled %d, no source %d, already set %d.', $filled, $failed, $skipped));

        return Command::SUCCESS;
    }

    /**
     * Mirrors LiederlisteController::fetchDuration(): Dropbox audio first, then YouTube links.
     */
    private function computeDuration(SongKeyword $song): ?string
    {
        $folderPath = $song->getAktuelleDropboxlink() ?? $song->getDropboxlink();
        if ($folderPath !== null) {
            $duration = $this->plausible($this->dropboxService->getFirstAudioDuration($folderPath));
            if ($duration !== null) {
                return $duration;
            }
        }

        foreach ($song->getLinks() as $link) {
            $duration = $this->plausible($this->getYoutubeDuration($link->getUrl()));
            if ($duration !== null) {
                return $duration;
            }
        }

        return null;
    }

    /** Reject obviously broken "M:SS" estimates (see MAX_PLAUSIBLE_SECONDS). */
    private function plausible(?string $duration): ?string
    {
        if ($duration === null || !preg_match('/^(\d+):(\d{2})$/', $duration, $m)) {
            return null;
        }
        $seconds = (int) $m[1] * 60 + (int) $m[2];
        return ($seconds > 0 && $seconds < self::MAX_PLAUSIBLE_SECONDS) ? $duration : null;
    }

    private function getYoutubeDuration(string $url): ?string
    {
        if (!preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return null;
        }

        $ctx = stream_context_create(['http' => [
            'timeout'    => 8,
            'user_agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ]]);

        $html = @file_get_contents('https://www.youtube.com/watch?v=' . $m[1], false, $ctx);
        if ($html === false) {
            return null;
        }

        if (preg_match('/"lengthSeconds"\s*:\s*"(\d+)"/', $html, $m)) {
            $s = (int) $m[1];
            return $s > 0 ? sprintf('%d:%02d', intdiv($s, 60), $s % 60) : null;
        }

        return null;
    }
}
