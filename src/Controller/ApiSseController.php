<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\ServerEvent;

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

  /**
   * Stream server side events for real-time collaboration.
   *
   * Update islands every time another user is triggering a state altering
   * event: ON_UPDATE, ON_ATTACH_TO_ROOT, ON_ATTACH_TO_SLOT, ON_MOVE,
   * ON_DELETE, ON_PRESET_SAVE, ON_PUBLISH and ON_HISTORY_CHANGE.
   * Skip ON_ACTIVE.
   *
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return \Symfony\Component\HttpFoundation\EventStreamResponse
   *   The event stream response.
   *
   * @see https://v1.htmx.org/extensions/server-sent-events/
   * @see https://symfony.com/blog/new-in-symfony-7-3-simpler-server-event-streaming
   */
  public function sse(InstanceInterface $display_builder_instance): EventStreamResponse {
    $builder_id = (string) $display_builder_instance->id();

    return new EventStreamResponse(function () use ($builder_id) {
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
        $latest = $collection->get("{$builder_id}_latest");

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

        // Reload the instance to ensure we get the builder's latest
        // state.
        $builder = $this->entityTypeManager()->getStorage('display_builder_instance')
          ->loadUnchanged($builder_id);

        if (!$builder instanceof InstanceInterface) {
          \sleep($this::REFRESH_WINDOW);

          continue;
        }
        $this->builder = $builder;

        // Recompute islands regarding the current user.
        // Use ON_HISTORY_CHANGE because it is the event where most islands are
        // updated.
        $event = $this->createEventWithEnabledIsland(
          DisplayBuilderEvents::ON_HISTORY_CHANGE,
          $builder->getSources(),
          NULL,
          NULL,
        );

        foreach ($event->getResult() as $island_id => $result) {
          // Do nothing if the island is not updated.
          if (empty($result)) {
            continue;
          }

          // Do nothing if the island is empty.
          if (!isset($result['content'])) {
            continue;
          }

          $sse_target = "island-{$builder->id()}-{$island_id}";
          $data = $this->renderer->renderInIsolation($result['content']);

          // Need to send the data back as one line.
          // @see https://www.reddit.com/r/htmx/comments/1mjhzl1/comment/n7bdf0s
          $data = \str_replace(["\r\n", "\r", "\n"], ' ', (string) $data);

          yield new ServerEvent($data, type: $sse_target);
        }

        \sleep($this::REFRESH_WINDOW);
      }
    });
  }

}
