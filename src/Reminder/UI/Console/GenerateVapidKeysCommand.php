<?php

declare(strict_types=1);

namespace App\Reminder\UI\Console;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fabrique la paire de clés qui identifie ce serveur auprès des services de
 * notification.
 *
 * Une paire par déploiement, et une seule : la changer invalide tous les
 * abonnements existants, qui ont été signés avec l'ancienne clé publique.
 */
#[AsCommand(name: 'app:vapid', description: 'Génère une paire de clés VAPID pour les notifications poussées.')]
final class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = VAPID::createVapidKeys();

        $io->writeln('# À reporter dans .env.local (ou dans le coffre à secrets en production).');
        $io->writeln('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $io->newLine();
        $io->warning('Une paire par déploiement : en changer invalide tous les abonnements déjà pris.');

        return Command::SUCCESS;
    }
}
