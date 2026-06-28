<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\RealTime\OutboxRelay;
use App\Application\RealTime\OutboxRelayFailed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `app:outbox:relay` — relaie une fois l'outbox vers Mercure (§20.6, §25.4).
 *
 * MÊME relais que le message planifié : publie les lignes non publiées EN ORDRE,
 * race-safe, marque les publiées. Pour le runbook (vidage manuel d'un backlog) et
 * un boucle de dev (`watch`/`while`) en l'absence de worker. Sortie non nulle si
 * au moins une publication a échoué (laissée non publiée, à rejouer).
 */
#[AsCommand(name: 'app:outbox:relay', description: 'Relaie les événements non publiés de l’outbox vers Mercure.')]
final class OutboxRelayCommand extends Command
{
    public function __construct(private readonly OutboxRelay $outboxRelay)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        try {
            $this->outboxRelay->relayPending();
        } catch (OutboxRelayFailed $outboxRelayFailed) {
            $symfonyStyle->warning($outboxRelayFailed->getMessage());

            return Command::FAILURE;
        }

        $symfonyStyle->success('Outbox relayée : toutes les lignes en attente ont été publiées.');

        return Command::SUCCESS;
    }
}
