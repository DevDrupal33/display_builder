<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
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
  default_region: 'end',
)]
class Collaboration extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandReloadEventsTrait;
  use IslandConfigurationFormTrait;

  /**
   * Seconds in 15 minutes.
   */
  private const SECONDS_IN_15_MINUTES = 900;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

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
    $with_picture_text = $conf['image_style'] ? $this->t('With @style picture.', ['@style' => $conf['image_style']]) : $this->t('With picture.');

    return [
      $conf['image_field'] ? $with_picture_text : $this->t('Without picture.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $this->builder = $builder;
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
  public function alterRenderable(InstanceInterface $instance, array $build): array {
    $build['#attributes'] = [
      'hx-ext' => 'sse',
      'sse-connect' => Url::fromRoute('display_builder.api_sse', ['display_builder_instance' => (string) $instance->id()])->toString(),
    ];
    // We don't attach it from the ::build() because the island doesn't
    // always render something in the toolbar.
    $build['#attached']['library'][] = 'display_builder/htmx_sse';

    return $build;
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

}
