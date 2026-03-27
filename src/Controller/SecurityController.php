<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('member_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/email-erfassen', name: 'app_collect_email')]
    public function collectEmail(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user || $user->getEmail()) {
            return $this->redirectToRoute('member_dashboard');
        }

        if ($request->isMethod('POST')) {
            $email = trim($request->request->getString('_email'));
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $user->setEmail($email);
                $em->flush();
            }
            return $this->redirectToRoute('member_dashboard');
        }

        return $this->render('security/collect_email.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
