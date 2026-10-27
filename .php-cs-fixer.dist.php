<?php

use PhpCsFixer\Config;
use Symfony\Component\Finder\Finder;

$finder = Finder::create()
  ->in(__DIR__)
  ->name('*.php')
  ->ignoreDotFiles(true)
  ->ignoreVCS(true)
  ->exclude(['vendor', 'storage', 'resources']);

$config = new Config();

return $config->setRules([
  '@PSR2' => true,
  'array_indentation' => true,
  'array_syntax' => ['syntax' => 'short'],
  'binary_operator_spaces' => true,
  'blank_line_after_namespace' => true,
  'blank_line_before_statement' => ['statements' => ['return']],
  'class_definition' => true,
  'method_chaining_indentation' => true,
  'no_extra_blank_lines' => true,
  'multiline_whitespace_before_semicolons' => true,
  'echo_tag_syntax' => ['format' => 'long'],
  'no_spaces_around_offset' => ['positions' => ['inside']],
  'no_unused_imports' => true,
  'whitespace_after_comma_in_array' => true,
  'not_operator_with_successor_space' => true,
  'ordered_imports' => ['sort_algorithm' => 'alpha'],
  'trailing_comma_in_multiline' => ['elements' => ['arrays']],
  'trim_array_spaces' => true,
  'single_quote' => true,
  'strict_param' => false,
  'braces_position' => [
    'functions_opening_brace' => 'same_line',
    'classes_opening_brace' => 'same_line',
    'anonymous_functions_opening_brace' => 'same_line',
    'anonymous_classes_opening_brace' => 'same_line',
  ],
])
  ->setIndent(str_pad('', 2))
  ->setFinder($finder);
