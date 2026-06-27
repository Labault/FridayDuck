<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineFridayEditionRepository extends DoctrineRepository implements FridayEditionRepository
{
    public function findByFriday(\DateTimeImmutable $fridayDate, string $timezone): ?FridayEdition
    {
        $result = $this->em()
            ->createQuery(
                'SELECT e FROM '.FridayEdition::class.' e'
                .' WHERE e.fridayDate = :date AND e.timezone = :timezone',
            )
            ->setParameter('date', $fridayDate)
            ->setParameter('timezone', $timezone)
            ->getOneOrNullResult();

        return $result instanceof FridayEdition ? $result : null;
    }

    public function add(FridayEdition $fridayEdition): void
    {
        $entityManager = $this->em();

        try {
            $entityManager->persist($fridayEdition);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // L'EntityManager est fermé : le réinitialiser pour permettre la
            // relecture de la ligne gagnante par l'appelant.
            $this->reset();

            throw new ConcurrentCreationException('Une édition existe déjà pour ce vendredi et ce fuseau.', $exception->getCode(), previous: $exception);
        }
    }
}
