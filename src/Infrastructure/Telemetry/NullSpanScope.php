<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\SpanScope;

final class NullSpanScope implements SpanScope
{
    public function setAttribute(string $key, bool|int|float|string $value): void
    {
    }
}
