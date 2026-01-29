<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    #[Route('/unser-chor', name: 'page_chor')]
    public function unserChor(): Response
    {
        return $this->render('pages/unser-chor.html.twig');
    }

    #[Route('/konzerte-und-aktivitaeten', name: 'page_concerts')]
    public function konzerteUndAktivitaeten(): Response
    {
        return $this->render('pages/konzerte-und-aktivitaeten.html.twig');
    }

    #[Route('/historie', name: 'page_history')]
    public function historie(): Response
    {
        return $this->render('pages/historie.html.twig');
    }

    #[Route('/chorproben', name: 'page_rehearsals')]
    public function chorproben(): Response
    {
        return $this->render('pages/chorproben.html.twig');
    }

    #[Route('/unser-repertoire', name: 'page_repertoire')]
    public function repertoire(): Response
    {
        return $this->render('pages/unser-repertoire.html.twig');
    }

    #[Route('/unsere-naechsten-termine', name: 'page_events')]
    public function unsereTerrnine(): Response
    {
        return $this->render('pages/unsere-naechsten-termine.html.twig');
    }

    #[Route('/geselliges', name: 'page_social')]
    public function geselliges(): Response
    {
        return $this->render('pages/geselliges.html.twig');
    }

    #[Route('/archiv', name: 'page_archive')]
    public function archiv(): Response
    {
        return $this->render('pages/archiv.html.twig');
    }
}
