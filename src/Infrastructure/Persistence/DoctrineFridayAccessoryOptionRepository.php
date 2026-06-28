<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Accessory\FridayAccessoryOption;
use App\Domain\Accessory\FridayAccessoryOptionRepository;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineFridayAccessoryOptionRepository extends DoctrineRepository implements FridayAccessoryOptionRepository
{
    public function findByEdition(string $fridayEditionId): array
    {
        $result = $this->em()
            ->createQuery(
                'SELECT o FROM '.FridayAccessoryOption::class.' o'
                .' WHERE o.fridayEditionId = :edition ORDER BY o.displayOrder ASC',
            )
            ->setParameter('edition', $fridayEditionId)
            ->getResult();

        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof FridayAccessoryOption));
    }

    public function addAll(array $options): void
    {
        $entityManager = $this->em();

        try {
            foreach ($options as $option) {
                $entityManager->persist($option);
            }
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // Course : une requête concurrente a déjà posé les 3 options (mêmes,
            // car déterministes). On réinitialise pour relire l'ensemble gagnant.
            $this->reset();

            throw new ConcurrentCreationException('Options de vote déjà créées pour cette édition.', $exception->getCode(), previous: $exception);
        }
    }

    public function save(FridayAccessoryOption $fridayAccessoryOption): void
    {
        $entityManager = $this->em();
        $entityManager->persist($fridayAccessoryOption);
        $entityManager->flush();
    }
}
