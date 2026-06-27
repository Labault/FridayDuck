<?php

declare(strict_types=1);

namespace App\Application\Visitor;

use App\Domain\Visitor\AnonymousVisitor;

/**
 * Résultat de la résolution d'identité : le visiteur et s'il vient d'être créé.
 */
final readonly class VisitorResolution
{
    public function __construct(
        public AnonymousVisitor $visitor,
        public bool $isNew,
    ) {
    }
}
