<?php

namespace App\Controller;

use App\Repository\KonzertRepository;
use App\Repository\PostRepository;
use App\Repository\ScoreSyncAnchorsRepository;
use App\Repository\SongKeywordRepository;
use App\Repository\StyleRepository;
use App\Service\DropboxService;
use App\Service\PdfEtikettStamper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route('/api/dropbox/song-files', name: 'api_dropbox_song_files', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSongFiles(Request $request, DropboxService $dropboxService): JsonResponse
    {
        $path = $request->query->get('path');

        if (!$path) {
            return new JsonResponse(['error' => 'Path is required'], 400);
        }

        $result = $dropboxService->getFilesForFolder($path);

        return new JsonResponse([
            'files'         => $result['files'],
            'hasSubfolders' => $result['hasSubfolders'],
        ]);
    }

    #[Route('/api/dropbox/link', name: 'api_dropbox_link', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function getDropboxLink(Request $request, DropboxService $dropboxService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $path = $data['path'] ?? null;

        if (!$path) {
            return new JsonResponse(['error' => 'Path is required'], 400);
        }

        $link = $dropboxService->getTemporaryLink($path);

        if (!$link) {
            return new JsonResponse(['error' => 'Could not generate link'], 500);
        }

        return new JsonResponse(['link' => $link]);
    }

    #[Route('/api/dropbox/view', name: 'api_dropbox_view', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function viewDropboxFile(
        Request $request,
        DropboxService $dropboxService,
        SongKeywordRepository $songRepo,
        PdfEtikettStamper $stamper,
    ): Response {
        $path = $request->query->get('path');

        if (!$path) {
            throw $this->createNotFoundException('Path is required');
        }

        // Get temporary link from Dropbox
        $link = $dropboxService->getTemporaryLink($path);

        if (!$link) {
            throw $this->createNotFoundException('Could not generate link');
        }

        // Fetch the file content from Dropbox
        $fileContent = @file_get_contents($link);

        if ($fileContent === false) {
            throw $this->createNotFoundException('Could not fetch file');
        }

        // Determine content type based on file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match($extension) {
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'webm' => 'video/webm',
            'mxl' => 'application/vnd.recordare.musicxml',
            'musicxml' => 'application/vnd.recordare.musicxml+xml',
            default => 'application/octet-stream'
        };

        // Overlay the song's Etikett on page 1 of Noten PDFs.
        // The original in Dropbox is never modified.
        if ($extension === 'pdf') {
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
        }

        // Create response with inline content disposition for PDFs
        $response = new Response($fileContent);
        $response->headers->set('Content-Type', $contentType);

        if ($extension === 'pdf') {
            // inline to view in the browser; attachment (?dl=1) to download a stamped copy
            $disposition = $request->query->getBoolean('dl') ? 'attachment' : 'inline';
            $response->headers->set('Content-Disposition', $disposition . '; filename="' . basename($path) . '"');
        } else {
            // For other files, allow default behavior
            $response->headers->set('Content-Disposition', 'inline; filename="' . basename($path) . '"');
        }

        return $response;
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
