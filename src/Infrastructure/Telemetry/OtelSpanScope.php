<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\SpanScope;
use OpenTelemetry\API\Trace\SpanInterface;

final readonly class OtelSpanScope implements SpanScope
{
    public function __construct(private SpanInterface $span)
    {
    }

    public function setAttribute(string $key, bool|int|float|string $value): void
    {
        $this->span->setAttribute($key, $value);
    }
}
