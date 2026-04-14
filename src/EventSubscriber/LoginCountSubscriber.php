<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginCountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $securityLogger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getAuthenticatedToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->incrementLoginCount();
        $user->setLastLoginAt(new \DateTime());
        $this->em->flush();

        $ip = $event->getRequest()->getClientIp();
        $this->securityLogger->info('Login successful', [
            'username' => $user->getUserIdentifier(),
            'ip'       => $ip,
        ]);

        if (!$user->getEmail()) {
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_collect_email')
            ));
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $ip       = $event->getRequest()->getClientIp();
        $username = $event->getRequest()->request->get('_username', '(unknown)');
        $reason   = $event->getException()?->getMessageKey() ?? 'unknown';

        $this->securityLogger->warning('Login failed', [
            'username' => $username,
            'ip'       => $ip,
            'reason'   => $reason,
        ]);
    }
}
