<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\PracticeLinkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mitglieder')]
class MemberController extends AbstractController
{
    #[Route('', name: 'member_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('member/dashboard.html.twig');
    }

    #[Route('/aktuelle-proben', name: 'member_proben')]
    public function proben(PracticeLinkRepository $practiceLinkRepository): Response
    {
        $songGroups = $practiceLinkRepository->findAllGroupedBySong();

        return $this->render('member/proben.html.twig', [
            'songGroups' => $songGroups,
        ]);
    }

    #[Route('/kalender', name: 'member_kalender')]
    public function kalender(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findUpcoming();

        return $this->render('member/kalender.html.twig', [
            'events' => $events,
        ]);
    }
}
