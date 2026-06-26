<?php

namespace App\Controller\Admin;

use App\Entity\Konzert;
use App\Entity\Post;
use App\Entity\SongKeyword;
use App\Entity\Style;
use App\Entity\User;
use App\Form\KonzertType;
use App\Form\PostType;
use App\Form\SongKeywordType;
use App\Form\SongLinkType;
use App\Form\StyleType;
use App\Form\UserType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Repository\BlacklistedIpRepository;
use App\Repository\KonzertRepository;
use App\Repository\PostRepository;
use App\Repository\SongKeywordRepository;
use App\Repository\ScoreSyncAnchorsRepository;
use App\Entity\ScoreSyncAnchors;
use App\Repository\StyleRepository;
use App\Repository\UserRepository;
use App\Service\DropboxService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/cache/clear', name: 'admin_cache_clear', methods: ['POST'])]
    public function clearCache(
        Request $request,
        #[Autowire(service: 'cache.app')] CacheInterface $appCache,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('cache_clear', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Ungültiges CSRF-Token – bitte Seite neu laden und erneut versuchen.'], Response::HTTP_FORBIDDEN);
        }

        $cleared = [];
        $errors  = [];

        // Symfony app cache pools (file-based in prod)
        try {
            $appCache->clear();
            $cleared[] = 'symfony-cache';
        } catch (\Throwable $e) {
            $errors[] = 'symfony-cache: ' . $e->getMessage();
        }

        // OPcache (compiled PHP bytecode)
        if (function_exists('opcache_reset')) {
            if (opcache_reset()) {
                $cleared[] = 'opcache';
            } else {
                $errors[] = 'opcache: reset() returned false';
            }
        }

        // APCu user cache
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
            $cleared[] = 'apcu';
        }

        if ($errors) {
            return new JsonResponse(['error' => implode("\n", $errors), 'cleared' => $cleared], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => true, 'cleared' => $cleared]);
    }

    #[Route('', name: 'admin_dashboard')]
    public function dashboard(
        UserRepository $userRepository,
        PostRepository $postRepository,
        SongKeywordRepository $songRepository,
        KonzertRepository $konzertRepository,
        \App\Repository\LiederlisteRepository $liederlisteRepository,
        #[Autowire('%kernel.logs_dir%')] string $logsDir,
        #[Autowire('%kernel.environment%')] string $env,
    ): Response {
        $logStats = $this->getLogStats("$logsDir/$env.log");

        return $this->render('admin/dashboard.html.twig', [
            'userCount'           => $userRepository->countMembers(),
            'postCount'           => $postRepository->countAll(),
            'songCount'           => $songRepository->countAllIncludingMovements(),
            'konzertCount'        => $konzertRepository->countAll(),
            'liederlistenCount'   => $liederlisteRepository->countAll(),
            'memberLoginSum'          => $userRepository->sumLoginCountExcluding('joel'),
            'memberUniqueLoginCount'  => $userRepository->countUsersWithLoginsExcluding('joel'),
            'memberLastLoginAt'       => $userRepository->lastLoginAtExcluding('joel'),
            'logErrorCount'  => $logStats['errorCount'],
            'logLastErrorAt' => $logStats['lastErrorAt'],
        ]);
    }

    #[Route('/logs', name: 'admin_logs')]
    public function logs(
        Request $request,
        #[Autowire('%kernel.logs_dir%')] string $logsDir,
        #[Autowire('%kernel.environment%')] string $env,
    ): Response {
        $logEnv = $request->query->get('env', $env);
        if (!in_array($logEnv, ['dev', 'prod', 'test'], true)) {
            $logEnv = $env;
        }
        $logFile  = "$logsDir/$logEnv.log";
        $minLevel = $request->query->get('level', 'WARNING');
        $levels   = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
        if (!in_array($minLevel, $levels, true)) {
            $minLevel = 'WARNING';
        }

        $rawLines    = $this->readLastLines($logFile, 3000);
        $entries     = [];
        $infoIdx     = array_search('INFO', $levels);
        $minLevelIdx = array_search($minLevel, $levels);
        $cutoff      = new \DateTimeImmutable('-2 weeks');
        foreach (array_reverse($rawLines) as $line) {
            $entry = $this->parseLogLine($line);
            if ($entry === null) {
                continue;
            }
            if ($entry['timestamp'] < $cutoff) {
                continue;
            }
            $entryIdx = array_search($entry['level'], $levels);
            $threshold = ($entry['channel'] === 'security') ? min($minLevelIdx, $infoIdx) : $minLevelIdx;
            if ($entryIdx < $threshold) {
                continue;
            }
            $entries[] = $entry;
        }

        return $this->render('admin/logs.html.twig', [
            'entries'  => $entries,
            'minLevel' => $minLevel,
            'levels'   => $levels,
            'logFile'  => basename($logFile),
            'logEnv'   => $logEnv,
        ]);
    }

    #[Route('/logs/blacklist-ip', name: 'admin_blacklist_ip', methods: ['POST'])]
    public function blacklistIp(
        Request $request,
        BlacklistedIpRepository $blacklistedIpRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('blacklist_ip', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $ipAddress = trim($request->request->get('ip', ''));
        $reason = trim($request->request->get('reason', ''));

        if (!$ipAddress || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return new JsonResponse(['error' => 'Invalid IP address'], 400);
        }

        // Check if already blacklisted
        if ($blacklistedIpRepository->isBlacklisted($ipAddress)) {
            return new JsonResponse(['error' => 'IP already blacklisted'], 409);
        }

        $blacklistedIpRepository->addIp($ipAddress, $reason ?: null);

        return new JsonResponse(['success' => true, 'message' => "IP $ipAddress blacklisted"]);
    }

    #[Route('/blacklist-ip-from-email/{token}', name: 'blacklist_ip_from_email', methods: ['GET'])]
    public function blacklistIpFromEmail(
        string $token,
        Request $request,
        BlacklistedIpRepository $blacklistedIpRepository,
    ): Response {
        // Verify token format (token should be: hash(ip + secret + timestamp)
        $ipAddress = $request->query->get('ip');

        if (!$ipAddress || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return $this->render('admin/blacklist_error.html.twig', [
                'error' => 'Invalid IP address'
            ]);
        }

        // Verify token (token should match: hash of ip + secret + date)
        $expectedToken = hash('sha256', $ipAddress . $_ENV['APP_SECRET'] . date('Y-m-d'));
        if (!hash_equals($token, $expectedToken)) {
            return $this->render('admin/blacklist_error.html.twig', [
                'error' => 'Invalid or expired token'
            ]);
        }

        // Check if already blacklisted
        if ($blacklistedIpRepository->isBlacklisted($ipAddress)) {
            return $this->render('admin/blacklist_success.html.twig', [
                'message' => "IP $ipAddress ist bereits blockiert",
                'ip' => $ipAddress
            ]);
        }

        // Blacklist the IP
        $blacklistedIpRepository->addIp($ipAddress, 'Failed login attempt (via email link)');

        return $this->render('admin/blacklist_success.html.twig', [
            'message' => "IP $ipAddress wurde erfolgreich blockiert",
            'ip' => $ipAddress
        ]);
    }

    private function getLogStats(string $logFile): array
    {
        $errorCount  = 0;
        $lastErrorAt = null;
        $errorLevels = ['WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

        foreach ($this->readLastLines($logFile, 500) as $line) {
            $entry = $this->parseLogLine($line);
            if ($entry === null) {
                continue;
            }
            if (in_array($entry['level'], $errorLevels, true)) {
                $errorCount++;
                if ($lastErrorAt === null || $entry['timestamp'] > $lastErrorAt) {
                    $lastErrorAt = $entry['timestamp'];
                }
            }
        }

        return ['errorCount' => $errorCount, 'lastErrorAt' => $lastErrorAt];
    }

    private function readLastLines(string $filePath, int $maxLines): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $startLine  = max(0, $totalLines - $maxLines);
        $file->seek($startLine);

        $lines = [];
        while (!$file->eof()) {
            $line = $file->fgets();
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function parseLogLine(string $line): ?array
    {
        // Format: [2026-01-01T12:00:00.000000+00:00] channel.LEVEL: message {context} {extra}
        if (!preg_match(
            '/^\[(\d{4}-\d{2}-\d{2}T[\d:.]+[+-]\d{2}:\d{2})\] (\w+)\.(\w+): (.*?)(\{.*\})\s+(\[.*\])\s*$/s',
            trim($line),
            $m
        )) {
            // Fallback: simpler match without context
            if (!preg_match('/^\[(\d{4}-\d{2}-\d{2}T[\d:.]+[+-]\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/', trim($line), $m)) {
                return null;
            }
            return [
                'timestamp' => new \DateTimeImmutable($m[1]),
                'channel'   => strtolower($m[2]),
                'level'     => strtoupper($m[3]),
                'message'   => trim($m[4]),
                'context'   => '',
            ];
        }

        // Extract short message (before the first { or end of line)
        $message = trim($m[4]);
        $context = $m[5] !== '{}' ? $m[5] : '';

        // If context contains an exception, extract the short message from it
        if ($context && str_contains($context, '"exception"')) {
            $decoded = json_decode($context, true);
            if (isset($decoded['exception']) && is_string($decoded['exception'])) {
                $context = explode("\n", $decoded['exception'])[0];
            }
        }

        return [
            'timestamp' => new \DateTimeImmutable($m[1]),
            'channel'   => strtolower($m[2]),
            'level'     => strtoupper($m[3]),
            'message'   => $message,
            'context'   => $context,
        ];
    }

    // ==================== SONGS ====================

    #[Route('/songs', name: 'admin_songs')]
    public function songs(SongKeywordRepository $songRepository, Request $request): Response
    {
        $search       = $request->query->get('q', '');
        $page         = max(1, $request->query->getInt('page', 1));
        $limit        = 25;
        $allowedSorts = ['songName', 'etikett', 'composer', 'arrangeur', 'compositionYear', 'latestKonzert', 'dropboxlink'];
        $sort         = in_array($request->query->get('sort'), $allowedSorts, true)
                            ? $request->query->get('sort')
                            : 'songName';
        $dir          = strtoupper($request->query->get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if (trim($search) !== '') {
            $songs = $songRepository->searchPaginated($search, $page, $limit, $sort, $dir);
            $total = $songRepository->countSearch($search);
        } else {
            $songs = $songRepository->findPaginated($page, $limit, $sort, $dir);
            $total = $songRepository->countAll();
        }

        $totalPages = max(1, (int) ceil($total / $limit));
        $page       = min($page, $totalPages);

        return $this->render('admin/songs/index.html.twig', [
            'songs'       => $songs,
            'search'      => $search,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'totalAll'    => $songRepository->countAllIncludingMovements(),
            'sort'        => $sort,
            'dir'         => $dir,
        ]);
    }

    #[Route('/songs/export-xlsx', name: 'admin_songs_export_xlsx')]
    public function exportSongsXlsx(SongKeywordRepository $songRepository): Response
    {
        $songs = $songRepository->createQueryBuilder('s')
            ->leftJoin('s.styles', 'st')->addSelect('st')
            ->leftJoin('s.parent', 'p')->addSelect('p')
            ->where('s.etikett IS NULL OR s.etikett = :empty')
            ->setParameter('empty', '')
            ->getQuery()->getResult();

        usort($songs, function ($a, $b) {
            $aKey = ($a->getParent()?->getSongName() ?? $a->getSongName()) . "\0" . $a->getSongName();
            $bKey = ($b->getParent()?->getSongName() ?? $b->getSongName()) . "\0" . $b->getSongName();
            return strnatcasecmp($aKey, $bKey);
        });

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Songs');

        foreach (['A' => 'Titel', 'B' => 'Komponist', 'C' => 'Übergeordneter Titel', 'D' => 'Stile'] as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
        }

        // Set column widths - Title gets the most space
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(16);

        $row = 2;
        foreach ($songs as $song) {
            $styles = $song->getStyles()->map(fn($s) => $s->getName())->toArray();
            $sheet->setCellValue('A' . $row, $song->getSongName());
            $sheet->setCellValue('B' . $row, $song->getComposer());
            $sheet->setCellValue('C' . $row, $song->getParent()?->getSongName());
            $sheet->setCellValue('D' . $row, implode(', ', $styles));
            $row++;
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="songs_export.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[Route('/songs/export-odt', name: 'admin_songs_export_odt')]
    public function exportSongsOdt(SongKeywordRepository $songRepository): Response
    {
        $songs = $songRepository->createQueryBuilder('s')
            ->leftJoin('s.styles', 'st')->addSelect('st')
            ->leftJoin('s.parent', 'p')->addSelect('p')
            ->where('s.etikett IS NULL OR s.etikett = :empty')
            ->setParameter('empty', '')
            ->getQuery()->getResult();

        usort($songs, function ($a, $b) {
            $aKey = ($a->getParent()?->getSongName() ?? $a->getSongName()) . "\0" . $a->getSongName();
            $bKey = ($b->getParent()?->getSongName() ?? $b->getSongName()) . "\0" . $b->getSongName();
            return strnatcasecmp($aKey, $bKey);
        });

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('de-DE'));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
            'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
            'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
            'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
        ]);

        $phpWord->addTitleStyle(1, ['name' => 'Calibri', 'size' => 16, 'bold' => true], ['spaceAfter' => 240]);
        $section->addTitle('Lieder ohne Etikett', 1);

        $tableStyle = array('borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellSpacing' => 0);
        $table = $section->addTable($tableStyle);
        $table->addRow();
        $headerWidths = [3000, 2000, 2200, 1500]; // Title gets the most space
        foreach (['Titel', 'Komponist', 'Übergeordneter Titel', 'Stile'] as $i => $header) {
            $table->addCell($headerWidths[$i])->addParagraph($header, ['bold' => true]);
        }

        foreach ($songs as $song) {
            $styles = $song->getStyles()->map(fn($s) => $s->getName())->toArray();
            $table->addRow();
            $table->addCell(3000)->addParagraph($song->getSongName());
            $table->addCell(2000)->addParagraph($song->getComposer());
            $table->addCell(2200)->addParagraph($song->getParent()?->getSongName());
            $table->addCell(1500)->addParagraph(implode(', ', $styles));
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'songs_export_') . '.odt';
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'ODText')->save($tmpFile);

        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($tmpFile);
        $response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE, 'songs_export.odt');
        $response->headers->set('Content-Type', 'application/vnd.oasis.opendocument.text');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/songs/new', name: 'admin_songs_new')]
    public function songsNew(Request $request, EntityManagerInterface $em): Response
    {
        $song = new SongKeyword();
        $form = $this->createForm(SongKeywordType::class, $song, ['exclude_id' => null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$song->getFolder()) {
                $song->setFolder('Notenverzeichnis');
            }
            $em->persist($song);
            $em->flush();

            $this->addFlash('success', 'Lied wurde erstellt.');
            return $this->redirectToRoute('admin_songs');
        }

        return $this->render('admin/songs/form.html.twig', [
            'form'  => $form,
            'song'  => $song,
            'isNew' => true,
        ]);
    }

    #[Route('/songs/{id}/edit', name: 'admin_songs_edit')]
    public function songsEdit(SongKeyword $song, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SongKeywordType::class, $song, ['exclude_id' => $song->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Movements (child songs) inherit the parent's Etikett.
            foreach ($song->getChildren() as $child) {
                $child->setEtikett($song->getEtikett());
            }
            $em->flush();

            $this->addFlash('success', 'Lied wurde aktualisiert.');
            return $this->redirectToRoute('admin_songs');
        }

        return $this->render('admin/songs/form.html.twig', [
            'form'  => $form,
            'song'  => $song,
            'isNew' => false,
        ]);
    }

    #[Route('/songs/{id}/links', name: 'admin_songs_links_edit')]
    public function songsLinksEdit(SongKeyword $song, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder($song)
            ->add('links', CollectionType::class, [
                'entry_type'    => SongLinkType::class,
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Links gespeichert.');
            return $this->redirectToRoute('member_proben');
        }

        return $this->render('admin/songs/links_edit.html.twig', [
            'form' => $form,
            'song' => $song,
        ]);
    }

    #[Route('/songs/{id}/delete', name: 'admin_songs_delete', methods: ['POST'])]
    public function songsDelete(SongKeyword $song, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $song->getId(), $request->request->get('_token'))) {
            $em->remove($song);
            $em->flush();
            $this->addFlash('success', 'Lied wurde gelöscht.');
        }

        return $this->redirectToRoute('admin_songs');
    }

    #[Route('/songs/bulk-delete', name: 'admin_songs_bulk_delete', methods: ['POST'])]
    public function songsBulkDelete(Request $request, EntityManagerInterface $em, SongKeywordRepository $songRepository): JsonResponse
    {
        if (!$this->isCsrfTokenValid('song_patch', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'CSRF token invalid'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            return $this->json(['error' => 'No IDs provided'], 400);
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $song = $songRepository->find($id);
            if ($song) {
                $em->remove($song);
                $deleted++;
            }
        }

        $em->flush();

        return $this->json(['deleted' => $deleted]);
    }

    #[Route('/songs/etikett-next-number', name: 'admin_songs_etikett_next_number', methods: ['GET'])]
    public function etikettNextNumber(Request $request, SongKeywordRepository $repo): JsonResponse
    {
        $color = trim((string) $request->query->get('color', ''));
        if ($color === '') {
            return new JsonResponse(['next' => null]);
        }

        // Collect the purely-numeric Etikett numbers already used for this colour
        // (colour match is case-insensitive via the column collation).
        $rows = $repo->createQueryBuilder('s')
            ->select('s.etikettNumber')
            ->where('s.etikettColor = :c')->setParameter('c', $color)
            ->andWhere('s.etikettNumber IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        $max = 0;
        foreach ($rows as $row) {
            $n = (string) ($row['etikettNumber'] ?? '');
            if ($n !== '' && ctype_digit($n)) {
                $max = max($max, (int) $n);
            }
        }

        // Next sequential number after the highest used for this colour.
        return new JsonResponse(['next' => $max + 1]);
    }

    #[Route('/songs/{id}/patch-field', name: 'admin_songs_patch_field', methods: ['POST'])]
    public function songsPatchField(
        SongKeyword $song,
        Request $request,
        EntityManagerInterface $em,
        \App\Service\AktuelleProbenSyncService $syncService
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true) ?? [];
        $token = $data['_token'] ?? '';

        if (!$this->isCsrfTokenValid('song_patch', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $allowed = ['songName', 'composer', 'arrangeur', 'compositionYear', 'etikett', 'dropboxlink', 'isAktuelleProben'];
        $field   = $data['field'] ?? '';
        $value   = trim($data['value'] ?? '');

        if (!in_array($field, $allowed, true)) {
            return new JsonResponse(['error' => 'Invalid field'], 400);
        }

        switch ($field) {
            case 'songName':
                if ($value === '') {
                    return new JsonResponse(['error' => 'Liedtitel darf nicht leer sein'], 422);
                }
                $song->setSongName($value);
                break;
            case 'composer':
                $song->setComposer($value !== '' ? $value : null);
                break;
            case 'arrangeur':
                $song->setArrangeur($value !== '' ? $value : null);
                break;
            case 'compositionYear':
                $song->setCompositionYear($value !== '' ? $value : null);
                break;
            case 'etikett':
                $newEtikett = $value !== '' ? $value : null;
                $song->setEtikett($newEtikett);
                // Movements (child songs) inherit the parent's Etikett.
                foreach ($song->getChildren() as $child) {
                    $child->setEtikett($newEtikett);
                }
                break;
            case 'dropboxlink':
                $song->setDropboxlink($value !== '' ? $value : null);
                break;
            case 'isAktuelleProben':
                // The membership flag drives the member "Aktuelle Proben" page and is
                // set regardless of Dropbox state — a song may not exist on Dropbox yet.
                // Copying/removing the dedicated Dropbox folder is a best effort handled
                // inside the sync service (and silently skipped when not possible).
                $isActive = in_array($value, ['1', 'true', true], true);

                if ($isActive) {
                    $syncService->pushSongToAktuelleProben($song);
                } else {
                    $syncService->removeSongFromAktuelleProben($song);
                }
                break;
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * Search YouTube for a query and return the top video results so the user can
     * pick one instead of pasting a URL. Prefers the official YouTube Data API
     * (needs YOUTUBE_API_KEY) and falls back to scraping the public results page.
     */
    #[Route('/songs/youtube-search', name: 'admin_songs_youtube_search', methods: ['GET'])]
    public function youtubeSearch(
        Request $request,
        HttpClientInterface $http,
        #[Autowire('%env(YOUTUBE_API_KEY)%')] string $youtubeApiKey,
    ): JsonResponse {
        $q = trim((string) $request->query->get('q', ''));
        if ($q === '') {
            return new JsonResponse(['error' => 'Suchbegriff fehlt'], 400);
        }

        if ($youtubeApiKey !== '') {
            $results = $this->youtubeApiSearch($http, $youtubeApiKey, $q);
            if ($results !== null) {
                return new JsonResponse(['results' => $results]);
            }
        }

        return new JsonResponse(['results' => $this->youtubeScrapeSearch($q)]);
    }

    /**
     * Official YouTube Data API search (+ a cheap follow-up call for durations).
     * Returns null on any failure so the caller can fall back to scraping.
     */
    private function youtubeApiSearch(HttpClientInterface $http, string $key, string $q): ?array
    {
        try {
            $search = $http->request('GET', 'https://www.googleapis.com/youtube/v3/search', [
                'query'   => ['part' => 'snippet', 'type' => 'video', 'maxResults' => 12, 'q' => $q, 'key' => $key],
                'timeout' => 8,
            ])->toArray(false);
        } catch (\Throwable) {
            return null;
        }
        if (isset($search['error'])) {
            return null;
        }

        $items = $search['items'] ?? [];
        $ids   = [];
        foreach ($items as $it) {
            if (!empty($it['id']['videoId'])) {
                $ids[] = $it['id']['videoId'];
            }
        }
        if (!$ids) {
            return [];
        }

        // One extra call (1 quota unit) to attach durations.
        $durations = [];
        try {
            $videos = $http->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
                'query'   => ['part' => 'contentDetails', 'id' => implode(',', $ids), 'key' => $key],
                'timeout' => 8,
            ])->toArray(false);
            foreach ($videos['items'] ?? [] as $v) {
                $durations[$v['id']] = $this->iso8601ToClock($v['contentDetails']['duration'] ?? '');
            }
        } catch (\Throwable) {
            // Durations are optional.
        }

        $results = [];
        foreach ($items as $it) {
            $videoId = $it['id']['videoId'] ?? null;
            if (!$videoId) {
                continue;
            }
            $sn = $it['snippet'] ?? [];
            $results[] = [
                'url'       => 'https://www.youtube.com/watch?v=' . $videoId,
                'title'     => mb_substr(html_entity_decode((string) ($sn['title'] ?? ''), ENT_QUOTES | ENT_HTML5), 0, 250),
                'channel'   => $sn['channelTitle'] ?? '',
                'duration'  => $durations[$videoId] ?? null,
                'thumbnail' => $sn['thumbnails']['medium']['url'] ?? ('https://i.ytimg.com/vi/' . $videoId . '/mqdefault.jpg'),
            ];
        }

        return $results;
    }

    /** Convert an ISO-8601 duration (e.g. PT3M23S) to "M:SS" / "H:MM:SS". */
    private function iso8601ToClock(string $iso): ?string
    {
        if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return null;
        }
        $h = (int) ($m[1] ?? 0);
        $i = (int) ($m[2] ?? 0);
        $s = (int) ($m[3] ?? 0);
        if ($h + $i + $s === 0) {
            return null;
        }
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $i, $s) : sprintf('%d:%02d', $i, $s);
    }

    /** Fallback: scrape the public YouTube results page (no API key required). */
    private function youtubeScrapeSearch(string $q): array
    {
        $url = 'https://www.youtube.com/results?search_query=' . rawurlencode($q) . '&hl=de&gl=DE';
        $ctx = stream_context_create(['http' => [
            'timeout' => 8,
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
                       . "Accept-Language: de\r\n"
                       . "Cookie: CONSENT=YES+1\r\n",
        ]]);

        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) {
            return [];
        }

        $data = $this->extractYtInitialData($html);
        if ($data === null) {
            return [];
        }

        $sections = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];
        $results  = [];
        foreach ($sections as $section) {
            foreach ($section['itemSectionRenderer']['contents'] ?? [] as $item) {
                $vr = $item['videoRenderer'] ?? null;
                if ($vr === null || empty($vr['videoId'])) {
                    continue;
                }
                $videoId = $vr['videoId'];
                $title   = $vr['title']['runs'][0]['text']
                    ?? ($vr['title']['accessibility']['accessibilityData']['label'] ?? '');
                $channel = $vr['ownerText']['runs'][0]['text']
                    ?? ($vr['longBylineText']['runs'][0]['text'] ?? '');

                $results[] = [
                    'url'       => 'https://www.youtube.com/watch?v=' . $videoId,
                    'title'     => mb_substr($title, 0, 250),
                    'channel'   => $channel,
                    'duration'  => $vr['lengthText']['simpleText'] ?? null,
                    'thumbnail' => 'https://i.ytimg.com/vi/' . $videoId . '/mqdefault.jpg',
                ];
                if (count($results) >= 12) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /** Pull the `ytInitialData` JSON blob out of a YouTube HTML page (balanced-brace scan). */
    private function extractYtInitialData(string $html): ?array
    {
        $pos = strpos($html, 'ytInitialData = ');
        if ($pos === false) {
            $pos = strpos($html, 'ytInitialData"] = ');
        }
        if ($pos === false) {
            return null;
        }

        $start = strpos($html, '{', $pos);
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inStr = false;
        $esc   = false;
        $len   = strlen($html);
        for ($i = $start; $i < $len; $i++) {
            $ch = $html[$i];
            if ($inStr) {
                if ($esc)              { $esc = false; }
                elseif ($ch === '\\')  { $esc = true; }
                elseif ($ch === '"')   { $inStr = false; }
                continue;
            }
            if ($ch === '"')      { $inStr = true; }
            elseif ($ch === '{')  { $depth++; }
            elseif ($ch === '}')  {
                if (--$depth === 0) {
                    return json_decode(substr($html, $start, $i - $start + 1), true) ?: null;
                }
            }
        }

        return null;
    }

    #[Route('/songs/{id}/sync-anchors', name: 'admin_songs_sync_anchors_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function saveSyncAnchors(
        SongKeyword $song,
        Request $request,
        EntityManagerInterface $em,
        ScoreSyncAnchorsRepository $repo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!$this->isCsrfTokenValid('sync_anchors', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $pdfPath   = trim((string) ($data['pdf'] ?? ''));
        $audioPath = trim((string) ($data['audio'] ?? ''));
        $anchors   = $data['anchors'] ?? [];

        if ($pdfPath === '' || $audioPath === '') {
            return new JsonResponse(['error' => 'pdf and audio required'], 400);
        }

        if (!is_array($anchors)) {
            return new JsonResponse(['error' => 'anchors must be an array'], 400);
        }

        $record = $repo->findOneByPaths($song->getId(), $pdfPath, $audioPath);
        if (!$record) {
            $record = (new ScoreSyncAnchors())
                ->setSong($song)
                ->setPdfPath($pdfPath)
                ->setAudioPath($audioPath);
            $em->persist($record);
        }

        $record->setAnchors(array_values($anchors));
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/songs/{id}/sync-anchors', name: 'admin_songs_sync_anchors_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteSyncAnchors(
        SongKeyword $song,
        Request $request,
        EntityManagerInterface $em,
        ScoreSyncAnchorsRepository $repo,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!$this->isCsrfTokenValid('sync_anchors', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $pdfPath   = trim((string) ($data['pdf'] ?? ''));
        $audioPath = trim((string) ($data['audio'] ?? ''));

        $record = $repo->findOneByPaths($song->getId(), $pdfPath, $audioPath);
        if ($record) {
            $em->remove($record);
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/songs/{id}/reorder-children', name: 'admin_songs_reorder_children', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reorderChildren(SongKeyword $song, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!$this->isCsrfTokenValid('song_patch', $data['_token'] ?? '')) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $order    = array_map('intval', $data['order'] ?? []);
        $childMap = [];
        foreach ($song->getChildren() as $child) {
            $childMap[$child->getId()] = $child;
        }

        foreach ($order as $position => $childId) {
            if (isset($childMap[$childId])) {
                $childMap[$childId]->setSortOrder($position);
            }
        }

        $em->flush();
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/songs/sync-dropbox', name: 'admin_songs_sync_dropbox', methods: ['POST'])]
    public function songsSyncDropbox(
        SongKeywordRepository $songRepository,
        EntityManagerInterface $em,
        DropboxService $dropboxService,
        Request $request
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('sync_dropbox', $request->request->get('_token'))) {
            return $this->json(['error' => 'CSRF-Token ungültig'], 403);
        }

        // Clear stale file-name cache so renames/moves are picked up immediately
        $dropboxService->clearFileCache();

        // Fetch immediate subfolders from the Noten location only.
        // "Aktuelle Proben" is no longer a sync source — membership there is
        // controlled exclusively by the "Aktuell in Proben" checkbox on the
        // songs page, which copies/removes the folder via AktuelleProbenSyncService.
        try {
            $notenFolders = $dropboxService->listSubfolders('/Chorgemeinschaft Teutonia/Noten');
        } catch (\Exception $e) {
            return $this->json(['error' => 'Dropbox-Verbindung fehlgeschlagen: ' . $e->getMessage()], 502);
        }

        // Build lookup maps: normalized name => original folder name
        $buildLookup = function (array $names): array {
            $byFull   = [];
            $byTitle  = [];
            foreach ($names as $name) {
                if (str_starts_with($name, '_')) {
                    continue;
                }
                $norm = $this->normalizeForSync($name);
                $byFull[$norm] = $name;
                if (str_contains($name, ' #')) {
                    [$titlePart] = explode(' #', $name, 2);
                    $normTitle = $this->normalizeForSync(trim($titlePart));
                    $byTitle[$normTitle] ??= $name;
                }
            }
            return [$byFull, $byTitle];
        };

        [$notenByFull, $notenByTitle] = $buildLookup($notenFolders);

        // Match a song against a set of Dropbox folders using all strategies.
        // Returns the matched folder name or null.
        $findInFolders = function (string $normTitle, string $normComposer, array $byFull, array $byTitle): ?string {
            // 1. Exact match on full folder name
            if (isset($byFull[$normTitle])) return $byFull[$normTitle];

            // 2. Match on the title part before " #"
            if (isset($byTitle[$normTitle])) return $byTitle[$normTitle];

            // 3. Composer last name + title combined substring match
            if ($normComposer !== '') {
                $parts    = explode(' ', $normComposer);
                $lastName = end($parts);
                foreach ($byFull as $norm => $name) {
                    if (str_contains($norm, $lastName) && str_contains($norm, $normTitle)) {
                        return $name;
                    }
                }
            }

            // 4. Fuzzy: Dropbox folder name contains the song title (≥ 8 chars)
            if (mb_strlen($normTitle) >= 8) {
                foreach ($byFull as $norm => $name) {
                    if (str_contains($norm, $normTitle)) return $name;
                }
            }

            // 5. Reverse fuzzy: song title contains the Dropbox title part (≥ 8 chars)
            if (mb_strlen($normTitle) >= 8) {
                foreach ($byTitle as $titleNorm => $name) {
                    if (mb_strlen($titleNorm) >= 8 && str_contains($normTitle, $titleNorm)) {
                        return $name;
                    }
                }
            }

            return null;
        };

        $songs         = $songRepository->findAll();
        $matched       = 0;
        $alreadyLinked = 0;
        $unmatched     = [];

        $notenBase = '/Chorgemeinschaft Teutonia/Noten';

        foreach ($songs as $song) {
            // Movements are handled in the second pass below; skip here to avoid
            // false matches against top-level Noten folders.
            if ($song->isMovement()) continue;

            // Skip songs that already have a dropboxlink (treat it as authoritative).
            // Members can change the song name/composer in the DB without breaking the link.
            // To re-match a song, manually clear its dropboxlink first.
            if ($song->getDropboxlink()) {
                $alreadyLinked++;
                continue;
            }

            $normTitle    = $this->normalizeForSync($song->getSongName());
            $normComposer = $song->getComposer() ? $this->normalizeForSync($song->getComposer()) : '';

            // Match against Noten only. "Aktuelle Proben" is managed by the checkbox.
            $notenFolder = $findInFolders($normTitle, $normComposer, $notenByFull, $notenByTitle);
            if ($notenFolder !== null) {
                $song->setDropboxlink($notenBase . '/' . $notenFolder);
                $matched++;
            } else {
                $unmatched[] = $song->getSongName();
            }
        }

        $em->flush();

        // Dedup: merge top-level songs that share the same normalised name.
        // Collapses any duplicates into the canonical entry — the one with children,
        // or with isAktuelleProben=true, or with both Dropbox links — preserving the
        // "Aktuelle Proben" state (set via the checkbox) on the surviving entry.
        $mergedIds   = [];
        $mergedCount = 0;
        $byNorm      = [];
        foreach ($songs as $s) {
            if ($s->isMovement()) continue;
            $byNorm[$this->normalizeForSync($s->getSongName())][] = $s;
        }
        foreach ($byNorm as $dupGroup) {
            if (count($dupGroup) <= 1) continue;

            usort($dupGroup, function ($a, $b) {
                $scoreA = ($a->getChildren()->count() > 0 ? 4 : 0)
                        + ($a->isAktuelleProben()         ? 2 : 0)
                        + ($a->getDropboxlink() && $a->getAktuelleDropboxlink() ? 1 : 0);
                $scoreB = ($b->getChildren()->count() > 0 ? 4 : 0)
                        + ($b->isAktuelleProben()         ? 2 : 0)
                        + ($b->getDropboxlink() && $b->getAktuelleDropboxlink() ? 1 : 0);
                return $scoreB <=> $scoreA;
            });

            $canonical = $dupGroup[0];
            for ($i = 1; $i < count($dupGroup); $i++) {
                $dup = $dupGroup[$i];

                // Transfer Dropbox paths that canonical is missing
                if ($dup->getDropboxlink() && !$canonical->getDropboxlink()) {
                    $canonical->setDropboxlink($dup->getDropboxlink());
                }
                if ($dup->getAktuelleDropboxlink() && !$canonical->getAktuelleDropboxlink()) {
                    $canonical->setAktuelleDropboxlink($dup->getAktuelleDropboxlink());
                }
                if ($dup->isAktuelleProben() && !$canonical->isAktuelleProben()) {
                    $canonical->setIsAktuelleProben(true);
                }

                // Move any children the duplicate might own
                foreach ($dup->getChildren() as $child) {
                    $child->setParent($canonical);
                    $child->setEtikett($canonical->getEtikett()); // inherit new parent's Etikett
                }

                // Transfer style associations not already on canonical
                foreach ($dup->getStyles() as $style) {
                    $canonical->addStyle($style);
                }

                // Remove the duplicate only when it has no concert history
                // (song_style and konzert_song rows cascade-delete automatically).
                if ($dup->getKonzerte()->isEmpty()) {
                    $em->remove($dup);
                    $mergedIds[] = $dup->getId();
                    $mergedCount++;
                }
            }
        }
        if ($mergedCount > 0) {
            $em->flush();
        }

        // Second pass: for each parent song, scan its Dropbox subfolders,
        // link already-registered children, and adopt matching standalone songs.
        // Uses dropboxlink (Noten) when available, falls back to aktuelleDropboxlink.
        $adoptable = [];
        foreach ($songs as $s) {
            if ($s->isMovement() || in_array($s->getId(), $mergedIds)) continue;
            $adoptable[$this->normalizeForSync($s->getSongName())][] = $s;
        }

        $movementsMatched = 0;
        foreach ($songs as $song) {
            if ($song->isMovement() || in_array($song->getId(), $mergedIds)) continue;

            $parentPath = $song->getDropboxlink() ?? $song->getAktuelleDropboxlink();
            if (!$parentPath) continue;

            try {
                $subfolderNames = $dropboxService->listSubfolders($parentPath);
            } catch (\Exception $e) {
                continue;
            }

            if (empty($subfolderNames)) continue;

            [$subByFull, $subByTitle] = $buildLookup($subfolderNames);

            // Link already-registered children to their subfolder.
            // Skip movements that already have a dropboxlink (treat it as authoritative).
            foreach ($song->getChildren() as $movement) {
                if ($movement->getDropboxlink()) {
                    continue; // already linked, don't re-match
                }
                $normTitle    = $this->normalizeForSync($movement->getSongName());
                $normComposer = $movement->getComposer() ? $this->normalizeForSync($movement->getComposer()) : '';
                $match = $findInFolders($normTitle, $normComposer, $subByFull, $subByTitle);
                if ($match !== null) {
                    $movement->setDropboxlink($parentPath . '/' . $match);
                    $movementsMatched++;
                }
            }

            // Adopt standalone songs whose name matches a subfolder of this parent
            foreach ($subfolderNames as $subfolder) {
                $subPath   = $parentPath . '/' . $subfolder;
                $normFull  = $this->normalizeForSync($subfolder);
                $normTitle = $normFull;
                if (str_contains($subfolder, ' #')) {
                    [$titlePart] = explode(' #', $subfolder, 2);
                    $normTitle = $this->normalizeForSync(trim($titlePart));
                }

                // Try exact matches first
                foreach (array_unique([$normTitle, $normFull]) as $norm) {
                    if (!isset($adoptable[$norm])) continue;
                    foreach ($adoptable[$norm] as $key => $candidate) {
                        if ($candidate === $song) continue;
                        // Skip adopting a song that already has a parent (prevent re-adoption)
                        if ($candidate->getParent()) continue;
                        $sortOrder = 0;
                        if (preg_match('/^(\d+)/', $subfolder, $m)) {
                            $sortOrder = (int) $m[1];
                        }
                        $candidate->setParent($song);
                        $candidate->setDropboxlink($subPath);
                        $candidate->setSortOrder($sortOrder);
                        $candidate->setEtikett($song->getEtikett()); // inherit parent's Etikett
                        unset($adoptable[$norm][$key]);
                        $movementsMatched++;
                        break 2;
                    }
                }
            }
        }

        if ($movementsMatched > 0) {
            $em->flush();
        }

        // Build a set of all dropboxlinks already in the DB (after matching above).
        // Keys are lowercased so the lookup is case-insensitive (Dropbox may return
        // folder names with different capitalisation than what was saved in the DB).
        $linkedPaths = [];
        foreach ($songRepository->findAll() as $s) {
            if ($s->getDropboxlink()) {
                $linkedPaths[mb_strtolower(rtrim($s->getDropboxlink(), '/'))] = true;
            }
            if ($s->getAktuelleDropboxlink()) {
                $linkedPaths[mb_strtolower(rtrim($s->getAktuelleDropboxlink(), '/'))] = true;
            }
        }

        // Create DB entries for Noten folders that have no matching song yet.
        // "Aktuelle Proben" folders are never imported — that folder is a working
        // copy populated by the "Aktuell in Proben" checkbox, not a source of songs.
        $created = 0;
        foreach ($notenFolders as $folderName) {
            if (str_starts_with($folderName, '_')) {
                continue;
            }
            $path = $notenBase . '/' . $folderName;
            if (isset($linkedPaths[mb_strtolower($path)])) {
                continue;
            }

            // Parse "[title] #[composer]" pattern from folder name
            $composer = null;
            $title    = $folderName;
            if (str_contains($folderName, ' #')) {
                [$titlePart, $composerPart] = explode(' #', $folderName, 2);
                $title    = trim($titlePart);
                $composer = trim($composerPart);
            }

            $song = new SongKeyword();
            $song->setSongName($title);
            $song->setComposer($composer);
            $song->setFolder('Notenverzeichnis');
            $song->setDropboxlink($path);
            $em->persist($song);
            $created++;
        }

        if ($created > 0) {
            $em->flush();
        }

        return $this->json([
            'matched'           => $matched,
            'movements_matched' => $movementsMatched,
            'merged'            => $mergedCount,
            'already_linked'    => $alreadyLinked,
            'unmatched'         => count($unmatched),
            'unmatched_names'   => $unmatched,
            'created'           => $created,
        ]);
    }

    private function normalizeForSync(string $s): string
    {
        $s   = mb_strtolower($s, 'UTF-8');
        // Treat / and , as equivalent (Dropbox replaced / with , in folder names)
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

    // ==================== STYLES ====================

    #[Route('/styles', name: 'admin_styles')]
    public function styles(StyleRepository $styleRepository): Response
    {
        return $this->render('admin/styles/index.html.twig', [
            'styles' => $styleRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/styles/new', name: 'admin_styles_new')]
    public function stylesNew(Request $request, EntityManagerInterface $em): Response
    {
        $style = new Style();
        $form  = $this->createForm(StyleType::class, $style);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleStyleImageUpload($form, $style);
            $em->persist($style);
            $em->flush();
            $this->addFlash('success', 'Stil wurde erstellt.');
            return $this->redirectToRoute('admin_styles');
        }

        return $this->render('admin/styles/form.html.twig', [
            'form'  => $form,
            'style' => $style,
            'isNew' => true,
        ]);
    }

    #[Route('/styles/{id}/edit', name: 'admin_styles_edit')]
    public function stylesEdit(
        Style $style,
        Request $request,
        EntityManagerInterface $em,
        SongKeywordRepository $songRepository
    ): Response {
        $form = $this->createForm(StyleType::class, $style);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleStyleImageUpload($form, $style);
            $em->flush();
            $this->addFlash('success', 'Stil wurde aktualisiert.');
            return $this->redirectToRoute('admin_styles_edit', [
                'id'   => $style->getId(),
                'page' => $request->query->getInt('page', 1),
            ]);
        }

        $page       = max(1, $request->query->getInt('page', 1));
        $limit      = 25;
        $total      = $songRepository->countByStyle($style);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page       = min($page, $totalPages);

        return $this->render('admin/styles/form.html.twig', [
            'form'        => $form,
            'style'       => $style,
            'isNew'       => false,
            'songs'       => $songRepository->findByStylePaginated($style, $page, $limit),
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
        ]);
    }

    #[Route('/styles/{id}/toggle-repertoire-song', name: 'admin_styles_toggle_repertoire_song', methods: ['POST'])]
    public function stylesToggleRepertoireSong(
        Style $style,
        Request $request,
        EntityManagerInterface $em,
        SongKeywordRepository $songRepository
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true) ?? [];
        $token = $data['_token'] ?? '';

        if (!$this->isCsrfTokenValid('song_patch', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $songId = (int) ($data['songId'] ?? 0);
        $active = ($data['active'] ?? '0') === '1';

        $song = $songRepository->find($songId);
        if (!$song) {
            return new JsonResponse(['error' => 'Song not found'], 404);
        }

        if ($active) {
            $style->addRepertoireSong($song);
        } else {
            $style->removeRepertoireSong($song);
        }

        $em->flush();

        return new JsonResponse(['active' => $active]);
    }

    #[Route('/styles/{id}/delete', name: 'admin_styles_delete', methods: ['POST'])]
    public function stylesDelete(Style $style, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_style' . $style->getId(), $request->request->get('_token'))) {
            if ($style->getImage()) {
                $path = $this->getParameter('kernel.project_dir') . '/public/images/styles/' . $style->getImage();
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            $em->remove($style);
            $em->flush();
            $this->addFlash('success', 'Stil wurde gelöscht.');
        }

        return $this->redirectToRoute('admin_styles');
    }

    private function handleStyleImageUpload($form, Style $style): void
    {
        $file = $form->get('imageFile')->getData();
        if (!$file) {
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/styles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($style->getImage() && file_exists($uploadDir . $style->getImage())) {
            unlink($uploadDir . $style->getImage());
        }

        $filename = uniqid('style_') . '.' . $file->guessExtension();
        $file->move($uploadDir, $filename);
        $style->setImage($filename);
    }

    // ==================== KONZERTE ====================

    #[Route('/konzerte', name: 'admin_konzerte')]
    public function konzerte(KonzertRepository $konzertRepository): Response
    {
        return $this->render('admin/konzerte/index.html.twig', [
            'konzerte'         => $konzertRepository->findAllOrdered(),
            'showAdminActions' => true,
        ]);
    }

    #[Route('/konzerte/new', name: 'admin_konzerte_new')]
    public function konzerteNew(Request $request, EntityManagerInterface $em, SongKeywordRepository $songRepo, PostRepository $postRepository): Response
    {
        $konzert = new Konzert();
        $form    = $this->createForm(KonzertType::class, $konzert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customJson = $request->request->get('custom_songs_json', '[]');
            $konzert->setCustomSongs(json_decode($customJson, true) ?: []);
            $orderJson = $request->request->get('song_order_json', '[]');
            $konzert->setSongOrder(json_decode($orderJson, true) ?: []);
            $videoJson = $request->request->get('video_links_json', '{}');
            $konzert->setVideoLinks(json_decode($videoJson, true) ?: []);
            $notesJson = $request->request->get('song_notes_json', '[]');
            $konzert->setSongNotes(json_decode($notesJson, true) ?: []);
            $plakatSelect = trim($request->request->get('plakat_select', ''));
            if ($plakatSelect !== '') {
                $konzert->setPlakatPath($plakatSelect);
            }
            $programmV = $form->get('programmVorderseiteFile')->getData();
            if ($programmV) {
                $path = $this->handleImageUpload($programmV);
                if ($path) $konzert->setProgrammVorderseite($path);
            }
            $programmR = $form->get('programmRueckseiteFile')->getData();
            if ($programmR) {
                $path = $this->handleImageUpload($programmR);
                if ($path) $konzert->setProgrammRueckseite($path);
            }
            $kritik = $form->get('kritikFile')->getData();
            if ($kritik) {
                $path = $this->handleImageUpload($kritik);
                if ($path) $konzert->setKritikPath($path);
            }
            $em->persist($konzert);
            $em->flush();
            $this->addFlash('success', 'Konzert wurde erstellt.');
            return $this->redirectToRoute('admin_konzerte');
        }

        $konzertImages = array_filter(
            $postRepository->findByPageOrderedByDate('konzerte-und-aktivitaeten'),
            fn($p) => $p->getImagePath()
        );

        return $this->render('admin/konzerte/form.html.twig', [
            'form'          => $form,
            'konzert'       => $konzert,
            'konzertImages' => array_values($konzertImages),
            'isNew'       => true,
            'songParents' => $songRepo->findParentIdMap(),
        ]);
    }

    #[Route('/konzerte/{id}/edit', name: 'admin_konzerte_edit')]
    public function konzerteEdit(Konzert $konzert, Request $request, EntityManagerInterface $em, SongKeywordRepository $songRepo, PostRepository $postRepository): Response
    {
        $form = $this->createForm(KonzertType::class, $konzert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customJson = $request->request->get('custom_songs_json', '[]');
            $konzert->setCustomSongs(json_decode($customJson, true) ?: []);
            $orderJson = $request->request->get('song_order_json', '[]');
            $konzert->setSongOrder(json_decode($orderJson, true) ?: []);
            $videoJson = $request->request->get('video_links_json', '{}');
            $konzert->setVideoLinks(json_decode($videoJson, true) ?: []);
            $notesJson = $request->request->get('song_notes_json', '[]');
            $konzert->setSongNotes(json_decode($notesJson, true) ?: []);
            $plakatSelect = trim($request->request->get('plakat_select', ''));
            $konzert->setPlakatPath($plakatSelect !== '' ? $plakatSelect : null);
            $this->handleFileDeleteRequest($request, 'delete_programm_vorderseite', $konzert->getProgrammVorderseite(), fn() => $konzert->setProgrammVorderseite(null));
            $this->handleFileDeleteRequest($request, 'delete_programm_rueckseite', $konzert->getProgrammRueckseite(), fn() => $konzert->setProgrammRueckseite(null));
            $this->handleFileDeleteRequest($request, 'delete_kritik', $konzert->getKritikPath(), fn() => $konzert->setKritikPath(null));
            $programmV = $form->get('programmVorderseiteFile')->getData();
            if ($programmV) {
                $path = $this->handleImageUpload($programmV, $konzert->getProgrammVorderseite());
                if ($path) $konzert->setProgrammVorderseite($path);
            }
            $programmR = $form->get('programmRueckseiteFile')->getData();
            if ($programmR) {
                $path = $this->handleImageUpload($programmR, $konzert->getProgrammRueckseite());
                if ($path) $konzert->setProgrammRueckseite($path);
            }
            $kritik = $form->get('kritikFile')->getData();
            if ($kritik) {
                $path = $this->handleImageUpload($kritik, $konzert->getKritikPath());
                if ($path) $konzert->setKritikPath($path);
            }
            $em->flush();
            $this->addFlash('success', 'Konzert wurde aktualisiert.');
            return $this->redirectToRoute('admin_konzerte_edit', ['id' => $konzert->getId()]);
        }

        $konzertImages = array_filter(
            $postRepository->findByPageOrderedByDate('konzerte-und-aktivitaeten'),
            fn($p) => $p->getImagePath()
        );

        return $this->render('admin/konzerte/form.html.twig', [
            'form'          => $form,
            'konzert'       => $konzert,
            'isNew'         => false,
            'songParents'   => $songRepo->findParentIdMap(),
            'konzertImages' => array_values($konzertImages),
        ]);
    }

    #[Route('/konzerte/{id}/delete', name: 'admin_konzerte_delete', methods: ['POST'])]
    public function konzerteDelete(Konzert $konzert, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_konzert' . $konzert->getId(), $request->request->get('_token'))) {
            $em->remove($konzert);
            $em->flush();
            $this->addFlash('success', 'Konzert wurde gelöscht.');
        }
        return $this->redirectToRoute('admin_konzerte');
    }

    // ==================== USERS ====================

    #[Route('/users', name: 'admin_users')]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/users/new', name: 'admin_users_new')]
    public function usersNew(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Benutzer wurde erstellt.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'isNew' => true,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit')]
    public function usersEdit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $em->flush();

            $this->addFlash('success', 'Benutzer wurde aktualisiert.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'isNew' => false,
        ]);
    }

    #[Route('/users/{id}/reset-password', name: 'admin_users_reset_password', methods: ['POST'])]
    public function usersResetPassword(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->isCsrfTokenValid('reset-password' . $user->getId(), $request->request->get('_token'))) {
            $user->setPassword($passwordHasher->hashPassword($user, 'teutonia'));
            $em->flush();
            $this->addFlash('success', 'Passwort von ' . $user->getFullName() . ' wurde auf "teutonia" zurückgesetzt.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function usersDelete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Benutzer wurde gelöscht.');
        }

        return $this->redirectToRoute('admin_users');
    }

    // ==================== POSTS ====================

    #[Route('/beitraege', name: 'admin_posts')]
    public function posts(PostRepository $postRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $filterPage = $request->query->get('filter_page');

        if ($filterPage === null) {
            $filterPage = 'unser-chor';
        }

        if ($filterPage === 'all') {
            $posts = $postRepository->findAllOrderedWithMainFirst($page, $limit);
            $totalCount = $postRepository->countAll();
        } else {
            $posts = $postRepository->findByPagePaginated($filterPage, $page, $limit);
            $totalCount = $postRepository->countByPage($filterPage);
        }

        return $this->render('admin/posts/index.html.twig', [
            'posts' => $posts,
            'currentPage' => $page,
            'totalPages' => ceil($totalCount / $limit),
            'filterPage' => $filterPage,
        ]);
    }

    #[Route('/beitraege/new', name: 'admin_posts_new')]
    public function postsNew(Request $request, EntityManagerInterface $em): Response
    {
        $post = new Post();
        $filterPage = $request->query->get('filter_page');
        if ($filterPage && $filterPage !== 'all') {
            $post->setPage($filterPage);
        }
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            try {
                $imagePath = $this->handleImageUpload($imageFile);
                if ($imagePath) {
                    $post->setImagePath($imagePath);
                }
            } catch (\Exception $e) {
                error_log('PDF Conversion Error: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                $this->addFlash('error', 'Fehler beim Hochladen: ' . $e->getMessage());
                return $this->render('admin/posts/form.html.twig', [
                    'form' => $form,
                    'post' => $post,
                    'isNew' => true,
                ]);
            }

            // If this post is marked as main, unmark any existing main post
            if ($post->isMain()) {
                $existingMain = $em->getRepository(Post::class)->findMainPost();
                if ($existingMain && $existingMain->getId() !== $post->getId()) {
                    $existingMain->setIsMain(false);
                }
            }

            $em->persist($post);
            $em->flush();

            // Check if this is a quick save (AJAX request)
            if ($request->request->get('quick_save')) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Beitrag wurde erstellt.',
                    'redirect' => $this->generateUrl('admin_posts_edit', ['id' => $post->getId()])
                ]);
            }

            $this->addFlash('success', 'Beitrag wurde erstellt.');
            return $this->redirectToRoute('admin_posts', ['filter_page' => $post->getPage()]);
        }

        return $this->render('admin/posts/form.html.twig', [
            'form' => $form,
            'post' => $post,
            'isNew' => true,
        ]);
    }

    #[Route('/beitraege/{id}/edit', name: 'admin_posts_edit')]
    public function postsEdit(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                try {
                    $imagePath = $this->handleImageUpload($imageFile, $post->getImagePath());
                    if ($imagePath) {
                        $post->setImagePath($imagePath);
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->render('admin/posts/form.html.twig', [
                        'form' => $form,
                        'post' => $post,
                        'isNew' => false,
                    ]);
                }
            }

            // If this post is marked as main, unmark any existing main post
            if ($post->isMain()) {
                $existingMain = $em->getRepository(Post::class)->findMainPost();
                if ($existingMain && $existingMain->getId() !== $post->getId()) {
                    $existingMain->setIsMain(false);
                }
            }

            $em->flush();

            // Check if this is a quick save (AJAX request)
            if ($request->request->get('quick_save')) {
                return new JsonResponse(['success' => true, 'message' => 'Beitrag wurde gespeichert.']);
            }

            $this->addFlash('success', 'Beitrag wurde aktualisiert.');
            return $this->redirectToRoute('admin_posts', ['filter_page' => $post->getPage()]);
        }

        return $this->render('admin/posts/form.html.twig', [
            'form' => $form,
            'post' => $post,
            'isNew' => false,
        ]);
    }

    #[Route('/beitraege/{id}/delete', name: 'admin_posts_delete', methods: ['POST'])]
    public function postsDelete(Post $post, Request $request, EntityManagerInterface $em, PostRepository $postRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            // Delete image if exists
            if ($post->getImagePath()) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/' . $post->getImagePath();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Beitrag wurde gelöscht.');
        }

        $filterPage = $request->request->get('filter_page') ?: 'unser-chor';
        $page       = max(1, (int) $request->request->get('page', 1));
        $limit      = 20;

        $totalCount = $filterPage === 'all'
            ? $postRepository->countAll()
            : $postRepository->countByPage($filterPage);
        $page = min($page, max(1, (int) ceil($totalCount / $limit)));

        return $this->redirectToRoute('admin_posts', [
            'filter_page' => $filterPage,
            'page'        => $page,
        ]);
    }

    #[Route('/beitraege/reorder', name: 'admin_posts_reorder', methods: ['POST'])]
    public function postsReorder(Request $request, EntityManagerInterface $em, PostRepository $postRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['order']) || !is_array($data['order'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
        }

        foreach ($data['order'] as $position => $postId) {
            $post = $postRepository->find($postId);
            if ($post) {
                $post->setPosition($position);
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Reihenfolge wurde aktualisiert.']);
    }

    private function handleFileDeleteRequest(Request $request, string $fieldName, ?string $currentPath, callable $clearFn): void
    {
        if ($request->request->get($fieldName) !== '1' || !$currentPath) {
            return;
        }
        $full = $this->getParameter('kernel.project_dir') . '/public/' . $currentPath;
        if (file_exists($full)) {
            unlink($full);
        }
        $clearFn();
    }

    private function handleImageUpload($imageFile, ?string $oldImagePath = null): ?string
    {
        if (!$imageFile) {
            return null;
        }

        // Delete old image if exists
        if ($oldImagePath) {
            $oldImageFullPath = $this->getParameter('kernel.project_dir') . '/public/' . $oldImagePath;
            if (file_exists($oldImageFullPath)) {
                unlink($oldImageFullPath);
            }
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/posts';
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);

        // Check if the file is a PDF
        if ($imageFile->getMimeType() === 'application/pdf') {
            try {
                // Create a temporary path for the PDF
                $tempPdfPath = $uploadDir . '/' . uniqid() . '.pdf';
                $imageFile->move($uploadDir, basename($tempPdfPath));

                // Convert PDF to image
                $outputFilename = $safeFilename . '-' . uniqid() . '.jpg';
                $outputPath = $uploadDir . '/' . $outputFilename;

                $imagick = new \Imagick();
                $imagick->setResolution(150, 150);
                $imagick->setBackgroundColor(new \ImagickPixel('white'));
                $imagick->readImage($tempPdfPath . '[0]');
                $imagick->setImageBackgroundColor(new \ImagickPixel('white'));

                // Composite PDF content onto an explicit white canvas to eliminate any transparency/black artifacts
                $white = new \Imagick();
                $white->newImage($imagick->getImageWidth(), $imagick->getImageHeight(), new \ImagickPixel('white'));
                $white->compositeImage($imagick, \Imagick::COMPOSITE_OVER, 0, 0);
                $white->setImageFormat('jpeg');
                $white->writeImage($outputPath);
                $white->clear();
                $imagick->clear();

                // Delete temporary PDF
                unlink($tempPdfPath);

                return 'images/posts/' . $outputFilename;
            } catch (\Exception $e) {
                throw new \RuntimeException('Fehler beim Konvertieren des PDFs: ' . $e->getMessage());
            }
        } else {
            // Regular image upload
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move($uploadDir, $newFilename);
            return 'images/posts/' . $newFilename;
        }
    }
}
