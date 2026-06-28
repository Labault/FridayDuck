<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Cycle\WeeklyReport;
use App\Domain\Cycle\WeeklyReportRepository;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineWeeklyReportRepository extends DoctrineRepository implements WeeklyReportRepository
{
    public function findByIsoWeek(string $isoWeek): ?WeeklyReport
    {
        $result = $this->em()
            ->createQuery('SELECT r FROM '.WeeklyReport::class.' r WHERE r.isoWeek = :week')
            ->setParameter('week', $isoWeek)
            ->getOneOrNullResult();

        return $result instanceof WeeklyReport ? $result : null;
    }

    public function add(WeeklyReport $weeklyReport): void
    {
        $entityManager = $this->em();

        try {
            $entityManager->persist($weeklyReport);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->reset();

            throw new ConcurrentCreationException('Un bilan existe déjà pour cette semaine.', $exception->getCode(), previous: $exception);
        }
    }
}
