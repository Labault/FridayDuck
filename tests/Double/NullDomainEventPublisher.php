<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\RealTime\DomainEvent;
use App\Application\RealTime\DomainEventPublisher;

/**
 * Implémentation neutre du port en environnement de test : aucune publication
 * réseau (le hub Mercure n'est pas joignable depuis la suite). Les tests qui
 * vérifient la publication injectent un {@see SpyDomainEventPublisher}.
 */
final class NullDomainEventPublisher implements DomainEventPublisher
{
    public function publish(\DateTimeImmutable $fridayDate, DomainEvent $event): void
    {
    }
}
