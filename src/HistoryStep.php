<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single step in the instance log history.
 */
class HistoryStep {

  /**
   * Construct an history step.
   *
   * @param array $data
   *   An UI Patterns sources tree.
   * @param int $hash
   *   An unique hash, as an crc32 checksum integer.
   * @param \Drupal\Component\Render\FormattableMarkup|string|null $log
   *   A log message.
   * @param int $time
   *   A timestamp.
   * @param ?int $user
   *   A Drupal user entity ID.
   */
  public function __construct(
    #[Assert\Type('list')]
    // Writable because of Instance::postCreate().
    public array $data,
    public readonly int $hash,
    public readonly null|FormattableMarkup|string $log,
    #[Assert\Positive]
    public readonly int $time,
    #[Assert\PositiveOrZero]
    public readonly ?int $user,
  ) {}

}
