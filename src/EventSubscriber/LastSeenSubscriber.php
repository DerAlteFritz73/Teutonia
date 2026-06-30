<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records when a member was last active on the site (lastSeenAt), updated once
 * per visit rather than only at (re-)authentication like lastLoginAt. Runs on
 * kernel.terminate — after the response is sent, so it never slows the request —
 * and throttles writes to at most one per THROTTLE seconds per user.
 */
class LastSeenSubscriber implements EventSubscriberInterface
{
    private const THROTTLE = 300; // seconds (5 min)

    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $now = new \DateTime();
        $last = $user->getLastSeenAt();
        if ($last !== null && ($now->getTimestamp() - $last->getTimestamp()) < self::THROTTLE) {
            return;
        }

        // Targeted UPDATE (not a full flush) so we never accidentally persist
        // other in-flight entity changes from the just-finished request.
        $this->em->createQuery('UPDATE App\Entity\User u SET u.lastSeenAt = :now WHERE u.id = :id')
            ->setParameter('now', $now)
            ->setParameter('id', $user->getId())
            ->execute();
    }
}
