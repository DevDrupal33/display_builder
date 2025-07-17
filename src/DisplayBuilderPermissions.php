<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions of the display_builder module.
 */
class DisplayBuilderPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DisplayBuilderPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DisplayBuilderPermissions {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of permissions.
   *
   * @return array
   *   An array of permissions keyed by permission name.
   */
  public function permissions(): array {
    $permissions = [];
    // Generate permissions for each display builder. Warn the administrator
    // that any of them are potentially unsafe.
    /** @var \Drupal\display_builder\DisplayBuilderInterface[] $builders */
    $builders = $this->entityTypeManager->getStorage('display_builder')->loadMultiple();
    uasort($builders, 'Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    foreach ($builders as $builder) {
      $permission = $builder->getPermissionName();
      $permissions[$permission] = [
        'title' => $this->t(
          'Use the <a href=":url">@label</a> display builder',
          [
            ':url' => $builder->toUrl()->toString(),
            '@label' => $builder->label(),
          ]
        ),
        'description' => [
          '#prefix' => '<em>',
          '#markup' => $this->t('Warning: This permission may have security implications depending on how the display builder is configured.'),
          '#suffix' => '</em>',
        ],
        // This permission is generated on behalf of $builder display builder,
        // therefore add this display builder as a config dependency.
        'dependencies' => [
          $builder->getConfigDependencyKey() => [
            $builder->getConfigDependencyName(),
          ],
        ],
      ];
    }
    return $permissions;
  }

}
