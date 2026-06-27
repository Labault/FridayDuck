<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Persistence\Transactional;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implémentation Doctrine du port transactionnel : `wrapInTransaction` ouvre une
 * transaction, exécute le travail, commit au succès et rollback sur exception.
 */
final readonly class DoctrineTransactional implements Transactional
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function transactional(callable $work): mixed
    {
        $objectManager = $this->managerRegistry->getManager();
        if (!$objectManager instanceof EntityManagerInterface) {
            throw new \LogicException('Un EntityManager ORM est requis.');
        }

        return $objectManager->wrapInTransaction(static fn (): mixed => $work());
    }
}
