<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * §12.1 / §24.5 `FRIDAY_OPENED` — annonce de cycle émise UNE FOIS à l'ouverture
 * du vendredi, par le Scheduler ou le rattrapage (invariant E). JAMAIS par la
 * résolution paresseuse (réparation silencieuse).
 */
final readonly class FridayOpened implements DomainEvent
{
    public function __construct(public string $friday)
    {
    }

    public function type(): string
    {
        return 'FRIDAY_OPENED';
    }

    public function payload(): array
    {
        return ['friday' => $this->friday];
    }
}
