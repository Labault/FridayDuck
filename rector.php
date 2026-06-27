<?php

declare(strict_types=1);

// Managed by bootstrap. Rector with broad upgrade + quality sets (§11.7).
// CI runs this in --dry-run; apply fixes locally with `make rector-fix`.
//
// NOTE: codingStyle is intentionally OFF — PHP-CS-Fixer (@Symfony) owns code
// style here. Enabling both makes them fight (each reformats the other's output),
// so the cs and rector dry-run gates could never be green at the same time.

use Rector\Config\RectorConfig;

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
    );
