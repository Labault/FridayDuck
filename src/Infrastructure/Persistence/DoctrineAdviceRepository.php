<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Advice\Advice;
use App\Domain\Advice\AdviceRepository;

final class DoctrineAdviceRepository extends DoctrineRepository implements AdviceRepository
{
    public function findActive(): array
    {
        $result = $this->em()
            ->createQuery('SELECT a FROM '.Advice::class.' a WHERE a.active = true ORDER BY a.slug ASC')
            ->getResult();

        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof Advice));
    }

    public function findById(string $id): ?Advice
    {
        $advice = $this->em()->find(Advice::class, $id);

        return $advice instanceof Advice ? $advice : null;
    }
}
