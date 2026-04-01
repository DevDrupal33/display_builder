<?php

declare(strict_types=1);

namespace Drupal\display_builder\Exception;

/**
 * Thrown when a tree operation targets a node that does not exist or invalid.
 *
 * Examples: attaching to a parent slot that cannot be found, or a node ID
 * mismatch detected during a source update.
 *
 * Extends \RuntimeException so callers can catch it selectively without
 * catching all possible exceptions.
 */
final class InvalidNodeException extends \RuntimeException {

}
