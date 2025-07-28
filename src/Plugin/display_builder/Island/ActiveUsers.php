<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * History buttons island plugin implementation.
 */
#[Island(
  id: 'active_users',
  label: new TranslatableMarkup('Active users'),
  description: new TranslatableMarkup('Users currently doing changes on the builder.'),
  type: IslandType::Button,
)]
class ActiveUsers extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;

  /**
   * Seconds in 15 minutes.
   */
  private const SECONDS_IN_15_MINUTES = 900;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'image_field' => 'user_picture',
      'image_style' => 'thumbnail',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [
      '' => $this->t('- None -'),
    ];
    $fields = $this->entityFieldManager->getFieldDefinitions('user', 'user');
    foreach ($fields as $field_id => $field) {
      if ($field->getType() === 'image') {
        $options[$field_id] = $field->getLabel();
      }
    }
    $form['image_field'] = [
      '#title' => $this->t('Image field'),
      '#type' => 'select',
      '#default_value' => $this->getConfiguration()['image_field'],
      '#options' => $options,
    ];

    $styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    $options = [
      '' => $this->t('- None -'),
    ];
    foreach ($styles as $name => $style) {
      $options[$name] = $style->label();
    }
    $form['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getConfiguration()['image_style'],
      '#options' => $options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $users = $this->stateManager->getUsers($builder_id);
    $current_user = $this->currentUser->id();
    $users = $this->removeInactiveUsers($users);
    // If no users (this situation must not happen), don't show anything.
    if (count($users) === 0) {
      return [];
    }
    if (array_key_exists($current_user, $users)) {
      // If the only user is the current user, don't show anything.
      if (count($users) === 1) {
        return [];
      }
      // Move current user at the beginning of the list.
      $users = [$current_user => $users[$current_user]] + $users;
      return $this->buildRenderable($users);
    }
    // If the only user is not the current user, add they at the start.
    if (count($users) === 1) {
      $users = [$current_user => NULL] + $users;
    }
    return $this->buildRenderable($users);
  }

  /**
   * Remove inactive users.
   *
   * @param array $users
   *   Each key is an User entity ID, each value is a timestamp.
   *
   * @return array
   *   Each key is an User entity ID, each value is a timestamp.
   */
  protected function removeInactiveUsers(array $users): array {
    foreach ($users as $user_id => $time) {
      if (\time() - $time > self::SECONDS_IN_15_MINUTES) {
        unset($users[$user_id]);
      }
    }
    return $users;
  }

  /**
   * Build renderable.
   *
   * @param array $users
   *   Each key is an User entity ID, each value is a timestamp.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildRenderable(array $users): array {
    $avatars = [];
    foreach ($users as $user_id => $time) {
      $user = $this->entityTypeManager->getStorage('user')->load($user_id);
      if (!$user) {
        // For example, if the user was deleted.
        continue;
      }
      $avatar = [
        '#type' => 'component',
        '#component' => 'display_builder:avatar',
        '#props' => [
          'name' => $user->getDisplayName(),
        ],
        '#attributes' => [
          'style' => '--size: 38px',
        ],
      ];
      if ($time) {
        // We can't use DateFormatterInterface::formatTimeDiffSince() because
        // the displayed value will become obsolete if the island is not updated
        // for a while.
        $time = $this->dateFormatter->format($time, 'custom', 'G:i');
        $avatar['#props']['name'] .= ', ' . $this->t('at @time', ['@time' => $time]);
      }
      $image_field = $this->getConfiguration()['image_field'];
      if ($image_field && !$user->get($image_field)->isEmpty()) {
        /** @var \Drupal\file\FileInterface $image */
        $image = $user->get($image_field)->entity;
        $image_style = $this->getConfiguration()['image_style'];
        $style = $this->entityTypeManager->getStorage('image_style')->load($image_style);
        $avatar['#props']['image'] = $style ? $style->buildUri($image->getFileUri()) : $image->getFileUri();
      }
      $avatars[] = $avatar;
    }
    if (count($avatars) === 0) {
      return [];
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => 'sl-avatar-group',
      ],
      'avatars' => $avatars,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id, ?string $current_island_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * Rebuilds the island with the given builder ID.
   *
   * @param string $builder_id
   *   The ID of the builder.
   *
   * @return array
   *   The rebuilt island.
   */
  private function rebuild(string $builder_id): array {
    return $this->addOutOfBand(
    $this->build($builder_id, []),
    '#' . $this->getHtmlId($builder_id),
    'innerHTML'
    );
  }

}
