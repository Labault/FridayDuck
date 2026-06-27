<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Socle commun des repositories Doctrine.
 *
 * Le gestionnaire est récupéré FRAÎCHEMENT à chaque appel via le registre :
 * après une violation d'unicité, l'EntityManager est fermé par Doctrine, et un
 * {@see reset()} en fournit un neuf — indispensable pour relire la ligne
 * gagnante (course, §25.2). Sous worker FrankenPHP, aucun état n'est conservé
 * entre requêtes (§22.2).
 */
abstract class DoctrineRepository
{
    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    protected function em(): EntityManagerInterface
    {
        $objectManager = $this->managerRegistry->getManager();
        if (!$objectManager instanceof EntityManagerInterface) {
            throw new \LogicException('Un EntityManager ORM est requis.');
        }

        return $objectManager;
    }

    /**
     * Réinitialise le gestionnaire fermé après une exception de flush.
     */
    protected function reset(): void
    {
        $this->managerRegistry->resetManager();
    }
}
