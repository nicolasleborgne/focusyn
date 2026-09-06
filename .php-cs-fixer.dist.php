<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/public'])
    ->exclude(['var', 'vendor'])
    ->notPath([
        'bundles.php',
        'reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP84Migration' => true,
        // PHP 8.4 permet `new X()->m()`, mais le parseur embarqué dans deptrac
        // ne le comprend pas : il écarte le fichier sans rien dire, et la règle
        // d'architecture cesse de s'y appliquer. Une paire de parenthèses coûte
        // moins cher qu'un garde-fou qui ne garde plus rien.
        'new_expression_parentheses' => ['use_parentheses' => true],
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'ordered_class_elements' => true,
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder)
;
