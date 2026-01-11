<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Builder island plugin implementation.
 *
 * Renders build content via internal subrequest to ensure proper theme
 * isolation - content is rendered with frontend theme while the admin
 * UI uses the backend theme. JavaScript moves content into Shadow DOM.
 */
#[Island(
  id: 'builder',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Builder'),
  description: new TranslatableMarkup('The Display Builder main island. Build the display with dynamic preview.'),
  type: IslandType::View,
  icon: 'tools',
)]
class BuilderPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'b' => new TranslatableMarkup('Show the builder'),
    ];
  }

  /**
   * Render build content via internal subrequest.
   *
   * Makes a subrequest to the BuildController which renders the content
   * with the frontend theme and returns HTML + CSS as JSON.
   *
   * @param string $builder_id
   *   The builder instance ID.
   *
   * @return array
   *   Array with 'html', 'css', 'js', and 'settings' keys.
   */
  protected function buildViaSubrequest(string $builder_id): array {
    $url = Url::fromRoute('display_builder.build', [
      'display_builder_instance' => $builder_id,
    ])->toString();

    // Get current request to copy context.
    $current_request = \Drupal::request();

    // Create subrequest with the same server context.
    $subrequest = Request::create(
      $url,
      'GET',
    // Query.
      [],
    // Cookies.
      $current_request->cookies->all(),
    // Files.
      [],
    // Server.
      $current_request->server->all()
    );

    // Copy session to preserve authentication.
    if ($current_request->hasSession()) {
      $subrequest->setSession($current_request->getSession());
    }

    // Mark this as an internal subrequest so our access checker can allow it.
    // This is checked by SubrequestAccessCheck.
    $subrequest->attributes->set('_display_builder_internal', TRUE);

    // Execute subrequest.
    $kernel = \Drupal::service('http_kernel');
    $response = $kernel->handle($subrequest, HttpKernelInterface::SUB_REQUEST);

    $content = $response->getContent();
    if ($content === FALSE) {
      return ['html' => '', 'css' => [], 'js' => [], 'settings' => []];
    }

    $data = json_decode($content, TRUE);

    if (!is_array($data)) {
      // Log error for debugging.
      \Drupal::logger('display_builder')->error('Subrequest failed for @id: Status @status, Content: @content', [
        '@id' => $builder_id,
        '@status' => $response->getStatusCode(),
        '@content' => substr($content, 0, 500),
      ]);
      return ['html' => '', 'css' => [], 'js' => [], 'settings' => []];
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    // Fetch content via subrequest (rendered with frontend theme).
    $subrequest_data = $this->buildViaSubrequest($builder_id);

    // Mark HTML as safe - it comes from our own renderer and contains
    // HTMX attributes and other markup that must not be sanitized.
    $html = Markup::create($subrequest_data['html'] ?? '');

    // Return container with staging area for the content.
    // JavaScript will move this into Shadow DOM for CSS isolation.
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['db-build-container'],
        'data-db-build-container' => $builder_id,
        'data-db-build-css' => json_encode($subrequest_data['css'] ?? []),
      ],
      'staging' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['db-build-staging', 'db-build-content'],
        ],
        '#markup' => $html,
      ],
      '#attached' => [
        'library' => ['display_builder/build_container'],
      ],
    ];
  }

  /**
   * Build OOB-swap for staging area update.
   *
   * @param string $builder_id
   *   The builder instance ID.
   *
   * @return array
   *   OOB-swap render array.
   */
  protected function buildStagingOobSwap(string $builder_id): array {
    $subrequest_data = $this->buildViaSubrequest($builder_id);

    // Mark HTML as safe - it comes from our own renderer.
    $html = Markup::create($subrequest_data['html'] ?? '');

    return $this->addOutOfBand([
      '#type' => 'container',
      '#attributes' => [
        'class' => ['db-build-staging', 'db-build-content'],
        'data-db-build-css' => json_encode($subrequest_data['css'] ?? []),
      ],
      '#markup' => $html,
    ], '[data-db-build-container="' . $builder_id . '"] .db-build-staging', 'outerHTML');
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->buildStagingOobSwap($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->buildStagingOobSwap($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->buildStagingOobSwap($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->buildStagingOobSwap($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->buildStagingOobSwap($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->buildStagingOobSwap($builder_id);
  }

}
