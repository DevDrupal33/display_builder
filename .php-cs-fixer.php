<?php

declare(strict_types=1);

/**
 * @codeCoverageIgnore
 */

use drupol\PhpCsFixerConfigsDrupal\Config\Drupal8;

$finder = PhpCsFixer\Finder::create()
  ->in(__DIR__)
  // CI builds the Drupal site next to the module sources.
  ->exclude('web')
  ->exclude('vendor')
  ->exclude('node_modules')
  ->name('*.module')
  ->notPath('*.md')
  ->notPath('*.info.yml')
  ->notName('display_builder.post_update.php')
;

$config = new Drupal8();

$config->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
$config->setFinder($finder);

$rules = [];
$rules = $config->getRules();

// Deprecated rule.
unset($rules['visibility_required']);

$local_rules = [
  'declare_strict_types' => true,
  'blank_line_after_opening_tag' => true,
  'ordered_imports' => true,
  'ordered_interfaces' => true,
  'php_unit_strict' => false,
  // 'return_assignment' => false,
  'php_unit_test_class_requires_covers' => false,
  'new_expression_parentheses' => ['use_parentheses' => true],
  'php_unit_data_provider_method_order' => true,
  'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
  // 'ordered_class_elements' => ['sort_algorithm' => 'alpha', 'case_sensitive' => false],
  'ordered_class_elements' => ['case_sensitive' => false],
  '@PHP8x3Migration' => true,
  '@PHP8x4Migration' => false,
  // 'native_function_invocation' => ['include' => ['@internal'], 'scope' => 'all', 'strict' => true],
  'modifier_keywords' => ['elements' => ['const', 'method', 'property']]
];

$rules = \array_merge($rules, $local_rules);

// Altered to avoid losing the existing leading \.
$rules['native_function_invocation']['include'] = [
  '@all',
];
$rules['native_function_invocation']['scope'] = 'all';


// Altered for phpcs compatibility.
$rules['blank_line_before_statement']['statements'] = ['case', 'declare', 'default'];

// On a multi-line class declaration, Drupal/blank_line_before_end_of_class
// indents the closing brace and phpcbf unindents it again. Restores what the
// Drupal8 ruleset drops, so no such declaration survives.
$rules['class_definition']['single_line'] = TRUE;

// Altered for phpstan compatibility.
$rules['return_assignment'] = ['skip_named_var_tags' => TRUE];

$config->setRules($rules);

return $config;
