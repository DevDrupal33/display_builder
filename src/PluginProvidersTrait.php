<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Add methods to make plugin forms available.
 */
trait PluginProvidersTrait {

  /**
   * Component provider to exclude by default.
   *
   * @var array
   *   The providers to exclude.
   */
  private const PROVIDER_EXCLUDE = ['display_builder', 'sdc_devel'];

  /**
   * Get providers for ConfigurableInterface::defaultConfiguration().
   *
   * @param array $definitions
   *   Plugin definitions.
   *
   * @return array
   *   An associative array of providers ID and definitions.
   */
  protected function getDefaultProviders(array $definitions): array {
    $providers = [];
    $default_theme = \Drupal::config('system.theme')->get('default');
    $active_themes = $this->buildRecursiveListOfParentThemes($default_theme, [$default_theme]);

    foreach ($this->getProviders($definitions) as $provider_id => $provider) {
      // If the provider is not the active theme or its parents, skip it.
      if ($provider['type'] === 'theme' && !\in_array($provider_id, $active_themes, TRUE)) {
        continue;
      }

      // If the provider is part of the excluded list, skip it.
      if (\in_array($provider_id, self::PROVIDER_EXCLUDE, TRUE)) {
        continue;
      }
      $providers[] = $provider_id;
    }

    return $providers;
  }

  /**
   * Build recursive list of parent themes.
   *
   * @param string $current
   *   The extension ID of the current (in the recursive loop context) theme.
   * @param array $themes
   *   The list from the previous recursive step.
   *
   * @return array
   *   The updated list of extensions ID.
   */
  protected function buildRecursiveListOfParentThemes(string $current, array $themes): array {
    $info = $this->themes->get($current)->info;

    if (!isset($info['base theme'])) {
      return $themes;
    }
    $parent = $info['base theme'];

    return $this->buildRecursiveListOfParentThemes($parent, \array_merge($themes, [$parent]));
  }

  /**
   * Get providers options for select input.
   *
   * @param array $definitions
   *   Plugin definitions.
   * @param string|TranslatableMarkup $singular
   *   Singular label of the plugins.
   * @param string|TranslatableMarkup $plural
   *   Plural label of the plugins.
   *
   * @return array
   *   An associative array with extension ID as key and extension description
   *   as value.
   */
  protected function getProvidersOptions(array $definitions, string|TranslatableMarkup $singular = 'definition', string|TranslatableMarkup $plural = 'definitions'): array {
    $options = [];

    foreach ($this->getProviders($definitions) as $provider_id => $provider) {
      $params = [
        '@name' => $provider['name'],
        '@type' => $provider['type'],
        '@count' => $provider['count'],
        '@singular' => $singular,
        '@plural' => $plural,
      ];
      $options[$provider_id] = $this->formatPlural($provider['count'], '@name (@type, @count @singular)', '@name (@type, @count @plural)', $params);
    }

    return $options;
  }

  /**
   * Get all providers.
   *
   * @param array $definitions
   *   Plugin definitions.
   *
   * @return array
   *   Drupal extension definitions, keyed by extension ID
   */
  protected function getProviders(array $definitions): array {
    $themes = $this->themes->getAllInstalledInfo();
    $modules = $this->modules->getAllInstalledInfo();
    $providers = [];

    foreach ($definitions as $definition) {
      $provider_id = $definition['provider'];
      $provider = $themes[$provider_id] ?? $modules[$provider_id] ?? NULL;

      if (!$provider) {
        continue;
      }
      $provider['count'] = isset($providers[$provider_id]) ? ($providers[$provider_id]['count']) + 1 : 1;
      $providers[$provider_id] = $provider;
    }

    return $providers;
  }

}
