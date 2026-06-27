<?php
declare(strict_types=1);

// Managed by bootstrap. PHP-CS-Fixer with the @Symfony ruleset (§11.7).
//
// PHP-CS-Fixer owns code style here, including `declare(strict_types=1);`
// placement. We enforce strict types right after `<?php` (no blank line in
// between) so it agrees with Rector's declare handling — otherwise the cs and
// rector dry-run gates fight over that one line.

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor', 'node_modules', 'public/bundles'])
    // Symfony auto-generates config/reference.php on cache:clear (gitignored,
    // not ours to format). Local addition on top of the bootstrap baseline.
    ->notPath('config/reference.php')
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'blank_line_after_opening_tag' => false,
    ])
    ->setFinder($finder);
