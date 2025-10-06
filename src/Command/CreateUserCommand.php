<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Create a new user')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'User email')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'User password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Set user as admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        if (!$email) {
            $email = $io->ask('Email');
        }

        $username = $input->getOption('username');
        if (!$username) {
            $username = $io->ask('Username');
        }

        $password = $input->getOption('password');
        if (!$password) {
            $password = $io->askHidden('Password');
        }

        $isAdmin = $input->getOption('admin');

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        if ($isAdmin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('User "%s" created successfully!', $username));

        return Command::SUCCESS;
    }
}
