<?php

$header = <<<'EOF'
This file is part of the package cpsit/import-export-core.

It is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License, either version 2 of the License, or (at
your option) any later version.

For the full license information, please read the LICENSE file that was
distributed with this source code.
EOF;

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
        'header_comment' => [
            'header' => $header,
            'comment_type' => 'comment',
            'location' => 'after_declare_strict',
            'separate' => 'both',
        ],
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