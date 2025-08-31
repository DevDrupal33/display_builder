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
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Real-time collaboration island plugin implementation.
 */
#[Island(
  id: 'collaboration',
  label: new TranslatableMarkup('Real-time collaboration'),
  description: new TranslatableMarkup('Allow concurrent editing with multiple users.'),
  type: IslandType::Button,
)]
class Collaboration extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;

  /**
   * Seconds in 15 minutes.
   */
  private const SECONDS_IN_15_MINUTES = 900;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The entity field manager.
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
    $configuration = $this->getConfiguration();

    $fields = $this->entityFieldManager->getFieldDefinitions('user', 'user');

    foreach ($fields as $field_id => $field) {
      if ($field->getType() === 'image') {
        $options[$field_id] = $field->getLabel();
      }
    }
    $form['image_field'] = [
      '#title' => $this->t('Image field'),
      '#type' => 'select',
      '#default_value' => $configuration['image_field'],
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
      '#default_value' => $configuration['image_style'],
      '#options' => $options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $conf = $this->getConfiguration();
    $with_picture_text = $conf['image_style'] ? $this->t('With @style picture.', ['@style' => $conf['image_style']]) : $this->t('With picture');

    return [
      $conf['image_field'] ? $with_picture_text : $this->t('Without picture.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $builder_id = (string) $builder->id();
    // @todo pass \Drupal\display_builder\InstanceInterface object in
    // parameters instead of loading again.
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
    $users = $builder->getUsers();
    $current_user = $this->currentUser->id();
    $users = $this->removeInactiveUsers($users);

    // If no users (this situation must not happen), don't show anything.
    if (\count($users) === 0) {
      return [];
    }

    if (\array_key_exists($current_user, $users)) {
      // If the only user is the current user, don't show anything.
      if (\count($users) === 1) {
        return [];
      }
      // Move current user at the beginning of the list.
      $users = [$current_user => $users[$current_user]] + $users;

      return $this->buildRenderable($users);
    }

    // If the only user is not the current user, add they at the start.
    if (\count($users) === 1) {
      $users = [$current_user => NULL] + $users;
    }

    return $this->buildRenderable($users);
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
    $configuration = $this->getConfiguration();

    foreach ($users as $user_id => $time) {
      /** @var \Drupal\user\UserInterface $user */
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

      if (isset($configuration['image_field']) && $user->hasField($configuration['image_field'])) {
        $image = $user->get($configuration['image_field'])->entity;

        if ($image instanceof FileInterface) {
          $image_style = $configuration['image_style'];
          $style = $this->entityTypeManager->getStorage('image_style')->load($image_style);
          $avatar['#props']['image'] = $style ? $style->buildUri($image->getFileUri()) : $image->getFileUri();
        }
      }
      $avatars[] = $avatar;
    }

    if (\count($avatars) === 0) {
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
   * Rebuilds the island with the given builder ID.
   *
   * @param string $builder_id
   *   The ID of the builder.
   *
   * @return array
   *   The rebuilt island.
   */
  private function rebuild(string $builder_id): array {
    // @todo pass \Drupal\display_builder\InstanceInterface object in
    // parameters instead of loading again.
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);

    return $this->addOutOfBand(
      $this->build($builder, []),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
