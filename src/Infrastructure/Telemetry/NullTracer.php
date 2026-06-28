<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\Tracer;

/**
 * Traceur neutre (OTel désactivé ou indisponible) : exécute le travail SANS tracer.
 * Le travail métier est toujours rendu — la télémétrie ne change jamais le résultat.
 */
final class NullTracer implements Tracer
{
    public function trace(string $name, array $attributes, callable $work): mixed
    {
        return $work(new NullSpanScope());
    }

    public function currentTraceparent(): ?string
    {
        return null;
    }

    public function traceLinkedTo(string $name, ?string $traceparent, array $attributes, callable $work): mixed
    {
        return $work(new NullSpanScope());
    }
}
