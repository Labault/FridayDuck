<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Advice\AdviceReaction;
use App\Domain\Advice\AdviceReactionRepository;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineAdviceReactionRepository extends DoctrineRepository implements AdviceReactionRepository
{
    public function findByEditionAndVisitor(string $fridayEditionId, string $visitorId): ?AdviceReaction
    {
        $result = $this->em()
            ->createQuery(
                'SELECT r FROM '.AdviceReaction::class.' r'
                .' WHERE r.fridayEditionId = :edition AND r.visitorId = :visitor',
            )
            ->setParameter('edition', $fridayEditionId)
            ->setParameter('visitor', $visitorId)
            ->getOneOrNullResult();

        return $result instanceof AdviceReaction ? $result : null;
    }

    public function add(AdviceReaction $adviceReaction): void
    {
        $entityManager = $this->em();

        try {
            $entityManager->persist($adviceReaction);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->reset();

            throw new ConcurrentCreationException('Une réaction existe déjà pour ce visiteur et cette édition.', $exception->getCode(), previous: $exception);
        }
    }

    public function save(AdviceReaction $adviceReaction): void
    {
        $entityManager = $this->em();
        $entityManager->persist($adviceReaction);
        $entityManager->flush();
    }
}
