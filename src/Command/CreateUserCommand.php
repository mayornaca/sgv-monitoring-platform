<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a new user',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::REQUIRED, 'User password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Create an admin user')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Last name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getArgument('email');
        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');
        
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        
        if ($input->getOption('firstname')) {
            $user->setFirstName($input->getOption('firstname'));
        }
        
        if ($input->getOption('lastname')) {
            $user->setLastName($input->getOption('lastname'));
        }
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        
        // Set roles
        $roles = ['ROLE_USER'];
        if ($input->getOption('admin')) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);
        
        // Persist user
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $io->success(sprintf('User %s created successfully!', $email));
        
        return Command::SUCCESS;
    }
}