<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            // Third-party plugins are checked out into the tree and listed in
            // .gitignore; Symfony's Finder does not skip them without this.
            ->ignoreVCSIgnored(true)
            ->exclude(['tests/node_modules'])
    )
;
