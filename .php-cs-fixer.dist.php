<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;

return (new Config())
    ->setParallelConfig(new ParallelConfig(2))
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => [
            // Convert \Foo\Bar to use Foo\Bar; + Bar (instanceof, new, types, ::class, etc.).
            'import_symbols' => true,
        ],
        // Global classes too (`use InvalidArgumentException;` instead of `new \InvalidArgumentException`); native
        // functions/constants keep the @Symfony:risky leading-backslash form.
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
            ->name('*.php'),
    );
