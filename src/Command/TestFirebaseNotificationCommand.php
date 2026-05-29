<?php

namespace App\Command;

use App\Entity\Animator;
use App\Entity\MobileDeviceToken;
use App\Service\FirebaseCloudMessagingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:firebase:test-notification',
    description: 'Send a test Firebase notification to an animator mobile device.',
)]
class TestFirebaseNotificationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FirebaseCloudMessagingClient $firebase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Animator username');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = strtolower(trim((string) $input->getArgument('username')));

        $animator = $this->entityManager->getRepository(Animator::class)->findOneBy(['username' => $username]);
        if (!$animator instanceof Animator) {
            $io->error(sprintf('Animateur introuvable : %s', $username));

            return Command::FAILURE;
        }

        $tokens = $this->entityManager->getRepository(MobileDeviceToken::class)->findBy([
            'animator' => $animator,
            'enabled' => true,
        ]);

        if ($tokens === []) {
            $io->warning('Aucun téléphone enregistré pour cet animateur. Connecte-toi d’abord dans l’app mobile.');

            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            $this->firebase->sendToToken(
                $token->getToken(),
                'Test OZ Mobile',
                sprintf('Notification de test pour %s.', $animator->getFirstName()),
                ['type' => 'test_notification'],
            );
            ++$sent;
        }

        $io->success(sprintf('%d notification(s) envoyée(s).', $sent));

        return Command::SUCCESS;
    }
}
