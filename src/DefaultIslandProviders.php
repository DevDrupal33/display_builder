<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Create default providers for islands.
 */
class DefaultIslandProviders {

  use StringTranslationTrait;

  /**
   * The default theme name.
   */
  private string $defaultTheme;

  /**
   * The theme handler service.
   */
  private ThemeExtensionList $themeList;

  /**
   * The module handler service.
   */
  private ModuleExtensionList $moduleList;

  /**
   * The island plugin manager.
   */
  private IslandPluginManagerInterface $islandPluginManager;

  public function __construct(ConfigFactoryInterface $configFactory, ModuleExtensionList $moduleList, ThemeExtensionList $themeList, IslandPluginManagerInterface $islandPluginManager) {
    $this->defaultTheme = $configFactory->get('system.theme')->get('default');
    $this->moduleList = $moduleList;
    $this->themeList = $themeList;
    $this->islandPluginManager = $islandPluginManager;
  }

  /**
   * Set default providers for a profile.
   *
   * @param \Drupal\display_builder\ProfileInterface $profile
   *   The display builder profile entity.
   */
  public function setDefaultIslandProviders(ProfileInterface $profile): void {
    $islands_configuration = $profile->getIslandConfigurations() ?: [];

    if (empty($islands_configuration)) {
      return;
    }

    $islands = $this->islandPluginManager->getDefinitions();

    foreach ($islands_configuration as $island_id => $configuration) {
      if (!isset($islands[$island_id])) {
        continue;
      }

      if (isset($configuration['providers']) && !empty($configuration['providers'])) {
        // Providers already set.
        continue;
      }

      $island_class = $islands[$island_id]['class'];

      if (!\in_array(IslandWithProviderInterface::class, (array) \class_implements($island_class), TRUE)) {
        continue;
      }

      $island = $this->islandPluginManager->createInstance($island_id, []);

      $configuration['providers'] = [];

      if (\method_exists($island, 'getDefaultProviders')) {
        $configuration['providers'] = $island->getDefaultProviders();
      }
      else {
        /** @var \Drupal\display_builder\IslandWithProviderInterface $island */
        $configuration['providers'] = $this->getDefaultIslandProviders($island->getProviderDefinitions(), $island_class::PROVIDER_EXCLUDE);
      }

      $profile->setIslandConfiguration($island_id, $configuration);
      $profile->save();
    }
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
  public function buildProvidersOptions(array $definitions, string|TranslatableMarkup $singular = 'definition', string|TranslatableMarkup $plural = 'definitions'): array {
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
  public function getProviders(array $definitions): array {
    $themes = $this->themeList->getAllInstalledInfo();
    $modules = $this->moduleList->getAllInstalledInfo();
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

  /**
   * Get providers for ConfigurableInterface::defaultConfiguration().
   *
   * @param array $definitions
   *   Plugin definitions.
   * @param array $provider_exclude
   *   The providers to exclude.
   *
   * @return array
   *   An associative array of providers ID and definitions.
   */
  protected function getDefaultIslandProviders(array $definitions, array $provider_exclude): array {
    $providers = [];
    $active_themes = $this->buildRecursiveListOfParentThemes($this->defaultTheme, [$this->defaultTheme]);

    foreach ($this->getProviders($definitions) as $provider_id => $provider) {
      // If the provider is not the active theme or its parents, skip it.
      if ($provider['type'] === 'theme' && !\in_array($provider_id, $active_themes, TRUE)) {
        continue;
      }

      // If the provider is part of the excluded list, skip it.
      if (\in_array($provider_id, $provider_exclude, TRUE)) {
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
  private function buildRecursiveListOfParentThemes(string $current, array $themes): array {
    $info = $this->themeList->get($current)->info;

    if (!isset($info['base theme'])) {
      return $themes;
    }
    $parent = $info['base theme'];

    return $this->buildRecursiveListOfParentThemes($parent, \array_merge($themes, [$parent]));
  }

}
