<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
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
 * build() only ever computes the *resting* status - what the pip should
 * show on the initial page build, or a plain reload() - because the four
 * labels it could show are static, translated strings that never depend on
 * anything the server alone knows. They ship once, as data attributes on
 * the component root, and components/save_status/save_status.js flips the
 * pip's class/label/pulse itself straight off the client's own successful
 * mutation, undo/redo, publish, restore or revert request, the same way
 * assets/js/instances.js reacts to its own dot - see
 * assets/js/request_action.js for the classifier both share. That is why
 * publish, restore and revert are separate statuses rather than all folded
 * into "saved": they are the three actions whose outcome a user cannot
 * infer from the canvas, and each carries its own color.
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
   * {@inheritdoc}
   *
   * Overrides build() rather than buildContent(), like the other toolbar
   * islands: IslandPluginBase::build() gates on isApplicable(), which
   * requires a node context no toolbar island ever has.
   *
   * Every event this island used to reload for (attach, move, update,
   * delete, undo/redo, publish, restore, revert) is now handled entirely by
   * components/save_status/save_status.js instead, so build() only has to
   * answer the resting-state question and hand the JS the labels it needs
   * for every other state up front.
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $status = $this->restingStatus($builder);
    $labels = $this->statusLabels();

    return [
      '#type' => 'component',
      '#component' => 'display_builder:save_status',
      '#props' => [
        'variant' => $status,
        'label' => (string) $labels[$status],
        'label_saved' => (string) $labels[self::STATUS_SAVED],
        'label_published' => (string) $labels[self::STATUS_PUBLISHED],
        'label_restored' => (string) $labels[self::STATUS_RESTORED],
        'label_reverted' => (string) $labels[self::STATUS_REVERTED],
      ],
    ];
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
    if ($builder->isPublishedPresent()) {
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
