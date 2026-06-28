<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\RealTime\DomainEvent;
use App\Application\RealTime\DomainEventPublisher;

/**
 * Espion du port : enregistre chaque publication typée pour assertion (Phase 3/4).
 */
final class SpyDomainEventPublisher implements DomainEventPublisher
{
    /** @var list<array{date: \DateTimeImmutable, event: DomainEvent}> */
    public array $calls = [];

    public function publish(\DateTimeImmutable $fridayDate, DomainEvent $event): void
    {
        $this->calls[] = ['date' => $fridayDate, 'event' => $event];
    }

    /**
     * @return list<DomainEvent>
     */
    public function eventsOfType(string $type): array
    {
        return array_values(array_map(
            static fn (array $call): DomainEvent => $call['event'],
            array_filter($this->calls, static fn (array $call): bool => $call['event']->type() === $type),
        ));
    }
}
