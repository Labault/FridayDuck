<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Coffee\CoffeeContribution;
use App\Domain\Coffee\CoffeeContributionRepository;

final class DoctrineCoffeeContributionRepository extends DoctrineRepository implements CoffeeContributionRepository
{
    public function findByIdempotencyKey(string $idempotencyKey): ?CoffeeContribution
    {
        $result = $this->em()
            ->createQuery(
                'SELECT c FROM '.CoffeeContribution::class.' c WHERE c.idempotencyKey = :key',
            )
            ->setParameter('key', $idempotencyKey)
            ->getOneOrNullResult();

        return $result instanceof CoffeeContribution ? $result : null;
    }

    public function countForVisitorAndEdition(string $fridayEditionId, string $visitorId): int
    {
        return (int) $this->em()
            ->createQuery(
                'SELECT COUNT(c.id) FROM '.CoffeeContribution::class.' c'
                .' WHERE c.fridayEditionId = :edition AND c.visitorId = :visitor',
            )
            ->setParameter('edition', $fridayEditionId)
            ->setParameter('visitor', $visitorId)
            ->getSingleScalarResult();
    }

    public function add(CoffeeContribution $coffeeContribution): void
    {
        // Pas de capture de violation ici : l'idempotence est vérifiée sous le
        // verrou d'édition en amont. La contrainte UNIQUE(idempotency_key) reste
        // le filet de sécurité ultime (§8.6) ; si elle se déclenchait, l'exception
        // remonte et fait échouer la transaction (rollback).
        $entityManager = $this->em();
        $entityManager->persist($coffeeContribution);
        $entityManager->flush();
    }
}
