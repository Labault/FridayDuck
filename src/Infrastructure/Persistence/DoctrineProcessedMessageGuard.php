<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Cycle\ProcessedMessageGuard;
use App\Domain\Cycle\ProcessedMessage;
use App\Domain\Shared\Clock\ClockInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineProcessedMessageGuard implements ProcessedMessageGuard
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
    ) {
    }

    public function markIfFirst(string $key): bool
    {
        $objectManager = $this->managerRegistry->getManager();
        if (!$objectManager instanceof EntityManagerInterface) {
            throw new \LogicException('Un EntityManager ORM est requis.');
        }

        // Déjà traité (en mémoire ou en base) → on ne (re)persiste pas : ni annonce,
        // ni collision d'identité si la clé est déjà gérée dans le même EM.
        if ($objectManager->find(ProcessedMessage::class, $key) instanceof ProcessedMessage) {
            return false;
        }

        try {
            $objectManager->persist(ProcessedMessage::record($key, $this->clock->now()));
            $objectManager->flush();

            return true;
        } catch (UniqueConstraintViolationException) {
            // Course inter-process : un autre vient de l'insérer — EM fermé → réinitialiser.
            $this->managerRegistry->resetManager();

            return false;
        }
    }
}
