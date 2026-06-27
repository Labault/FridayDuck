<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Domain\Visitor\AnonymousVisitor;
use App\Domain\Visitor\AnonymousVisitorRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineAnonymousVisitorRepository extends DoctrineRepository implements AnonymousVisitorRepository
{
    public function findByHash(string $anonymousIdentifierHash): ?AnonymousVisitor
    {
        $result = $this->em()
            ->createQuery(
                'SELECT v FROM '.AnonymousVisitor::class.' v'
                .' WHERE v.anonymousIdentifierHash = :hash',
            )
            ->setParameter('hash', $anonymousIdentifierHash)
            ->getOneOrNullResult();

        return $result instanceof AnonymousVisitor ? $result : null;
    }

    public function add(AnonymousVisitor $anonymousVisitor): void
    {
        $entityManager = $this->em();

        try {
            $entityManager->persist($anonymousVisitor);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->reset();

            throw new ConcurrentCreationException('Un visiteur existe déjà pour cet identifiant.', $exception->getCode(), previous: $exception);
        }
    }

    public function touch(AnonymousVisitor $anonymousVisitor, \DateTimeImmutable $now): void
    {
        // Incrément atomique : insensible aux visites concurrentes.
        $this->em()
            ->createQuery(
                'UPDATE '.AnonymousVisitor::class.' v'
                .' SET v.totalVisits = v.totalVisits + 1, v.lastSeenAt = :now'
                .' WHERE v.id = :id',
            )
            ->setParameter('now', $now)
            ->setParameter('id', $anonymousVisitor->id())
            ->execute();
    }
}
