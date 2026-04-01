<?php

declare(strict_types=1);

namespace Drupal\display_builder\Exception;

/**
 * Thrown when island form validation fails during a builder API request.
 *
 * The exception message contains the first human-readable validation error
 * produced by the form. Callers convert it to an HTMX error response.
 *
 * Extends \RuntimeException so callers can catch it selectively without
 * catching all possible exceptions.
 */
final class FormValidationException extends \RuntimeException {

}
