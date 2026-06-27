<?php

declare(strict_types=1);

// Managed by bootstrap. Rector with broad upgrade + quality sets (§11.7).
// CI runs this in --dry-run; apply fixes locally with `make rector-fix`.
//
// NOTE: codingStyle is intentionally OFF — PHP-CS-Fixer (@Symfony) owns code
// style here. Enabling both makes them fight (each reformats the other's output),
// so the cs and rector dry-run gates could never be green at the same time.

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

// Entités du Domaine : modèles MUTABLES managés par Doctrine.
$mutableEntities = [
    __DIR__.'/src/Domain/Friday/FridayEdition.php',
    __DIR__.'/src/Domain/Visitor/AnonymousVisitor.php',
    __DIR__.'/src/Domain/Visitor/FridayVisit.php',
    __DIR__.'/src/Domain/Coffee/CoffeeContribution.php',
];

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src'])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
    )
    // FridayEdition évoluera (énergie/cafés) en Phase 2a-ii et Doctrine hydrate
    // par réflexion : on ne fige pas ces entités en `readonly` (ni classe ni
    // propriétés). Les autres règles (renommages, etc.) restent actives.
    ->withSkip([
        ReadOnlyClassRector::class => $mutableEntities,
        ReadOnlyPropertyRector::class => $mutableEntities,
    ]);
