<?php

namespace App\Controller;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        return $this->render('pages/konzerte-und-aktivitaeten.html.twig', [
            'posts' => $postRepository->findByPage('konzerte-und-aktivitaeten'),
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
    public function repertoire(PostRepository $postRepository): Response
    {
        return $this->render('pages/unser-repertoire.html.twig', [
            'posts' => $postRepository->findByPage('unser-repertoire'),
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
}
