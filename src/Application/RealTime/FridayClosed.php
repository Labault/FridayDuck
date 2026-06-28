<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * §12.5 / §24.5 `FRIDAY_CLOSED` — annonce de cycle émise UNE FOIS à la fermeture
 * (samedi minuit), par le Scheduler ou le rattrapage (invariant E).
 */
final readonly class FridayClosed implements DomainEvent
{
    public function __construct(public string $friday)
    {
    }

    public function type(): string
    {
        return 'FRIDAY_CLOSED';
    }

    public function payload(): array
    {
        return ['friday' => $this->friday];
    }
}
