<?php

namespace App\Controller;

use App\Repository\PostRepository;
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
}
