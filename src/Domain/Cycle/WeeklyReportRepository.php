<?php

declare(strict_types=1);

namespace App\Domain\Cycle;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

interface WeeklyReportRepository
{
    public function findByIsoWeek(string $isoWeek): ?WeeklyReport;

    /**
     * @throws ConcurrentCreationException si UNIQUE(iso_week) déjà pris (course)
     */
    public function add(WeeklyReport $weeklyReport): void;
}
