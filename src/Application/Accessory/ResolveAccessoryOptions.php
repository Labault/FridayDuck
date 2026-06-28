<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryRepository;
use App\Domain\Accessory\DateSeededOrdering;
use App\Domain\Accessory\FridayAccessoryOption;
use App\Domain\Accessory\FridayAccessoryOptionRepository;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\ConcurrentCreationException;

/**
 * Résout (ou crée à la volée, §25.2) les TROIS options d'une édition — même
 * pattern résoudre-ou-créer race-safe que l'édition (2a-i).
 *
 * La sélection est DÉTERMINISTE, seedée par la date du vendredi (§10.4) : deux
 * créateurs concurrents choisissent les MÊMES trois accessoires ; l'unicité
 * (friday_edition_id, accessory_id) départage la course et l'on relit l'ensemble
 * gagnant.
 */
final readonly class ResolveAccessoryOptions
{
    private const int OPTION_COUNT = 3;

    public function __construct(
        private AccessoryRepository $accessoryRepository,
        private FridayAccessoryOptionRepository $fridayAccessoryOptionRepository,
        private IdentifierGenerator $identifierGenerator,
    ) {
    }

    /**
     * @return list<FridayAccessoryOption>
     */
    public function resolve(string $fridayEditionId, \DateTimeImmutable $fridayDate): array
    {
        $existing = $this->fridayAccessoryOptionRepository->findByEdition($fridayEditionId);
        if (\count($existing) >= self::OPTION_COUNT) {
            return $existing;
        }

        $options = [];
        foreach ($this->select($fridayDate) as $index => $accessory) {
            $options[] = FridayAccessoryOption::create(
                $this->identifierGenerator->nextIdentifier(),
                $fridayEditionId,
                $accessory->id(),
                $index + 1,
            );
        }

        try {
            $this->fridayAccessoryOptionRepository->addAll($options);

            return $options;
        } catch (ConcurrentCreationException) {
            return $this->fridayAccessoryOptionRepository->findByEdition($fridayEditionId);
        }
    }

    /**
     * Les trois accessoires du jour : ordre déterministe seedé par la date, puis
     * les trois premiers (§10.4).
     *
     * @return list<Accessory>
     */
    private function select(\DateTimeImmutable $fridayDate): array
    {
        $ordered = DateSeededOrdering::order(
            $fridayDate->format('Y-m-d'),
            $this->accessoryRepository->findActive(),
            static fn (Accessory $accessory): string => $accessory->code(),
        );

        return \array_slice($ordered, 0, self::OPTION_COUNT);
    }
}
