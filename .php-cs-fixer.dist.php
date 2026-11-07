<?php

// PHP code style: PER Coding Style 3.0 (https://www.php-fig.org/per/coding-style/).
// Check: php-cs-fixer fix --dry-run --diff
// Apply: php-cs-fixer fix
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->name('*.php')
    ->name('*.class.inc')
    // Third-party libraries keep their upstream form.
    ->notPath('plugins/xmpp/XMPPHP')
    ->notPath('php/utility/minifier.php')
    ->exclude(['share', 'node_modules']);

return (new PhpCsFixer\Config())
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setRules([
        '@PER-CS3x0' => true,
        'ordered_class_elements' => true,
        // One space around binary operators, without column alignment.
        'binary_operator_spaces' => ['default' => 'single_space'],
        // PHP 7.4 is supported: a trailing comma after the last parameter
        // or match arm needs PHP 8.0.
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => ['arguments', 'array_destructuring', 'arrays'],
        ],
    ])
    ->setFinder($finder);
