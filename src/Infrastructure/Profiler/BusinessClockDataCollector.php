<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiler;

use App\Domain\Friday\FridayCalendar;
use App\Domain\Shared\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rend l'horloge métier VISIBLE dans la barre de debug (§7.4).
 *
 * Affiche l'instant courant (fuseau métier), l'état vendredi/dormant et, surtout,
 * si l'horloge est SIMULÉE via `APP_FAKE_NOW`. Profiler = dev/test uniquement.
 */
final class BusinessClockDataCollector extends AbstractDataCollector
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly FridayCalendar $fridayCalendar,
        #[Autowire('%env(string:default::APP_FAKE_NOW)%')]
        private readonly string $fakeNow,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $fridayState = $this->fridayCalendar->currentState();
        $now = $this->clock->now()->setTimezone($fridayState->timezone);

        $this->data = [
            'now' => $now->format(\DateTimeInterface::ATOM),
            'simulated' => '' !== $this->fakeNow && 'prod' !== $this->environment,
            'fake_now' => $this->fakeNow,
            'environment' => $this->environment,
            'active' => $fridayState->active,
            'status' => $fridayState->status->value,
            'friday_date' => $fridayState->date(),
            'timezone' => $fridayState->timezoneName(),
        ];
    }

    public static function getTemplate(): string
    {
        return 'data_collector/business_clock.html.twig';
    }

    public function isSimulated(): bool
    {
        return $this->boolValue('simulated');
    }

    public function getNow(): string
    {
        return $this->stringValue('now');
    }

    public function getStatus(): string
    {
        return $this->stringValue('status');
    }

    public function getFridayDate(): string
    {
        return $this->stringValue('friday_date');
    }

    public function getTimezone(): string
    {
        return $this->stringValue('timezone');
    }

    public function getFakeNow(): string
    {
        return $this->stringValue('fake_now');
    }

    public function getEnvironment(): string
    {
        return $this->stringValue('environment');
    }

    public function isActive(): bool
    {
        return $this->boolValue('active');
    }

    private function stringValue(string $key): string
    {
        $value = $this->payload()[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    private function boolValue(string $key): bool
    {
        return (bool) ($this->payload()[$key] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return \is_array($this->data) ? $this->data : [];
    }
}
