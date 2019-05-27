<?php declare(strict_types=1);
/**
 * PHP-CS-Fixer config for elabftw
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules(array(
        '@PSR2' => true,
        '@PHP71Migration' => true,
        'psr4' => true,
        'array_syntax' => ['syntax' => 'long'],
        'php_unit_construct' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'dir_constant' => true,
        'pow_to_exponentiation' => true,
        'is_null' => true,
        'no_homoglyph_names' => true,
        'no_null_property_initialization' => true,
        'no_php4_constructor' => true,
        'non_printable_character' => true,
        'ordered_imports' => true,
        'ordered_class_elements' => true,
        'single_blank_line_before_namespace' => true,
        'single_class_element_per_statement' => true,
        'space_after_semicolon' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'trailing_comma_in_multiline_array' => true,
        'trim_array_spaces' => true,
        'unary_operator_spaces' => true,
    ))
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->name('/\.php|\.php.dist$/')
            ->in(['bin', 'src', 'tests', 'web'])
    )
;
