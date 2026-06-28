<?php

declare(strict_types=1);

namespace App\Domain\Cycle;

/**
 * Bilan hebdomadaire figé (§12.5) — PHP pur, mapping externe. Agrège les chiffres
 * déjà suivis (§26.4) : énergie pic, cafés, surcaféination, visiteurs uniques,
 * accessoire gagnant, conseil, réactions. UNIQUE(iso_week) : un bilan par semaine.
 */
final readonly class WeeklyReport
{
    private function __construct(
        private string $id,
        private \DateTimeImmutable $fridayDate,
        private string $isoWeek,
        private int $peakEnergy,
        private int $coffeeCount,
        private int $overcaffeinationCount,
        private int $uniqueVisitors,
        private ?string $winnerAccessoryCode,
        private ?string $adviceSlug,
        private int $concerningCount,
        private int $alreadyDoneCount,
        private int $takingNotesCount,
        private \DateTimeImmutable $generatedAt,
    ) {
    }

    /**
     * @param array{
     *     peakEnergy: int, coffeeCount: int, overcaffeinationCount: int,
     *     uniqueVisitors: int, winnerAccessoryCode: ?string, adviceSlug: ?string,
     *     concerning: int, alreadyDone: int, takingNotes: int
     * } $figures
     */
    public static function generate(
        string $id,
        \DateTimeImmutable $fridayDate,
        string $isoWeek,
        array $figures,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $fridayDate,
            $isoWeek,
            $figures['peakEnergy'],
            $figures['coffeeCount'],
            $figures['overcaffeinationCount'],
            $figures['uniqueVisitors'],
            $figures['winnerAccessoryCode'],
            $figures['adviceSlug'],
            $figures['concerning'],
            $figures['alreadyDone'],
            $figures['takingNotes'],
            $now,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fridayDate(): \DateTimeImmutable
    {
        return $this->fridayDate;
    }

    public function isoWeek(): string
    {
        return $this->isoWeek;
    }

    public function peakEnergy(): int
    {
        return $this->peakEnergy;
    }

    public function coffeeCount(): int
    {
        return $this->coffeeCount;
    }

    public function overcaffeinationCount(): int
    {
        return $this->overcaffeinationCount;
    }

    public function uniqueVisitors(): int
    {
        return $this->uniqueVisitors;
    }

    public function winnerAccessoryCode(): ?string
    {
        return $this->winnerAccessoryCode;
    }

    public function adviceSlug(): ?string
    {
        return $this->adviceSlug;
    }

    public function concerningCount(): int
    {
        return $this->concerningCount;
    }

    public function alreadyDoneCount(): int
    {
        return $this->alreadyDoneCount;
    }

    public function takingNotesCount(): int
    {
        return $this->takingNotesCount;
    }

    public function generatedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }
}
