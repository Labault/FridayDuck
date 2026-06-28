<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Cycle\CycleStep;
use App\Infrastructure\Messaging\RunCycleStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * `app:cycle:dispatch <step>` — DÉCLENCHE manuellement une étape de cycle sur le
 * BUS (§25.1, §25.4).
 *
 * Dispatché sans `ReceivedStamp`, le message suit le routing Messenger → transport
 * `async`, donc HÉRITE de la retry_strategy et du failure_transport (§25.4), comme
 * le déclenchement par le Scheduler (qui redispatche aussi sur `async`). Outil
 * opérationnel/diagnostic : rejouer une étape sans attendre son cron, et observer
 * que les garanties d'échec s'appliquent. Le métier reste idempotent (dédup par
 * clé) : un re-déclenchement est sans effet de bord.
 */
#[AsCommand(name: 'app:cycle:dispatch', description: 'Dispatche une étape de cycle sur le bus (transport async, garanties §25.4).')]
final class CycleDispatchCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'step',
            InputArgument::REQUIRED,
            'Étape de cycle : '.implode(', ', array_map(static fn (CycleStep $cycleStep): string => $cycleStep->name, CycleStep::cases())),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $requestedArgument = $input->getArgument('step');
        $requested = \is_string($requestedArgument) ? $requestedArgument : '';

        $step = $this->resolveStep($requested);
        if (!$step instanceof CycleStep) {
            $symfonyStyle->error(\sprintf('Étape inconnue « %s ». Valeurs : %s.', $requested, implode(', ', array_map(static fn (CycleStep $cycleStep): string => $cycleStep->name, CycleStep::cases()))));

            return Command::INVALID;
        }

        $this->messageBus->dispatch(new RunCycleStep($step));
        $symfonyStyle->success(\sprintf('Étape %s dispatchée sur le transport async (retry + file d’échec §25.4).', $step->name));

        return Command::SUCCESS;
    }

    private function resolveStep(string $requested): ?CycleStep
    {
        foreach (CycleStep::cases() as $cycleStep) {
            if (0 === strcasecmp($cycleStep->name, $requested)) {
                return $cycleStep;
            }
        }

        return null;
    }
}
