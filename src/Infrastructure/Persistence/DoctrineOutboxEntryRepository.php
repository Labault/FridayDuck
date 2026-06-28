<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Outbox\OutboxEntry;
use App\Domain\Outbox\OutboxEntryRepository;

final class DoctrineOutboxEntryRepository extends DoctrineRepository implements OutboxEntryRepository
{
    public function add(OutboxEntry $outboxEntry): void
    {
        $entityManager = $this->em();
        $entityManager->persist($outboxEntry);

        // Transaction métier ambiante (chemin requête) → la ligne est emportée par
        // son commit (atomicité, invariant A). Aucune transaction (chemin cycle) →
        // on flushe ici pour rendre l'annonce durable (sa propre transaction).
        if (0 === $entityManager->getConnection()->getTransactionNestingLevel()) {
            $entityManager->flush();
        }
    }

    public function pendingFridayDates(): array
    {
        $dates = [];
        foreach ($this->em()->getConnection()->fetchFirstColumn(
            'SELECT friday_date FROM outbox WHERE published_at IS NULL GROUP BY friday_date ORDER BY MIN(id) ASC',
        ) as $value) {
            if (\is_string($value)) {
                $dates[] = $value;
            }
        }

        return $dates;
    }

    public function lockPendingForEdition(string $fridayDate): array
    {
        // Verrou de ligne race-safe : un relais concurrent saute les lignes déjà
        // réclamées (SKIP LOCKED) → chaque ligne publiée une seule fois (invariant B).
        $ids = $this->em()->getConnection()->fetchFirstColumn(
            'SELECT id FROM outbox WHERE friday_date = :friday AND published_at IS NULL'
            .' ORDER BY id ASC FOR UPDATE SKIP LOCKED',
            ['friday' => $fridayDate],
        );

        $entries = [];
        foreach ($ids as $id) {
            // `find` accepte un id scalaire ; Doctrine le normalise vers la PK.
            $outboxEntry = $this->em()->find(OutboxEntry::class, $id);
            if ($outboxEntry instanceof OutboxEntry) {
                $entries[] = $outboxEntry;
            }
        }

        return $entries;
    }

    public function save(OutboxEntry $outboxEntry): void
    {
        $entityManager = $this->em();
        $entityManager->persist($outboxEntry);
        $entityManager->flush();
    }

    public function countPending(): int
    {
        return (int) $this->em()
            ->createQuery('SELECT COUNT(o) FROM '.OutboxEntry::class.' o WHERE o.publishedAt IS NULL')
            ->getSingleScalarResult();
    }
}
