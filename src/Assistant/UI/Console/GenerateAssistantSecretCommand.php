<?php

declare(strict_types=1);

namespace App\Assistant\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tire le secret qui scelle les clés d'API.
 *
 * Un par déploiement, et il ne se change pas : toutes les clés déjà scellées
 * deviendraient illisibles, et il faudrait les redemander une par une.
 */
#[AsCommand(name: 'app:assistant-secret', description: 'Tire le secret de scellement des clés d\'API.')]
final class GenerateAssistantSecretCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('# À reporter dans .env.local (ou dans le coffre à secrets en production).');
        $io->writeln('ASSISTANT_SECRET='.base64_encode(random_bytes(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $io->newLine();
        $io->warning('Ce secret ne se change pas : toutes les clés déjà enregistrées deviendraient illisibles.');

        return Command::SUCCESS;
    }
}
