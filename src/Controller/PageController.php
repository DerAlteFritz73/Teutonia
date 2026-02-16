<?php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\SongKeywordRepository;
use App\Service\DropboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function konzerteUndAktivitaeten(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findByPage('konzerte-und-aktivitaeten');

        // Extract unique years from posts
        $years = [];
        foreach ($posts as $post) {
            if ($post->getDate()) {
                $year = substr($post->getDate(), 0, 4);
                if ($year && !in_array($year, $years)) {
                    $years[] = $year;
                }
            }
        }
        rsort($years); // Sort years in descending order

        return $this->render('pages/konzerte-und-aktivitaeten.html.twig', [
            'posts' => $posts,
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
    public function repertoire(PostRepository $postRepository, DropboxService $dropboxService): Response
    {
        // Get Dropbox file structure
        $dropboxFiles = $dropboxService->getFileStructure('/Chorgemeinschaft Teutonia');

        return $this->render('pages/unser-repertoire.html.twig', [
            'posts' => $postRepository->findByPage('unser-repertoire'),
            'dropboxFiles' => $dropboxFiles,
        ]);
    }

    #[Route('/unsere-naechsten-termine', name: 'page_events')]
    public function unsereTerrnine(PostRepository $postRepository): Response
    {
        return $this->render('pages/unsere-naechsten-termine.html.twig', [
            'posts' => $postRepository->findByPage('unsere-naechsten-termine'),
        ]);
    }

    #[Route('/geselliges', name: 'page_social')]
    public function geselliges(PostRepository $postRepository): Response
    {
        return $this->render('pages/geselliges.html.twig', [
            'posts' => $postRepository->findByPage('geselliges'),
        ]);
    }

    #[Route('/archiv', name: 'page_archive')]
    public function archiv(PostRepository $postRepository): Response
    {
        return $this->render('pages/archiv.html.twig', [
            'posts' => $postRepository->findByPage('archiv'),
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
    public function viewDropboxFile(Request $request, DropboxService $dropboxService): Response
    {
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
            default => 'application/octet-stream'
        };

        // Create response with inline content disposition for PDFs
        $response = new Response($fileContent);
        $response->headers->set('Content-Type', $contentType);

        if ($extension === 'pdf') {
            // For PDFs, use inline disposition to show in browser
            $response->headers->set('Content-Disposition', 'inline; filename="' . basename($path) . '"');
        } else {
            // For other files, allow default behavior
            $response->headers->set('Content-Disposition', 'inline; filename="' . basename($path) . '"');
        }

        return $response;
    }

    #[Route('/api/wordcloud', name: 'api_wordcloud', methods: ['GET'])]
    public function getWordCloudData(SongKeywordRepository $songKeywordRepository): JsonResponse
    {
        $composerData = $songKeywordRepository->getComposerFrequency('Noten');

        // Format for word cloud library (array of {text, size})
        $wordCloudData = array_map(function($item) {
            return [
                'text' => $item['composer'],
                'size' => (int)$item['frequency']
            ];
        }, $composerData);

        return new JsonResponse($wordCloudData);
    }
}
