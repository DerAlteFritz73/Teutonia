<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetController extends AbstractController
{
    #[Route('/passwort-vergessen', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $error = $request->query->get('error') === 'mail'
            ? 'Die E-Mail konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder wenden Sie sich an einen Administrator.'
            : null;
        $success = $request->query->has('sent');
        $noEmail = $request->query->has('no_email');
        $maskedEmail = $request->query->get('email');

        $identifier = $request->isMethod('POST')
            ? $request->request->get('identifier')
            : null;

        if ($identifier) {
            // Find user by username or email
            $user = $userRepository->findOneBy(['username' => $identifier])
                ?? $userRepository->findOneBy(['email' => $identifier]);

            if ($user && !$user->getEmail()) {
                return $this->redirectToRoute('app_forgot_password', ['no_email' => 1]);
            } elseif ($user && $user->getEmail()) {
                $maskedEmail = $this->maskEmail($user->getEmail());

                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $user->setResetToken($token);
                $user->setResetTokenExpiresAt(new \DateTime('+1 hour'));
                $em->flush();

                // Generate absolute reset URL
                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                // Send email
                try {
                    $email = (new Email())
                        ->from('chor.teutonia@gmail.com')
                        ->to($user->getEmail())
                        ->subject('Passwort zurücksetzen - Chorgemeinschaft Teutonia')
                        ->html($this->renderView('emails/password_reset.html.twig', [
                            'user' => $user,
                            'resetUrl' => $resetUrl,
                        ]));

                    $mailer->send($email);
                } catch (\Exception $e) {
                    return $this->redirectToRoute('app_forgot_password', ['error' => 'mail']);
                }
            }

            // Redirect after POST so Turbo (and browser refresh) work correctly.
            // Always redirect on success (even if user not found) to avoid revealing account existence.
            return $this->redirectToRoute('app_forgot_password', [
                'sent' => 1,
                'email' => $maskedEmail,
            ]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'error' => $error,
            'success' => $success,
            'no_email' => $noEmail,
            'masked_email' => $maskedEmail,
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = min(3, strlen($local));
        return substr($local, 0, $visible) . str_repeat('*', max(3, strlen($local) - $visible)) . '@' . $domain;
    }

    #[Route('/passwort-zuruecksetzen/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            $this->addFlash('error', 'Der Link ist ungültig oder abgelaufen.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');

            if (strlen($password) < 6) {
                $error = 'Das Passwort muss mindestens 6 Zeichen lang sein.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Die Passwörter stimmen nicht überein.';
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Ihr Passwort wurde erfolgreich geändert. Sie können sich jetzt anmelden.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'error' => $error,
            'token' => $token,
        ]);
    }
}
