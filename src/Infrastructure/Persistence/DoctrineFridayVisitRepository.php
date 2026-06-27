<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Domain\Visitor\FridayVisit;
use App\Domain\Visitor\FridayVisitRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineFridayVisitRepository extends DoctrineRepository implements FridayVisitRepository
{
    public function find(string $fridayEditionId, string $visitorId): ?FridayVisit
    {
        $result = $this->em()
            ->createQuery(
                'SELECT fv FROM '.FridayVisit::class.' fv'
                .' WHERE fv.fridayEditionId = :edition AND fv.visitorId = :visitor',
            )
            ->setParameter('edition', $fridayEditionId)
            ->setParameter('visitor', $visitorId)
            ->getOneOrNullResult();

        return $result instanceof FridayVisit ? $result : null;
    }

    public function add(FridayVisit $fridayVisit): void
    {
        $entityManager = $this->em();

        try {
            $entityManager->persist($fridayVisit);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->reset();

            throw new ConcurrentCreationException('Une visite existe déjà pour ce visiteur sur cette édition.', $exception->getCode(), previous: $exception);
        }
    }

    public function touch(FridayVisit $fridayVisit, \DateTimeImmutable $now): void
    {
        $this->em()
            ->createQuery(
                'UPDATE '.FridayVisit::class.' fv'
                .' SET fv.lastSeenAt = :now WHERE fv.id = :id',
            )
            ->setParameter('now', $now)
            ->setParameter('id', $fridayVisit->id())
            ->execute();
    }
}
