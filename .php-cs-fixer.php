<?php

declare(strict_types=1);

use drupol\PhpCsFixerConfigsDrupal\Config\Drupal8;

$finder = PhpCsFixer\Finder::create()
  ->name('*.module')
  ->name('*.inc')
  ->name('*.install')
  ->name('*.test')
  ->name('*.profile')
  ->name('*.theme')
  ->notPath('*.md')
  ->notPath('*.info.yml')
;

// $config = new \PhpCsFixer\Config();
$config = new Drupal8();

// $config->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
$config->setFinder($finder);

$rules = [];
$rules = $config->getRules();

$local_rules = [
  'declare_strict_types' => true,
  'blank_line_after_opening_tag' => true,
  'ordered_imports' => true,
  'ordered_interfaces' => true,
  'php_unit_strict' => true,
  'php_unit_data_provider_method_order' => true,
  'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
  'ordered_class_elements' => ['case_sensitive' => false],
  '@PHP83Migration' => true,
  '@PHP84Migration' => true,
  'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced', 'strict' => true],
];

$config->setRules(array_merge($rules, $local_rules));
// $config->setRules(array_merge($local_rules, $rules));

return $config;