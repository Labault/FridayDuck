<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Garde-fou DÉFENSIF (§7.4).
 *
 * En production, `APP_FAKE_NOW` est déjà neutralisé par construction (la liaison
 * `ClockInterface` pointe sur `SystemClock`). Ce garde-fou ajoute une alerte
 * explicite si la variable est malgré tout présente en prod : signe d'une erreur
 * de déploiement. On journalise (une fois) sans interrompre le service, puisque
 * l'effet est déjà nul.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
final class FakeClockProductionGuard
{
    private bool $checked = false;

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(string:default::APP_FAKE_NOW)%')]
        private readonly string $fakeNow,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RequestEvent $requestEvent): void
    {
        if (!$requestEvent->isMainRequest() || $this->checked) {
            return;
        }
        $this->checked = true;

        if ('prod' === $this->environment && '' !== $this->fakeNow) {
            $this->logger?->warning(
                'APP_FAKE_NOW est défini en PRODUCTION : ignoré (horloge système), '
                .'mais cette variable ne devrait jamais être présente en prod (§7.4).',
                ['app_fake_now' => $this->fakeNow],
            );
        }
    }
}
