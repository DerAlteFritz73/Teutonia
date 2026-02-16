<?php

namespace App\Controller;

use App\Entity\SongSuggestion;
use App\Entity\SongSuggestionLike;
use App\Form\SongSuggestionType;
use App\Repository\EventRepository;
use App\Repository\PracticeLinkRepository;
use App\Repository\SongSuggestionLikeRepository;
use App\Repository\SongSuggestionRepository;
use App\Service\DropboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function proben(DropboxService $dropboxService): Response
    {
        // Get Dropbox file structure from "Aktuelle Proben" folder
        $dropboxFiles = $dropboxService->getFileStructure('/Chorgemeinschaft Teutonia/Aktuelle Proben');

        return $this->render('member/proben.html.twig', [
            'dropboxFiles' => $dropboxFiles,
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

    #[Route('/ideenkiste', name: 'member_ideenkiste')]
    public function ideenkiste(SongSuggestionRepository $suggestionRepo, Request $request): Response
    {
        $sort = $request->query->get('sort', 'newest');

        $suggestions = $suggestionRepo->findAllWithLikeCounts($sort === 'popular' ? 'popular' : 'newest');

        return $this->render('member/ideenkiste.html.twig', [
            'suggestions' => $suggestions,
            'sort' => $sort,
        ]);
    }

    #[Route('/ideenkiste/neu', name: 'member_ideenkiste_new')]
    public function ideenkisteNew(Request $request, EntityManagerInterface $em): Response
    {
        $suggestion = new SongSuggestion();
        $form = $this->createForm(SongSuggestionType::class, $suggestion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $suggestion->setSubmittedBy($this->getUser());

            // Handle PDF upload
            $pdfFile = $form->get('pdfFile')->getData();
            if ($pdfFile) {
                $pdfPath = $this->handlePdfUpload($pdfFile);
                $suggestion->setPdfPath($pdfPath);
            }

            $em->persist($suggestion);
            $em->flush();

            $this->addFlash('success', 'Vorschlag erfolgreich eingereicht!');
            return $this->redirectToRoute('member_ideenkiste');
        }

        return $this->render('member/ideenkiste_form.html.twig', [
            'form' => $form,
            'suggestion' => $suggestion,
            'isNew' => true,
        ]);
    }

    #[Route('/ideenkiste/{id}/bearbeiten', name: 'member_ideenkiste_edit')]
    public function ideenkisteEdit(SongSuggestion $suggestion, Request $request, EntityManagerInterface $em): Response
    {
        // Security check: only allow editing own suggestions
        if ($suggestion->getSubmittedBy() !== $this->getUser()) {
            $this->addFlash('error', 'Sie können nur eigene Vorschläge bearbeiten.');
            return $this->redirectToRoute('member_ideenkiste');
        }

        $form = $this->createForm(SongSuggestionType::class, $suggestion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle PDF upload (replace old if new uploaded)
            $pdfFile = $form->get('pdfFile')->getData();
            if ($pdfFile) {
                $pdfPath = $this->handlePdfUpload($pdfFile, $suggestion->getPdfPath());
                $suggestion->setPdfPath($pdfPath);
            }

            $suggestion->setUpdatedAt(new \DateTime());
            $em->flush();

            $this->addFlash('success', 'Änderungen gespeichert!');
            return $this->redirectToRoute('member_ideenkiste');
        }

        return $this->render('member/ideenkiste_form.html.twig', [
            'form' => $form,
            'suggestion' => $suggestion,
            'isNew' => false,
        ]);
    }

    #[Route('/ideenkiste/{id}/loeschen', name: 'member_ideenkiste_delete', methods: ['POST'])]
    public function ideenkisteDelete(SongSuggestion $suggestion, Request $request, EntityManagerInterface $em): Response
    {
        // Security check: only allow deleting own suggestions
        if ($suggestion->getSubmittedBy() !== $this->getUser()) {
            $this->addFlash('error', 'Sie können nur eigene Vorschläge löschen.');
            return $this->redirectToRoute('member_ideenkiste');
        }

        // Validate CSRF token
        if ($this->isCsrfTokenValid('delete' . $suggestion->getId(), $request->request->get('_token'))) {
            // Delete PDF file if exists
            if ($suggestion->getPdfPath()) {
                $pdfFullPath = $this->getParameter('kernel.project_dir') . '/public/' . $suggestion->getPdfPath();
                if (file_exists($pdfFullPath)) {
                    unlink($pdfFullPath);
                }
            }

            $em->remove($suggestion);
            $em->flush();

            $this->addFlash('success', 'Vorschlag gelöscht.');
        }

        return $this->redirectToRoute('member_ideenkiste');
    }

    #[Route('/ideenkiste/{id}/like', name: 'member_ideenkiste_like', methods: ['POST'])]
    public function ideenkisteLike(
        SongSuggestion $suggestion,
        EntityManagerInterface $em,
        SongSuggestionLikeRepository $likeRepo
    ): JsonResponse {
        $user = $this->getUser();
        $existingLike = $likeRepo->findLikeByUserAndSuggestion($user, $suggestion);

        if ($existingLike) {
            // Unlike
            $em->remove($existingLike);
            $em->flush();
            $liked = false;
        } else {
            // Like
            $like = new SongSuggestionLike();
            $like->setUser($user);
            $like->setSuggestion($suggestion);
            $em->persist($like);
            $em->flush();
            $liked = true;
        }

        return new JsonResponse([
            'liked' => $liked,
            'likeCount' => $suggestion->getLikeCount(),
        ]);
    }

    private function handlePdfUpload($pdfFile, ?string $oldPdfPath = null): ?string
    {
        if (!$pdfFile) {
            return null;
        }

        // Delete old PDF if exists
        if ($oldPdfPath) {
            $oldPdfFullPath = $this->getParameter('kernel.project_dir') . '/public/' . $oldPdfPath;
            if (file_exists($oldPdfFullPath)) {
                unlink($oldPdfFullPath);
            }
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/pdfs/suggestions';

        // Create directory if doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
            $originalFilename
        );
        $newFilename = $safeFilename . '-' . uniqid() . '.pdf';

        $pdfFile->move($uploadDir, $newFilename);

        return 'pdfs/suggestions/' . $newFilename;
    }
}
