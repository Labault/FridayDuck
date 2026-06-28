<?php

declare(strict_types=1);

namespace App\Application\Advice;

/**
 * Bloc « advice » du GET (§24.1) : le conseil du jour et les compteurs GLOBAUX
 * de réactions, avec la séquence pour la barrière front.
 */
final readonly class AdviceView
{
    public function __construct(
        public string $text,
        public string $slug,
        public int $adviceSequence,
        public int $concerning,
        public int $alreadyDone,
        public int $takingNotes,
    ) {
    }
}
