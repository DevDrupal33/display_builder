<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\DisplayVariant;

use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Plugin\DisplayVariant\SimplePageVariant;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A variant for pages managed by Display Builder Page Layout.
 */
#[PageDisplayVariant(
  id: 'display_builder_full',
  admin_label: new TranslatableMarkup('Full page Display Builder')
)]
class FullPageBuilderPageVariant extends SimplePageVariant implements ContainerFactoryPluginInterface {

  /**
   * The theme registry.
   */
  protected Registry $themeRegistry;

  /**
   * The list of modules.
   */
  protected ExtensionList $modules;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Registry $theme_registry,
    ExtensionList $modules,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeRegistry = $theme_registry;
    $this->modules = $modules;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme.registry'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder_page_layout\Hook\PageLayoutHook
   */
  public function build() {
    $build = parent::build();
    $build['#page_variant'] = 'display_builder_full';

    // We alter the registry runtime here instead of implementing
    // hook_theme_registry_alter in order keep the alteration specific to each
    // page.
    $theme_registry = $this->themeRegistry->get();
    $template_uri = $this->modules->getPath('display_builder_page_layout') . '/templates';
    $runtime = $this->themeRegistry->getRuntime();
    $theme_registry['page']['path'] = $template_uri;
    $runtime->set('page', $theme_registry['page']);

    return $build;
  }

}
