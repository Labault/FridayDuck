<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\Telemetry\SpanScope;

final class RecordingSpanScope implements SpanScope
{
    /** @var array<string, bool|int|float|string> */
    public array $attributes = [];

    public function setAttribute(string $key, bool|int|float|string $value): void
    {
        $this->attributes[$key] = $value;
    }
}
