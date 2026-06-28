<?php

declare(strict_types=1);

namespace App\Application\Visitor;

use App\Application\Telemetry\Tracer;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Domain\Visitor\AnonymousVisitor;
use App\Domain\Visitor\AnonymousVisitorRepository;

/**
 * Résout l'identité anonyme depuis le HASH du cookie (§23.2, §27) : la crée si
 * absente, sinon marque la visite (incrément atomique). Race-safe via
 * UNIQUE(anonymous_identifier_hash).
 */
final readonly class ResolveAnonymousVisitor
{
    public function __construct(
        private AnonymousVisitorRepository $anonymousVisitorRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
        // Optionnel : autowiré en prod (span visitor.resolve), null en test.
        private ?Tracer $tracer = null,
    ) {
    }

    public function resolve(string $anonymousIdentifierHash): VisitorResolution
    {
        $work = function () use ($anonymousIdentifierHash): VisitorResolution {
            $existing = $this->anonymousVisitorRepository->findByHash($anonymousIdentifierHash);
            if ($existing instanceof AnonymousVisitor) {
                $this->anonymousVisitorRepository->touch($existing, $this->clock->now());

                return new VisitorResolution($existing, false);
            }

            $anonymousVisitor = AnonymousVisitor::register(
                $this->identifierGenerator->nextIdentifier(),
                $anonymousIdentifierHash,
                $this->clock->now(),
            );

            try {
                $this->anonymousVisitorRepository->add($anonymousVisitor);

                return new VisitorResolution($anonymousVisitor, true);
            } catch (ConcurrentCreationException $exception) {
                $winner = $this->anonymousVisitorRepository->findByHash($anonymousIdentifierHash);
                if (!$winner instanceof AnonymousVisitor) {
                    throw new \RuntimeException('Visiteur introuvable après une création concurrente.', $exception->getCode(), previous: $exception);
                }
                $this->anonymousVisitorRepository->touch($winner, $this->clock->now());

                return new VisitorResolution($winner, false);
            }
        };

        // Attributs vides : JAMAIS le hash ni l'identité dans un span (§27, risque C).
        if (!$this->tracer instanceof Tracer) {
            return $work();
        }

        return $this->tracer->trace('visitor.resolve', [], static fn (): VisitorResolution => $work());
    }
}
