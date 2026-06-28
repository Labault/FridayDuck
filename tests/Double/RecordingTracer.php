<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\Telemetry\Tracer;

/**
 * Traceur enregistreur : capture le nom, les attributs, le parent (par pile) et le
 * lien async (traceparent) de chaque span — pour asserter la hiérarchie, les
 * attributs (PII) et la propagation, sans SDK ni réseau.
 */
final class RecordingTracer implements Tracer
{
    /** @var list<array{name: string, attributes: array<string, bool|int|float|string>, parent: ?string, linkedTo: ?string}> */
    public array $spans = [];

    /** @var list<string> */
    private array $stack = [];

    public string $traceparent = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';

    public function trace(string $name, array $attributes, callable $work): mixed
    {
        $parent = [] === $this->stack ? null : $this->stack[array_key_last($this->stack)];
        $index = \count($this->spans);
        $this->spans[] = ['name' => $name, 'attributes' => $attributes, 'parent' => $parent, 'linkedTo' => null];
        $this->stack[] = $name;

        $scope = new RecordingSpanScope();

        try {
            return $work($scope);
        } finally {
            array_pop($this->stack);
            $this->spans[$index]['attributes'] = [...$this->spans[$index]['attributes'], ...$scope->attributes];
        }
    }

    public function currentTraceparent(): ?string
    {
        return $this->traceparent;
    }

    public function traceLinkedTo(string $name, ?string $traceparent, array $attributes, callable $work): mixed
    {
        $this->spans[] = ['name' => $name, 'attributes' => $attributes, 'parent' => null, 'linkedTo' => $traceparent];

        return $work(new RecordingSpanScope());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (array $span): string => $span['name'], $this->spans);
    }

    /**
     * @return array{name: string, attributes: array<string, bool|int|float|string>, parent: ?string, linkedTo: ?string}|null
     */
    public function span(string $name): ?array
    {
        foreach ($this->spans as $span) {
            if ($span['name'] === $name) {
                return $span;
            }
        }

        return null;
    }
}
