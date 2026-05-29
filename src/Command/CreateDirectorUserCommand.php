<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
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
    name: 'app:user:create-director',
    description: 'Create or update the first director account stored in the database.',
)]
class CreateDirectorUserCommand extends Command
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
            ->addArgument('username', InputArgument::OPTIONAL, 'Director username. Falls back to APP_INITIAL_DIRECTOR_USERNAME.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Director password. Falls back to APP_INITIAL_DIRECTOR_PASSWORD.')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Director first name.', 'Direction')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Director last name.', 'OZ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = strtolower(trim((string) ($input->getArgument('username') ?: $this->env('APP_INITIAL_DIRECTOR_USERNAME'))));
        $password = (string) ($input->getArgument('password') ?: $this->env('APP_INITIAL_DIRECTOR_PASSWORD'));
        $firstName = trim((string) ($input->getOption('first-name') ?: $this->env('APP_INITIAL_DIRECTOR_FIRST_NAME') ?: 'Direction'));
        $lastName = trim((string) ($input->getOption('last-name') ?: $this->env('APP_INITIAL_DIRECTOR_LAST_NAME') ?: 'OZ'));

        if ($username === '' || $password === '') {
            $io->error('Renseigne username/password en arguments ou via APP_INITIAL_DIRECTOR_USERNAME et APP_INITIAL_DIRECTOR_PASSWORD.');

            return Command::INVALID;
        }

        if (mb_strlen($password) < 8) {
            $io->error('Le mot de passe direction doit contenir au moins 8 caractères.');

            return Command::INVALID;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $created = false;
        if (!$user instanceof User) {
            $user = new User();
            $created = true;
            $this->entityManager->persist($user);
        }

        $user
            ->setUsername($username)
            ->setFirstName($firstName !== '' ? $firstName : 'Direction')
            ->setLastName($lastName !== '' ? $lastName : 'OZ')
            ->setRole(UserRole::Director)
            ->setActive(true)
            ->setMustChangePassword(false)
            ->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->flush();

        $io->success(sprintf(
            '%s compte direction "%s".',
            $created ? 'Création du' : 'Mise à jour du',
            $username,
        ));

        return Command::SUCCESS;
    }

    private function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name) ?: null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
