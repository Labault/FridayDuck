<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Accessory\AccessoryVote;
use App\Domain\Accessory\AccessoryVoteRepository;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineAccessoryVoteRepository extends DoctrineRepository implements AccessoryVoteRepository
{
    public function findByEditionAndVisitor(string $fridayEditionId, string $visitorId): ?AccessoryVote
    {
        $result = $this->em()
            ->createQuery(
                'SELECT v FROM '.AccessoryVote::class.' v'
                .' WHERE v.fridayEditionId = :edition AND v.visitorId = :visitor',
            )
            ->setParameter('edition', $fridayEditionId)
            ->setParameter('visitor', $visitorId)
            ->getOneOrNullResult();

        return $result instanceof AccessoryVote ? $result : null;
    }

    public function add(AccessoryVote $accessoryVote): void
    {
        $entityManager = $this->em();

        try {
            $entityManager->persist($accessoryVote);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // Double-soumission concurrente : UNIQUE(edition, visitor) → ALREADY_VOTED.
            $this->reset();

            throw new ConcurrentCreationException('Un vote existe déjà pour ce visiteur et cette édition.', $exception->getCode(), previous: $exception);
        }
    }
}
