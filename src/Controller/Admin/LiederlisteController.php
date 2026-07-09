<?php

namespace App\Controller\Admin;

use App\Entity\Liederliste;
use App\Repository\LiederlisteRepository;
use App\Repository\SongKeywordRepository;
use App\Service\AktuelleProbenSyncService;
use App\Service\DropboxService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_NOTENWART')"))]
#[Route('/admin/liederlisten', name: 'admin_liederlisten')]
class LiederlisteController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(LiederlisteRepository $repo): Response
    {
        return $this->render('admin/liederliste/index.html.twig', [
            'listen' => $repo->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em, SongKeywordRepository $songRepo): Response
    {
        $liste = new Liederliste();
        return $this->handleForm($liste, $request, $em, $songRepo, true);
    }

    #[Route('/{id}/edit', name: '_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Liederliste $liste, Request $request, EntityManagerInterface $em, SongKeywordRepository $songRepo): Response
    {
        return $this->handleForm($liste, $request, $em, $songRepo, false);
    }

    #[Route('/{id}/delete', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Liederliste $liste, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_liederliste' . $liste->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('admin_liederlisten');
        }

        $em->remove($liste);
        $em->flush();

        $this->addFlash('success', 'Liederliste gelöscht.');
        return $this->redirectToRoute('admin_liederlisten');
    }

    #[Route('/{id}/download', name: '_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Liederliste $liste, SongKeywordRepository $songRepo): Response
    {
        $phpWord = $this->buildPhpWord($liste, $songRepo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'liederliste_') . '.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($tmpFile);

        $safeName = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß _-]/', '', $liste->getName());
        $filename  = 'Liederliste_' . str_replace(' ', '_', $safeName) . '.docx';

        $response = new BinaryFileResponse($tmpFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/{id}/download-odt', name: '_download_odt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadOdt(Liederliste $liste, SongKeywordRepository $songRepo): Response
    {
        $phpWord = $this->buildPhpWord($liste, $songRepo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'liederliste_') . '.odt';
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'ODText')->save($tmpFile);

        $safeName = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß _-]/', '', $liste->getName());
        $filename  = 'Liederliste_' . str_replace(' ', '_', $safeName) . '.odt';

        $response = new BinaryFileResponse($tmpFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        $response->headers->set('Content-Type', 'application/vnd.oasis.opendocument.text');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/{id}/download-pdf', name: '_download_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadPdf(Liederliste $liste, SongKeywordRepository $songRepo): Response
    {
        $data = $this->buildRows($liste, $songRepo);

        $html = $this->renderView('admin/liederliste/pdf.html.twig', [
            'title'       => $liste->getName(),
            'subtitle'    => $liste->getTitle(),
            'showEtikett' => $data['showEtikett'],
            'rows'        => $data['rows'],
            'total'       => $data['total'],
        ]);

        $dompdf = new \Dompdf\Dompdf(['defaultFont' => 'DejaVu Sans']);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $safeName = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß _-]/', '', $liste->getName());
        $filename = 'Liederliste_' . str_replace(' ', '_', $safeName) . '.pdf';
        // makeDisposition() needs a pure-ASCII fallback for the non-UTF-8 clients.
        $asciiFallback = strtr($filename, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        ]);
        $asciiFallback = preg_replace('/[^\x20-\x7E]/', '_', $asciiFallback);

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename, $asciiFallback),
        );

        return $response;
    }

    #[Route('/{id}/add-to-proben', name: '_add_to_proben', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function addToProben(
        Liederliste $liste,
        Request $request,
        SongKeywordRepository $songRepo,
        AktuelleProbenSyncService $syncService,
    ): Response {
        if (!$this->isCsrfTokenValid('add_to_proben' . $liste->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('admin_liederlisten_edit', ['id' => $liste->getId()]);
        }

        // Unique song IDs referenced by the list (custom items carry no songId).
        $songIds = [];
        foreach ($liste->getItems() as $item) {
            $songId = $item['songId'] ?? null;
            if ($songId !== null) {
                $songIds[$songId] = true;
            }
        }

        $added = $already = $skipped = 0;
        foreach (array_keys($songIds) as $songId) {
            $song = $songRepo->find($songId);
            if ($song === null) {
                $skipped++;
                continue;
            }
            if ($song->isAktuelleProben()) {
                $already++;
                continue;
            }
            // Always succeeds — the flag is set even when the song isn't on Dropbox.
            $syncService->pushSongToAktuelleProben($song);
            $added++;
        }

        if ($added === 0 && $already === 0 && $skipped === 0) {
            $this->addFlash('error', 'Diese Liste enthält keine Lieder, die hinzugefügt werden können.');
        } else {
            $parts = [];
            $parts[] = $added . ' ' . ($added === 1 ? 'Lied' : 'Lieder') . ' zu „Aktuelle Proben" hinzugefügt';
            if ($already > 0) {
                $parts[] = $already . ' bereits enthalten';
            }
            if ($skipped > 0) {
                $parts[] = $skipped . ' nicht gefunden';
            }
            $this->addFlash($added > 0 ? 'success' : 'error', implode(', ', $parts) . '.');
        }

        return $this->redirectToRoute('admin_liederlisten_edit', ['id' => $liste->getId()]);
    }

    /**
     * Normalises a Liederliste into flat rows shared by every export format
     * (DOCX/ODT via PhpWord and PDF/HTML via Twig). Each row carries the values
     * already resolved (running number, inherited Etikett + colour, note, …) so
     * the renderers only have to lay them out.
     *
     * @return array{showEtikett: bool, rows: list<array{num: string, etikett: string, etikettColor: ?string, title: string, note: string, composer: string, duration: string, isCustom: bool}>, total: string}
     */
    private function buildRows(Liederliste $liste, SongKeywordRepository $songRepo): array
    {
        $showEtikett = $liste->isShowEtikett();

        // Pre-load per-song data (etikett + whether the song is a parent "shell"
        // that only groups movements) keyed by song ID to avoid N+1 queries.
        $etikettMap  = [];
        $isParentMap = [];
        foreach ($liste->getItems() as $item) {
            $songId = $item['songId'] ?? null;
            if ($songId !== null && !array_key_exists($songId, $isParentMap)) {
                $song = $songRepo->find($songId);
                // Movements (child songs) inherit the parent's Etikett when their own is empty.
                $etikett = $song?->getEtikett() ?? '';
                if ($etikett === '' && $song?->getParent()) {
                    $etikett = $song->getParent()->getEtikett() ?? '';
                }
                $etikettMap[$songId]  = $etikett;
                $isParentMap[$songId] = $song !== null && $song->getChildren()->count() > 0;
            }
        }

        $rows    = [];
        $counter = 0;
        foreach ($liste->getItems() as $item) {
            $isCustom = ($item['type'] ?? '') === 'custom';
            $songId   = $item['songId'] ?? null;
            $isParent = $songId !== null && ($isParentMap[$songId] ?? false);

            // Parent songs are only a shell for their movements → no running number.
            $etikettText = $songId ? ($etikettMap[$songId] ?? '') : '';

            $rows[] = [
                'num'          => $isParent ? '' : (string) (++$counter),
                'etikett'      => $etikettText,
                'etikettColor' => $etikettText !== '' ? self::etikettColor($etikettText) : null,
                'title'        => (string) ($item['title'] ?? ''),
                'note'         => trim((string) ($item['note'] ?? '')),
                'composer'     => (string) ($item['composer'] ?? ''),
                'duration'     => (string) ($item['duration'] ?? ''),
                'isCustom'     => $isCustom,
            ];
        }

        return [
            'showEtikett' => $showEtikett,
            'rows'        => $rows,
            'total'       => $liste->getTotalDuration(),
        ];
    }

    private function buildPhpWord(Liederliste $liste, SongKeywordRepository $songRepo): PhpWord
    {
        $data        = $this->buildRows($liste, $songRepo);
        $showEtikett = $data['showEtikett'];

        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('de-DE'));

        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
            'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.0),
            'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
            'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
        ]);

        $subtitle = $liste->getTitle();
        $phpWord->addTitleStyle(1, ['name' => 'Calibri', 'size' => 16, 'bold' => true], ['spaceAfter' => $subtitle ? 40 : 240]);

        $section->addTitle($liste->getName(), 1);
        if ($subtitle) {
            $section->addText($subtitle, ['name' => 'Calibri', 'size' => 12, 'italic' => true, 'color' => '555555'], ['spaceAfter' => 240]);
        }

        // Column widths in twips — A4 text area: 16cm = 9072 twips (2.5cm margins each side)
        $wNum  = 500;
        $wDur  = 800;
        if ($showEtikett) {
            $wComposer = 2400;
            $wEtikett  = 1700;
            $wTitle    = 9072 - $wNum - $wComposer - $wEtikett - $wDur; // 3672
        } else {
            $wComposer = 2700;
            $wEtikett  = 0;
            $wTitle    = 9072 - $wNum - $wComposer - $wDur; // 5072
        }

        $tableStyle = [
            'borderSize'  => 6,
            'borderColor' => 'CCCCCC',
            'cellMargin'  => 80,
        ];
        $headerStyle = ['bold' => true, 'size' => 10];
        $cellBg      = ['bgColor' => 'E8E8E8'];

        $table = $section->addTable($tableStyle);

        $table->addRow();
        $table->addCell($wNum, $cellBg)->addText('#', $headerStyle, ['alignment' => Jc::CENTER]);
        if ($showEtikett) {
            $table->addCell($wEtikett, $cellBg)->addText('Etikett', $headerStyle);
        }
        $table->addCell($wTitle, $cellBg)->addText('Titel', $headerStyle);
        $table->addCell($wComposer, $cellBg)->addText('Komponist', $headerStyle);
        $table->addCell($wDur, $cellBg)->addText('Dauer', $headerStyle, ['alignment' => Jc::CENTER]);

        $numStyle    = ['size' => 10, 'color' => '888888'];
        $textStyle   = ['size' => 10];
        $italicStyle = ['size' => 10, 'italic' => true];

        foreach ($data['rows'] as $row) {
            $table->addRow();
            $table->addCell($wNum)->addText($row['num'], $numStyle, ['alignment' => Jc::CENTER]);

            if ($showEtikett) {
                $etikettStyle = $row['etikettColor'] !== null
                    ? ['size' => 10, 'color' => $row['etikettColor']]
                    : $textStyle;
                $table->addCell($wEtikett)->addText($row['etikett'], $etikettStyle);
            }

            $titleCell = $table->addCell($wTitle);
            $titlePara = $titleCell->addTextRun();
            $titlePara->addText($row['title'], $row['isCustom'] ? $italicStyle : $textStyle);
            if ($row['note'] !== '') {
                $titlePara->addText(' (' . $row['note'] . ')', ['size' => 9, 'italic' => true, 'color' => '888888']);
            }

            $table->addCell($wComposer)->addText($row['composer'], $textStyle);
            $table->addCell($wDur)->addText($row['duration'], ['size' => 10], ['alignment' => Jc::CENTER]);
        }

        $total = $data['total'];
        $table->addRow();
        $table->addCell($wNum);
        if ($showEtikett) {
            $table->addCell($wEtikett);
        }
        $table->addCell($wTitle);
        $table->addCell($wComposer)->addText('Gesamt', ['bold' => true, 'size' => 10], ['alignment' => Jc::RIGHT]);
        $table->addCell($wDur)->addText($total, ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);

        return $phpWord;
    }

    /**
     * Maps an Etikett to a text colour (hex, no '#') that echoes the Bootstrap badge
     * colours from the web page while staying readable on white. Keyed on the first
     * word, lower-cased.
     */
    private static function etikettColor(string $etikett): string
    {
        $prefix = strtolower(explode(' ', trim($etikett))[0] ?? '');

        return match ($prefix) {
            'blau'     => '0D6EFD', // primary blue
            'gelb'     => '997404', // readable amber (warning-text-emphasis)
            'rosa'     => 'DC3545', // danger red
            'extrabox' => '6C757D', // secondary gray
            default    => '212529', // dark
        };
    }

    #[Route('/fetch-duration', name: '_fetch_duration', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function fetchDuration(
        Request $request,
        SongKeywordRepository $songRepo,
        DropboxService $dropboxService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $songId = (int) $request->query->get('songId', 0);
        if ($songId <= 0) {
            return $this->json(['error' => 'Ungültige Song-ID'], 400);
        }

        $song = $songRepo->find($songId);
        if ($song === null) {
            return $this->json(['error' => 'Song nicht gefunden'], 404);
        }

        // Use the cached value; computing it (Dropbox audio download / YouTube
        // scrape) is expensive, so only do it once per song — unless the caller
        // explicitly asks to recompute ("neu berechnen").
        if (!$request->query->getBoolean('force') && !empty($song->getDuration())) {
            return $this->json(['duration' => $song->getDuration()]);
        }

        // Try Dropbox first
        $folderPath = $song->getAktuelleDropboxlink() ?? $song->getDropboxlink();
        if ($folderPath !== null) {
            $duration = $dropboxService->getFirstAudioDuration($folderPath);
            if ($duration !== null) {
                $song->setDuration($duration);
                $em->flush();
                return $this->json(['duration' => $duration]);
            }
        }

        // Try YouTube links
        foreach ($song->getLinks() as $link) {
            $duration = $this->getYoutubeDuration($link->getUrl());
            if ($duration !== null) {
                $song->setDuration($duration);
                $em->flush();
                return $this->json(['duration' => $duration]);
            }
        }

        return $this->json(['error' => 'Dauer nicht ermittelbar']);
    }

    private function handleForm(
        Liederliste $liste,
        Request $request,
        EntityManagerInterface $em,
        SongKeywordRepository $songRepo,
        bool $isNew,
    ): Response {
        if ($request->isMethod('POST')) {
            // NOTENWART users may view the edit page but never persist changes.
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $name = trim((string) $request->request->get('liste_name', ''));
            if ($name === '') {
                $this->addFlash('error', 'Bitte einen Namen eingeben.');
            } else {
                $liste->setName($name);
                $liste->setTitle(trim((string) $request->request->get('liste_title', '')) ?: null);
                $liste->setShowEtikett($request->request->has('show_etikett'));

                $itemsJson = (string) $request->request->get('items_json', '[]');
                $items     = json_decode($itemsJson, true);
                if (!is_array($items)) {
                    $items = [];
                }
                $liste->setItems($items);

                $em->persist($liste);
                $em->flush();

                $this->addFlash('success', $isNew ? 'Liederliste erstellt.' : 'Liederliste gespeichert.');
                return $this->redirectToRoute('admin_liederlisten_edit', ['id' => $liste->getId()]);
            }
        }

        $allSongs = $songRepo->findBy([], ['songName' => 'ASC']);

        $songData = [];
        foreach ($allSongs as $song) {
            $songData[] = [
                'id'       => $song->getId(),
                'parentId' => $song->getParent()?->getId(),
                'composer' => $song->getComposer() ?? '',
                'title'    => $song->getSongName() ?? '',
                'etikett'  => $song->getEtikett() ?? '',
                'hasDropbox' => $song->getDropboxlink() !== null || $song->getAktuelleDropboxlink() !== null,
                'hasLinks'   => !$song->getLinks()->isEmpty(),
            ];
        }

        return $this->render('admin/liederliste/edit.html.twig', [
            'liste'    => $liste,
            'isNew'    => $isNew,
            'songData' => $songData,
        ]);
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
