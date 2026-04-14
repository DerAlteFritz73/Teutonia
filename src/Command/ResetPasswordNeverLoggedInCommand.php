<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-password-never-logged-in',
    description: 'Reset password to "teutonia" for all users who have never logged in.',
)]
class ResetPasswordNeverLoggedInCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = array_filter(
            $this->userRepository->findAll(),
            fn($u) => $u->getLoginCount() === 0
        );

        if (empty($users)) {
            $io->success('No users with zero logins found.');
            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            $user->setPassword($this->passwordHasher->hashPassword($user, 'teutonia'));
            $io->writeln(sprintf('  Reset: %s', $user->getUserIdentifier()));
        }

        $this->em->flush();

        $io->success(sprintf('Reset password for %d user(s).', count($users)));

        return Command::SUCCESS;
    }
}
