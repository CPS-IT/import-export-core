<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/Classes')
    ->in(__DIR__ . '/Tests')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'cast_spaces' => ['space' => 'none'],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'function_typehint_space' => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'no_alias_functions' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_indent' => true,
        'phpdoc_no_package' => true,
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_trim' => true,
        'single_blank_line_before_namespace' => true,
        'single_quote' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;