<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\RealTime\RealTimeTransport;

/**
 * Transport temps réel espion : enregistre chaque publication ; peut simuler un
 * hub injoignable ({@see down()}) pour exercer le rejeu/at-least-once du relais.
 */
final class SpyRealTimeTransport implements RealTimeTransport
{
    /** @var list<array{topic: string, payload: string}> */
    public array $published = [];

    public int $attempts = 0;

    private bool $down = false;

    public function down(bool $down = true): void
    {
        $this->down = $down;
    }

    public function publish(string $topic, string $payload): void
    {
        ++$this->attempts;

        if ($this->down) {
            throw new \RuntimeException('Hub Mercure injoignable (test).');
        }

        $this->published[] = ['topic' => $topic, 'payload' => $payload];
    }
}
