<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\ServerEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Display builder routes.
 */
class ApiSseController extends ApiControllerBase {

  /**
   * Check if the builder needs to be refreshed after this period, in seconds.
   */
  public const int REFRESH_WINDOW = 2;

  /**
   * Refresh builder only if the last edit is older than this window.
   */
  public const int STALE = (self::REFRESH_WINDOW * 2) - 1;

  public function __construct(
    EventDispatcherInterface $eventDispatcher,
    MemoryCacheInterface $memoryCache,
    RendererInterface $renderer,
    TimeInterface $time,
    #[Autowire(service: 'tempstore.shared')] SharedTempStoreFactory $sharedTempStoreFactory,
    SessionInterface $session,
    private StateInterface $state,
  ) {
    parent::__construct($eventDispatcher, $memoryCache, $renderer, $time, $sharedTempStoreFactory, $session);
  }

  /**
   * Stream server side events for real-time collaboration.
   *
   * Update islands every time another user is triggering a state altering
   * event: ON_UPDATE, ON_ATTACH_TO_ROOT, ON_ATTACH_TO_SLOT, ON_MOVE,
   * ON_DELETE, ON_PRESET_SAVE, ON_SAVE and ON_HISTORY_CHANGE.
   * Skip ON_ACTIVE.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Symfony\Component\HttpFoundation\EventStreamResponse
   *   The event stream response.
   *
   * @see https://v1.htmx.org/extensions/server-sent-events/
   * @see https://symfony.com/blog/new-in-symfony-7-3-simpler-server-event-streaming
   */
  public function sse(InstanceInterface $builder): EventStreamResponse {
    return new EventStreamResponse(function () use ($builder) {
      $sessionId = $this->session->getId();
      $collection = $this->sharedTempStoreFactory->get($this::SSE_COLLECTION);

      // Infinite loop because we keep the HTTP transaction open until it is
      // closed by the client.
      // @phpstan-ignore-next-line
      while (TRUE) {
        // We start by sending an empty event to avoid HTTP timeout. We need
        // at least the HTTP response headers to be sent back to client without
        // waiting for a real event to occur.
        yield new ServerEvent('');
        $latest = $collection->get("{$builder->id()}_latest");

        if (!$latest) {
          \sleep($this::REFRESH_WINDOW);

          continue;
        }

        // The current session is the last one editing the builder. Do nothing.
        // Use session ID to handle:
        // - different users
        // - the same user in different browsers
        // Not handled: multiple tabs with the same user in the same browser.
        if ($latest['sessionId'] === $sessionId) {
          \sleep($this::REFRESH_WINDOW);

          continue;
        }

        // If the last edit is older than STALE, do nothing
        // as we consider that the builder has already been refreshed.
        if ($latest['timestamp'] < $this->time->getCurrentTime() - $this::STALE) {
          \sleep($this::REFRESH_WINDOW);

          continue;
        }

        // Reset state static cache to ensure we load the builder's latest
        // state.
        $this->state->resetCache();

        // Recompute islands regarding the current user.
        // Use ON_HISTORY_CHANGE because it is the event where most islands are
        // updated.
        $event = $this->createEventWithEnabledIsland(
          DisplayBuilderEvents::ON_HISTORY_CHANGE,
          $builder,
          $builder->getCurrentState(),
          NULL,
          NULL,
          NULL,
        );

        $updated = FALSE;
        foreach ($event->getResult() as $island_id => $result) {
          // Do nothing if the island is not updated.
          if (empty($result)) {
            continue;
          }

          // Do nothing if the island is empty.
          if (!isset($result['content'])) {
            continue;
          }

          $updated = TRUE;
          $sse_target = "island-{$builder->id()}-{$island_id}";
          $data = $this->renderer->renderInIsolation($result['content']);

          // Need to send the data back as one line.
          // @see https://www.reddit.com/r/htmx/comments/1mjhzl1/comment/n7bdf0s
          $data = \str_replace(["\r\n", "\r", "\n"], ' ', (string) $data);

          yield new ServerEvent($data, type: $sse_target);
        }

        if ($updated) {
          $message = $this->buildMessage($this->t('The user @username is currently editing this display, your version has been refreshed.', [
            '@username' => $latest['username'],
          ]));
          $sse_target = "message-{$builder->id()}";

          $data = $this->renderer->renderInIsolation($message);
          // Need to send the data back as one line.
          // @see https://www.reddit.com/r/htmx/comments/1mjhzl1/comment/n7bdf0s
          $data = \str_replace(["\r\n", "\r", "\n"], ' ', (string) $data);
          yield new ServerEvent($data, type: $sse_target);
        }

        \sleep($this::REFRESH_WINDOW);
      }
    });
  }

  /**
   * Build message.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to display.
   *
   * @return array
   *   The message render array.
   */
  protected function buildMessage(string|TranslatableMarkup $message): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:alert',
      '#slots' => [
        'content' => $message,
      ],
      '#props' => [
        'variant' => 'neutral',
        'icon' => 'exclamation-octagon',
        'open' => TRUE,
        'closable' => TRUE,
        'duration' => $this::REFRESH_WINDOW * 1000,
      ],
      '#attributes' => [
        'class' => 'db-message',
        // @todo to test if it adapts automatically between rtl and ltr.
        'countdown' => 'undefined',
      ],
    ];

    return $build;
  }

}
