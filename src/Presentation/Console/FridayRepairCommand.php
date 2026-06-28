<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Cycle\CycleStep;
use App\Application\Cycle\FridayCycle;
use App\Domain\Shared\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `app:friday:repair <date>` — rattrapage (§25.2, invariant D).
 *
 * Amène l'édition d'un vendredi à l'état CORRECT selon l'horloge, en invoquant le
 * MÊME aiguilleur que le Scheduler ({@see FridayCycle}) : prépare l'édition, et
 * selon le moment courant, clôt le vote / ferme l'édition / publie les annonces
 * manquantes — le tout idempotent (les annonces ne sortent qu'une fois). Le filet
 * si le Scheduler a été indisponible.
 */
#[AsCommand(name: 'app:friday:repair', description: 'Répare une édition de vendredi à l’état correct selon l’horloge.')]
final class FridayRepairCommand extends Command
{
    public function __construct(
        private readonly FridayCycle $fridayCycle,
        private readonly ClockInterface $clock,
        private readonly string $businessTimezone,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('date', InputArgument::REQUIRED, 'Date du vendredi à réparer (Y-m-d).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $dateTimeZone = new \DateTimeZone($this->businessTimezone);

        $dateArgument = $input->getArgument('date');
        $friday = \is_string($dateArgument)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dateArgument, $dateTimeZone)
            : false;
        if (false === $friday) {
            $symfonyStyle->error('Date invalide (format attendu : Y-m-d).');

            return Command::INVALID;
        }
        if (5 !== (int) $friday->format('N')) {
            $symfonyStyle->error(\sprintf('%s n’est pas un vendredi.', $friday->format('Y-m-d')));

            return Command::INVALID;
        }

        $tz = $dateTimeZone->getName();
        $now = $this->clock->now();
        $closesAt = new \DateTimeImmutable($friday->format('Y-m-d').' 14:00:00', $dateTimeZone);
        $saturdayMidnight = $friday->modify('+1 day');

        // Toujours : préparer (édition + options + conseil), idempotent.
        $this->fridayCycle->run(CycleStep::PrepareEdition, $friday, $tz);

        // Le vendredi a commencé → annonce d'ouverture (une fois).
        if ($now >= $friday) {
            $this->fridayCycle->run(CycleStep::PublishFridayOpened, $friday, $tz);
        }

        // Après 14:00 → vote clos + gagnant annoncé (une fois).
        if ($now >= $closesAt) {
            $this->fridayCycle->run(CycleStep::CloseVote, $friday, $tz);
            $this->fridayCycle->run(CycleStep::PublishWinner, $friday, $tz);
        }

        // Après samedi minuit → édition fermée + bilan (une fois).
        if ($now >= $saturdayMidnight) {
            $this->fridayCycle->run(CycleStep::PrepareReport, $friday, $tz);
            $this->fridayCycle->run(CycleStep::CloseFriday, $friday, $tz);
            $this->fridayCycle->run(CycleStep::GenerateReport, $friday, $tz);
        }

        $symfonyStyle->success(\sprintf('Édition du %s amenée à l’état correct (annonces manquantes émises une fois).', $friday->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
