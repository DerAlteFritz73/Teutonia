<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\Post;
use App\Entity\PracticeLink;
use App\Entity\User;
use App\Form\EventType;
use App\Form\PostType;
use App\Form\PracticeLinkType;
use App\Form\UserType;
use App\Repository\EventRepository;
use App\Repository\PostRepository;
use App\Repository\PracticeLinkRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Spatie\PdfToImage\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function dashboard(
        UserRepository $userRepository,
        PracticeLinkRepository $linkRepository,
        EventRepository $eventRepository,
        PostRepository $postRepository
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'userCount' => count($userRepository->findAll()),
            'linkCount' => count($linkRepository->findAll()),
            'eventCount' => count($eventRepository->findAll()),
            'postCount' => count($postRepository->findAll()),
        ]);
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
        $form = $this->createForm(UserType::class, $user);
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

    // ==================== PRACTICE LINKS ====================

    #[Route('/links', name: 'admin_links')]
    public function links(PracticeLinkRepository $linkRepository): Response
    {
        return $this->render('admin/links/index.html.twig', [
            'links' => $linkRepository->findAllOrderedBySortOrder(),
        ]);
    }

    #[Route('/links/new', name: 'admin_links_new')]
    public function linksNew(Request $request, EntityManagerInterface $em): Response
    {
        $link = new PracticeLink();
        $form = $this->createForm(PracticeLinkType::class, $link);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($link);
            $em->flush();

            $this->addFlash('success', 'Link wurde erstellt.');
            return $this->redirectToRoute('admin_links');
        }

        return $this->render('admin/links/form.html.twig', [
            'form' => $form,
            'link' => $link,
            'isNew' => true,
        ]);
    }

    #[Route('/links/{id}/edit', name: 'admin_links_edit')]
    public function linksEdit(PracticeLink $link, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PracticeLinkType::class, $link);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Link wurde aktualisiert.');
            return $this->redirectToRoute('admin_links');
        }

        return $this->render('admin/links/form.html.twig', [
            'form' => $form,
            'link' => $link,
            'isNew' => false,
        ]);
    }

    #[Route('/links/{id}/delete', name: 'admin_links_delete', methods: ['POST'])]
    public function linksDelete(PracticeLink $link, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $link->getId(), $request->request->get('_token'))) {
            $em->remove($link);
            $em->flush();
            $this->addFlash('success', 'Link wurde gelöscht.');
        }

        return $this->redirectToRoute('admin_links');
    }

    // ==================== EVENTS ====================

    #[Route('/termine', name: 'admin_events')]
    public function events(EventRepository $eventRepository): Response
    {
        return $this->render('admin/termine/index.html.twig', [
            'events' => $eventRepository->findUpcoming(),
        ]);
    }

    #[Route('/termine/new', name: 'admin_events_new')]
    public function eventsNew(Request $request, EntityManagerInterface $em): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'Termin wurde erstellt.');
            return $this->redirectToRoute('admin_events');
        }

        return $this->render('admin/termine/form.html.twig', [
            'form' => $form,
            'event' => $event,
            'isNew' => true,
        ]);
    }

    #[Route('/termine/{id}/edit', name: 'admin_events_edit')]
    public function eventsEdit(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Termin wurde aktualisiert.');
            return $this->redirectToRoute('admin_events');
        }

        return $this->render('admin/termine/form.html.twig', [
            'form' => $form,
            'event' => $event,
            'isNew' => false,
        ]);
    }

    #[Route('/termine/{id}/delete', name: 'admin_events_delete', methods: ['POST'])]
    public function eventsDelete(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'Termin wurde gelöscht.');
        }

        return $this->redirectToRoute('admin_events');
    }

    // ==================== POSTS ====================

    #[Route('/beitraege', name: 'admin_posts')]
    public function posts(PostRepository $postRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $filterPage = $request->query->get('filter_page');

        if ($filterPage === 'all' || $filterPage === null) {
            $posts = $postRepository->findAllOrderedWithMainFirst($page, $limit);
            $totalCount = $postRepository->countAll();
            $filterPage = $filterPage ?? 'all'; // Set to 'all' if null for template
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
            return $this->redirectToRoute('admin_posts');
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
            return $this->redirectToRoute('admin_posts');
        }

        return $this->render('admin/posts/form.html.twig', [
            'form' => $form,
            'post' => $post,
            'isNew' => false,
        ]);
    }

    #[Route('/beitraege/{id}/delete', name: 'admin_posts_delete', methods: ['POST'])]
    public function postsDelete(Post $post, Request $request, EntityManagerInterface $em): Response
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

        return $this->redirectToRoute('admin_posts');
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

                $pdf = new Pdf($tempPdfPath);
                $pdf->setOutputFormat('jpg')
                    ->setResolution(150)
                    ->saveImage($outputPath);

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
