<?php

declare(strict_types=1);

namespace App\Domain\Visitor;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

interface AnonymousVisitorRepository
{
    public function findByHash(string $anonymousIdentifierHash): ?AnonymousVisitor;

    /**
     * @throws ConcurrentCreationException si le hash est déjà pris (course)
     */
    public function add(AnonymousVisitor $anonymousVisitor): void;

    /**
     * Marque une visite : incrément ATOMIQUE de total_visits + last_seen_at,
     * en une seule instruction SQL (pas de lecture-modification-écriture).
     */
    public function touch(AnonymousVisitor $anonymousVisitor, \DateTimeImmutable $now): void;
}
