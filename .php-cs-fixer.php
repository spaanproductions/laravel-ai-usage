<?php

use PhpCsFixer\Finder;
use PhpCsFixer\Config;

$finder = (new Finder)
	->in(__DIR__ . '/src')
	->in(__DIR__ . '/tests')
	->in(__DIR__ . '/database');

return (new Config)
	->setRules([
		'@PSR12' => true,
		'@PHP83Migration' => true,
		'array_syntax' => ['syntax' => 'short'],
		'new_with_parentheses' => [
			'anonymous_class' => false,
			'named_class' => false,
		],
		'trailing_comma_in_multiline' => true,
		'array_indentation' => true,
		'binary_operator_spaces' => ['default' => 'single_space'],
		'phpdoc_line_span' => [
			'const' => 'single',
			'method' => 'single',
			'property' => 'single',
		],
		'no_multiline_whitespace_around_double_arrow' => true,
		'no_trailing_comma_in_singleline' => true,
		'no_whitespace_before_comma_in_array' => true,
		'trim_array_spaces' => true,
		'linebreak_after_opening_tag' => true,
		'blank_line_after_opening_tag' => true,
		'blank_line_before_statement' => true,
		'combine_consecutive_issets' => true,
		'combine_consecutive_unsets' => true,
		'no_short_bool_cast' => true,
		'echo_tag_syntax' => ['format' => 'long'],
		'no_unused_imports' => true,
		'not_operator_with_space' => true,
		'object_operator_without_whitespace' => true,
		'ordered_imports' => [
			'sort_algorithm' => 'length',
		],
		'phpdoc_single_line_var_spacing' => true,
		'no_whitespace_in_blank_line' => true,
		'php_unit_method_casing' => [
			'case' => 'snake_case',
		],
	])
	->setIndent("\t")
	->setFinder($finder);
