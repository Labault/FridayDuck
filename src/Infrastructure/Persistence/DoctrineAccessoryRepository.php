<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryRepository;

final class DoctrineAccessoryRepository extends DoctrineRepository implements AccessoryRepository
{
    public function findActive(): array
    {
        $result = $this->em()
            ->createQuery('SELECT a FROM '.Accessory::class.' a WHERE a.active = true ORDER BY a.code ASC')
            ->getResult();

        return $this->onlyAccessories($result);
    }

    public function findByCode(string $code): ?Accessory
    {
        $result = $this->em()
            ->createQuery('SELECT a FROM '.Accessory::class.' a WHERE a.code = :code')
            ->setParameter('code', $code)
            ->getOneOrNullResult();

        return $result instanceof Accessory ? $result : null;
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $result = $this->em()
            ->createQuery('SELECT a FROM '.Accessory::class.' a WHERE a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getResult();

        return $this->onlyAccessories($result);
    }

    /**
     * @return list<Accessory>
     */
    private function onlyAccessories(mixed $result): array
    {
        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn (mixed $row): bool => $row instanceof Accessory));
    }
}
