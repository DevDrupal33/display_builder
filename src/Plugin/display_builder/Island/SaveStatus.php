<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHtmx;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Save status island plugin implementation.
 *
 * A dot and a word in the toolbar confirming that the last action reached
 * the stored state. Every mutation already persists a revision before the
 * response is built, but nothing said so: the undo counter only reports
 * how much history exists, and the toast stack is reserved for errors -
 * success toasts on every drag would train people to stop reading the
 * corner that carries failures.
 *
 * The label is state, the pulse is event. build() derives the label from
 * the instance itself whenever it is called without a status (the initial
 * page build, or a plain reload()), so the pip is a standing answer to
 * "where does this display stand", not only a flash. An event handler
 * passes its own status through $options, which both overrides the label
 * for that one render and turns the animation on.
 *
 * That is why publish, restore and revert are separate statuses rather
 * than all folded into "saved": they are the three actions whose outcome
 * a user cannot infer from the canvas, and each carries its own color.
 * The transient ones (restored, reverted) describe how the display got
 * here, so decaying to the plain state label on the next render is
 * correct rather than a bug.
 */
#[Island(
  id: 'save_status',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Save status'),
  description: new TranslatableMarkup('A small indicator confirming the last action reached the stored state.'),
  type: IslandType::Button,
  region: 'start',
)]
class SaveStatus extends IslandPluginBase {

  /**
   * A draft revision was written, the display differs from the published one.
   */
  private const string STATUS_SAVED = 'saved';

  /**
   * The display was published: the current state is the published one.
   */
  private const string STATUS_PUBLISHED = 'published';

  /**
   * The display was restored to the last published version.
   */
  private const string STATUS_RESTORED = 'restored';

  /**
   * An entity display override was reverted to the published entity display.
   */
  private const string STATUS_REVERTED = 'reverted';

  /**
   * Key carrying the status through the $options passed to build().
   */
  private const string OPTION_STATUS = 'save_status';

  /**
   * {@inheritdoc}
   *
   * Overrides build() rather than buildContent(), like the other toolbar
   * islands: IslandPluginBase::build() gates on isApplicable(), which
   * requires a node context no toolbar island ever has.
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $status = $options[self::OPTION_STATUS] ?? $this->restingStatus($builder);

    return [
      '#type' => 'component',
      '#component' => 'display_builder:save_status',
      '#props' => [
        'variant' => $status,
        'label' => (string) ($this->statusLabels()[$status] ?? $this->statusLabels()[self::STATUS_SAVED]),
        // Only an action animates. A reload triggered by anything else
        // rebuilds the same pip silently.
        'pulse' => isset($options[self::OPTION_STATUS]),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithStatus($instance, self::STATUS_SAVED);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array {
    return $this->reloadWithStatus($instance, self::STATUS_SAVED);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithStatus($instance, self::STATUS_SAVED);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithStatus($instance, self::STATUS_SAVED);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array {
    return $this->reloadWithStatus($instance, self::STATUS_SAVED);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(InstanceInterface $instance): array {
    return $this->reloadWithStatus($instance, self::STATUS_SAVED);
  }

  /**
   * {@inheritdoc}
   */
  public function onPublish(InstanceInterface $instance): array {
    return $this->reloadWithStatus($instance, self::STATUS_PUBLISHED);
  }

  /**
   * {@inheritdoc}
   */
  public function onRestore(InstanceInterface $instance): array {
    return $this->reloadWithStatus($instance, self::STATUS_RESTORED);
  }

  /**
   * {@inheritdoc}
   */
  public function onRevert(InstanceInterface $instance): array {
    return $this->reloadWithStatus($instance, self::STATUS_REVERTED);
  }

  /**
   * Reloads the island showing a given status, with the pulse animation on.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string $status
   *   One of the self::STATUS_* constants.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  private function reloadWithStatus(InstanceInterface $instance, string $status): array {
    return DisplayBuilderHtmx::outOfBand(
      $this->build($instance, $instance->getCurrentState(), [self::OPTION_STATUS => $status]),
      '#' . $this->getHtmlId((string) $instance->id()),
      'innerHTML'
    );
  }

  /**
   * The status to show when no action caused this render.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The display builder instance.
   *
   * @return string
   *   One of the self::STATUS_* constants.
   */
  private function restingStatus(InstanceInterface $builder): string {
    // isPublishedPresent() resolves the buildable plugin's sources to hash
    // them, so it is only worth asking where publishing is a thing at all.
    if ($builder->isPublishable() && $builder->isPublishedPresent()) {
      return self::STATUS_PUBLISHED;
    }

    return self::STATUS_SAVED;
  }

  /**
   * Human-readable name for each status.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Labels keyed by the self::STATUS_* constants.
   */
  private function statusLabels(): array {
    return [
      self::STATUS_SAVED => $this->t('Saved'),
      self::STATUS_PUBLISHED => $this->t('Published'),
      self::STATUS_RESTORED => $this->t('Restored'),
      self::STATUS_REVERTED => $this->t('Reverted'),
    ];
  }

}
