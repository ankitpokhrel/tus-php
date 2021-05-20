<?php

$finder = PhpCsFixer\Finder::create()->in(['src', 'tests']);

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        'not_operator_with_space' => true,
        'single_quote' => true,
        'binary_operator_spaces' => ['align_equals' => true],
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
