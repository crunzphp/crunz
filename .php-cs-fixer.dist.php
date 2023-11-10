<?php

declare(strict_types=1);

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new PhpCsFixer\Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'protected_to_private' => false,
        'combine_consecutive_unsets' => true,
        'combine_consecutive_issets' => true,
        'compact_nullable_type_declaration' => true,
        'declare_strict_types' => true,
        'dir_constant' => true,
        'ereg_to_preg' => true,
        'explicit_indirect_variable' => true,
        'explicit_string_variable' => true,
        'function_to_constant' => true,
        'is_null' => true,
        'modernize_types_casting' => true,
        'linebreak_after_opening_tag' => true,
        'list_syntax' => ['syntax' => 'short'],
        'mb_str_functions' => true,
        'native_function_invocation' => [
            'include' => ['@all'],
        ],
        'no_alias_functions' => true,
        'no_homoglyph_names' => true,
        'no_php4_constructor' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'php_unit_construct' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_expectation' => true,
        'php_unit_mock' => true,
        'php_unit_namespaced' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'random_api_migration' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'ternary_to_null_coalescing' => true,
        'void_return' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'single_line_throw' => false,
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'self',
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
            ->in(__DIR__ . '/config')
            ->append(
                [
                    __FILE__,
                    __DIR__ . '/composer-install.php',
                ]
            )
    )
;
