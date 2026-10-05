<?php

namespace App\Controller;

use App\Repository\KonzertRepository;
use App\Repository\PostRepository;
use App\Repository\ScoreSyncAnchorsRepository;
use App\Repository\SongKeywordRepository;
use App\Repository\StyleRepository;
use App\Service\ArchiveService;
use App\Service\PdfEtikettStamper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PageController extends AbstractController
{
    #[Route('/unser-chor', name: 'page_chor')]
    public function unserChor(PostRepository $postRepository): Response
    {
        return $this->render('pages/unser-chor.html.twig', [
            'posts' => $postRepository->findByPage('unser-chor'),
        ]);
    }

    #[Route('/konzerte-und-aktivitaeten', name: 'page_concerts')]
    public function konzerteUndAktivitaeten(PostRepository $postRepository, KonzertRepository $konzertRepository): Response
    {
        $posts = $postRepository->findByPageOrderedByDate('konzerte-und-aktivitaeten');
        $konzerte = $konzertRepository->findAllOrdered();

        // Extract unique years from posts and concerts
        $years = [];
        foreach ($posts as $post) {
            if ($post->getDate()) {
                $year = substr($post->getDate(), 0, 4);
                if ($year && !in_array($year, $years)) {
                    $years[] = $year;
                }
            }
        }
        foreach ($konzerte as $konzert) {
            if ($konzert->getDate()) {
                $year = $konzert->getDate()->format('Y');
                if ($year && !in_array($year, $years)) {
                    $years[] = $year;
                }
            }
        }
        rsort($years); // Sort years in descending order

        return $this->render('pages/konzerte-und-aktivitaeten.html.twig', [
            'posts' => $posts,
            'konzerte' => $konzerte,
            'years' => $years,
        ]);
    }

    #[Route('/historie', name: 'page_history')]
    public function historie(PostRepository $postRepository): Response
    {
        return $this->render('pages/historie.html.twig', [
            'posts' => $postRepository->findByPage('historie'),
        ]);
    }

    #[Route('/chorproben', name: 'page_rehearsals')]
    public function chorproben(PostRepository $postRepository): Response
    {
        return $this->render('pages/chorproben.html.twig', [
            'posts' => $postRepository->findByPage('chorproben'),
        ]);
    }

    #[Route('/unser-repertoire', name: 'page_repertoire')]
    public function repertoire(PostRepository $postRepository, StyleRepository $styleRepository): Response
    {
        return $this->render('pages/unser-repertoire.html.twig', [
            'posts'  => $postRepository->findByPage('unser-repertoire'),
            'styles' => $styleRepository->findAllWithSongs(),
        ]);
    }

    #[Route('/unsere-naechsten-termine', name: 'page_events')]
    public function unsereTerrnine(PostRepository $postRepository): Response
    {
        return $this->render('pages/unsere-naechsten-termine.html.twig', [
            'posts' => $postRepository->findByPage('unsere-naechsten-termine'),
        ]);
    }

    #[Route('/beitraege', name: 'page_posts')]
    public function beitraege(PostRepository $postRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        return $this->render('pages/beitraege.html.twig', [
            'posts' => $postRepository->findByPagePaginated('beitraege', $page, $limit),
            'currentPage' => $page,
            'totalPages' => ceil($postRepository->countByPage('beitraege') / $limit),
        ]);
    }

    #[Route('/api/files/song-files', name: 'api_files_song_files', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSongFiles(Request $request, ArchiveService $archive): JsonResponse
    {
        $path = $request->query->get('path');

        if (!$path) {
            return new JsonResponse(['error' => 'Path is required'], 400);
        }

        $result = $archive->getFilesForFolder($path);

        return new JsonResponse([
            'files'         => $result['files'],
            'hasSubfolders' => $result['hasSubfolders'],
        ]);
    }

    /** URL the browser can load a file from (audio player src, download link). */
    #[Route('/api/files/link', name: 'api_files_link', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function getFileLink(Request $request, ArchiveService $archive): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $path = $data['path'] ?? null;

        if (!$path) {
            return new JsonResponse(['error' => 'Path is required'], 400);
        }

        if ($archive->getLocalPath($path) === null) {
            return new JsonResponse(['error' => 'Datei nicht gefunden'], 404);
        }

        return new JsonResponse(['link' => $this->generateUrl('api_files_view', ['path' => $path])]);
    }

    #[Route('/api/files/view', name: 'api_files_view', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function viewFile(
        Request $request,
        ArchiveService $archive,
        SongKeywordRepository $songRepo,
        PdfEtikettStamper $stamper,
    ): Response {
        $path = $request->query->get('path');

        if (!$path) {
            throw $this->createNotFoundException('Path is required');
        }

        $localPath = $archive->getLocalPath($path);

        if ($localPath === null) {
            throw $this->createNotFoundException('File not found');
        }

        // Determine content type based on file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match($extension) {
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'webm' => 'video/webm',
            'mxl' => 'application/vnd.recordare.musicxml',
            'musicxml' => 'application/vnd.recordare.musicxml+xml',
            default => 'application/octet-stream'
        };

        if ($extension !== 'pdf') {
            // Streamed from disk with Range support, so audio/video can seek.
            $response = new BinaryFileResponse($localPath);
            $response->headers->set('Content-Type', $contentType);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                basename($localPath),
                $this->asciiFilename(basename($localPath))
            );
            return $response;
        }

        $fileContent = @file_get_contents($localPath);

        if ($fileContent === false) {
            throw $this->createNotFoundException('Could not read file');
        }

        // Overlay the song's Etikett on page 1 of Noten PDFs.
        // The original in the archive is never modified.
        $song = $songRepo->findOneByFolder(\dirname($path));
        if ($song) {
            // Movements (child songs) usually carry no Etikett of their own —
            // fall back to the parent's, just like the rest of the app does.
            $etikett = trim((string) $song->getEtikett());
            if ($etikett === '' && $song->getParent() !== null) {
                $etikett = trim((string) $song->getParent()->getEtikett());
            }
            if ($etikett !== '') {
                $fileContent = $stamper->stamp($fileContent, $etikett);
            }
        }

        $response = new Response($fileContent);
        $response->headers->set('Content-Type', $contentType);

        // inline to view in the browser; attachment (?dl=1) to download a stamped copy
        $disposition = $request->query->getBoolean('dl') ? 'attachment' : 'inline';
        $response->headers->set('Content-Disposition', $disposition . '; filename="' . basename($path) . '"');

        return $response;
    }

    /** ASCII fallback for Content-Disposition (umlauts etc. go in filename*). */
    private function asciiFilename(string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $name) ?? 'datei';
        return $ascii !== '' ? $ascii : 'datei';
    }

    #[Route('/api/sync-anchors', name: 'api_sync_anchors', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSyncAnchors(Request $request, ScoreSyncAnchorsRepository $repo): JsonResponse
    {
        $pdfPath   = (string) $request->query->get('pdf', '');
        $audioPath = (string) $request->query->get('audio', '');

        if ($pdfPath === '' || $audioPath === '') {
            return new JsonResponse(['error' => 'pdf and audio params required'], 400);
        }

        $record = $repo->findByPaths($pdfPath, $audioPath);

        return new JsonResponse(['anchors' => $record ? $record->getAnchors() : null]);
    }

    #[Route('/api/wordcloud', name: 'api_wordcloud', methods: ['GET'])]
    public function getWordCloudData(SongKeywordRepository $songKeywordRepository, CacheInterface $cache): JsonResponse
    {
        $wordCloudData = $cache->get('wordcloud_data', function (ItemInterface $item) use ($songKeywordRepository) {
            $item->expiresAfter(86400); // 1 day TTL

            $words = [];

            // Composers — sized by number of songs (appear larger when repeated)
            foreach ($songKeywordRepository->getComposerFrequency('Noten') as $row) {
                $words[] = [
                    'text' => $row['composer'],
                    'size' => (int)$row['frequency'] * 3,
                ];
            }

            // Song titles — each counts as 1 (appears smaller than composers)
            foreach ($songKeywordRepository->getSongTitles('Noten') as $title) {
                $words[] = [
                    'text' => $title,
                    'size' => 1,
                ];
            }

            return $words;
        });

        return new JsonResponse($wordCloudData);
    }
}
