<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Display builder routes.
 */
class IframeController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function getIsland(Request $request, InstanceInterface $builder, string $island_id): array {
    /** @var \Drupal\display_builder\ProfileInterface $profile */
    $profile = $builder->getProfile();

    return $profile->buildSingleIsland($builder, $island_id);
  }

}
