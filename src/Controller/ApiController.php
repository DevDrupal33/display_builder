<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\Exception\FormValidationException;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Plugin\display_builder\Island\ContextualFormPanel;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\display_builder\SourceTree;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Display builder routes.
 */
class ApiController extends ApiControllerBase implements ApiControllerInterface {

  use RenderableBuilderTrait;

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected RendererInterface $renderer,
    protected TimeInterface $time,
    #[Autowire(service: 'tempstore.shared')]
    protected SharedTempStoreFactory $sharedTempStoreFactory,
    protected SessionInterface $session,
    protected RequestStack $requestStack,
    private IslandPluginManagerInterface $islandPluginManager,
  ) {
    parent::__construct($eventDispatcher, $renderer, $time, $sharedTempStoreFactory, $session, $requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public function reloadIsland(Request $request, InstanceInterface $display_builder_instance, string $island_id): array {
    $profile = $display_builder_instance->getProfile();

    if (!isset($profile->getEnabledIslands()[$island_id])) {
      $message = $this->t('[reloadIsland] Island @island is not enabled on this profile', ['@island' => $island_id]);
      $debug = [
        'island_id' => $island_id,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }

    $contexts = $display_builder_instance->getAvailableContexts();
    $definitions = \array_intersect_key(
      $this->islandPluginManager->getDefinitions(),
      [$island_id => TRUE],
    );
    $islands = $this->islandPluginManager->createInstances($definitions, $contexts, $profile->getIslandConfigurations());
    $island = $islands[$island_id] ?? NULL;

    if ($island === NULL) {
      $message = $this->t('[reloadIsland] Island @island is not available in this context', ['@island' => $island_id]);
      $debug = [
        'island_id' => $island_id,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }

    // This is the only GET endpoint that returns rendered island output, so it
    // is the only island response Dynamic Page Cache can store. The island
    // build reflects the instance's live working state but bubbles only the
    // SDC/block cacheability, not the instance's own cache tag. Every mutation
    // calls Instance::save(), which invalidates that tag, so attaching it here
    // makes the cached reload bust on the next change instead of serving stale
    // markup until the whole cache is flushed. Mirrors the full-page render in
    // ProfileViewBuilder::view().
    return [
      $island->reload($display_builder_instance),
      '#cache' => [
        'tags' => $display_builder_instance->getCacheTags(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function attachToRoot(Request $request, InstanceInterface $display_builder_instance): array {
    $position = (int) $request->request->get('position', 0);

    if ($request->request->has('preset_id')) {
      $preset_id = (string) $request->request->get('preset_id');

      return $this->attachPresetToRoot($display_builder_instance, $preset_id, $position);
    }

    $is_move = FALSE;

    if ($request->request->has('node_id')) {
      $node_id = (string) $request->request->get('node_id');

      if (!$display_builder_instance->moveToRoot($node_id, $position)) {
        $message = $this->t('[attachToRoot] moveToRoot failed with invalid data');
        $debug = [
          'request' => $request->request->all(),
          'instance' => $display_builder_instance,
        ];

        return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
      }

      $is_move = TRUE;
    }
    elseif ($request->request->has('source_id')) {
      $source_id = (string) $request->request->get('source_id');
      $data = $request->request->has('source') ? \json_decode((string) $request->request->get('source'), TRUE) : [];
      $node_id = $display_builder_instance->attachToRoot($position, $source_id, $data);
    }
    else {
      $message = '[attachToRoot] Missing content (source_id, node_id or preset_id)';
      $debug = [
        'request' => $request->request->all(),
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;
    // Let's refresh when we add new source to get the placeholder replacement.
    $this->islandId = $this->resolveSkipIslandId($request, $is_move);

    return $this->dispatchDisplayBuilderEvent(
      $is_move ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $node_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function attachToSlot(Request $request, InstanceInterface $display_builder_instance, string $node_id, string $slot): array {
    $parent_id = $node_id;
    $position = (int) $request->request->get('position', 0);

    if ($request->request->has('preset_id')) {
      $preset_id = (string) $request->request->get('preset_id');

      return $this->attachPresetToSlot($display_builder_instance, $preset_id, $parent_id, $slot, $position);
    }

    $is_move = FALSE;

    // First, we update the data state.
    if ($request->request->has('node_id')) {
      $node_id = (string) $request->request->get('node_id');

      if (!$display_builder_instance->moveToSlot($node_id, $parent_id, $slot, $position)) {
        $message = $this->t('[attachToSlot] moveToSlot failed with invalid data');
        $debug = [
          'node_id' => $node_id,
          'slot' => $slot,
          'request' => $request->request->all(),
          'instance' => $display_builder_instance,
        ];

        return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
      }

      $is_move = TRUE;
    }
    elseif ($request->request->has('source_id')) {
      $source_id = (string) $request->request->get('source_id');
      $data = $request->request->has('source') ? \json_decode((string) $request->request->get('source'), TRUE) : [];
      $node_id = $display_builder_instance->attachToSlot($parent_id, $slot, $position, $source_id, $data);
    }
    else {
      $message = $this->t('[attachToSlot] Missing content (component_id, block_id or node_id)');
      $debug = [
        'node_id' => $node_id,
        'slot' => $slot,
        'request' => $request->request->all(),
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;
    // Let's refresh when we add new source to get the placeholder replacement.
    $this->islandId = $this->resolveSkipIslandId($request, $is_move);

    return $this->dispatchDisplayBuilderEvent(
      $is_move ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_SLOT,
      NULL,
      $node_id,
      $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function get(Request $request, InstanceInterface $display_builder_instance, string $node_id): array {
    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_ACTIVE,
      $display_builder_instance->getNode($node_id),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function update(Request $request, InstanceInterface $display_builder_instance, string $node_id): array {
    $this->builder = $display_builder_instance;
    $body = $request->getPayload()->all();

    if (!isset($body['form_id'])) {
      $message = $this->t('[update] Missing payload!');
      $debug = [
        'node_id' => $node_id,
        'request' => $request->request->all(),
        'body' => $body,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }

    // Load the node to properly alter the form data into config data.
    $node = $display_builder_instance->getNode($node_id);

    if (isset($body['source']['form_build_id'])) {
      unset($body['source']['form_build_id'], $body['source']['form_token'], $body['source']['form_id']);
    }

    if (isset($body['form_build_id'])) {
      unset($body['form_build_id'], $body['form_token'], $body['form_id']);
    }
    $form_state = new FormState();
    // Default values are the existing values from the state.
    $form_state->addBuildInfo('args', [
      [
        'island_id' => 'contextual_form',
        'builder_id' => (string) $display_builder_instance->id(),
        'instance' => $node,
      ],
      $display_builder_instance->getAvailableContexts(),
    ]);
    $form_state->setTemporaryValue('gathered_contexts', $display_builder_instance->getAvailableContexts());
    // The body received corresponds to raw form values.
    // We need to set them in the form state to properly
    // take them into account.
    $form_state->setValues($body);

    $formClass = ContextualFormPanel::getFormClass();
    $data = [];

    try {
      $values = $this->validateIslandForm($formClass, $form_state);
      $data['source'] = $values;
    }
    catch (FormAjaxException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $debug = [
        'node_id' => $node_id,
        'request' => $request->request->all(),
        'form' => $form_state->getValues(),
        'body' => $body,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $e->getMessage(), $debug);
    }

    $display_builder_instance->setSource($node_id, $node['source_id'], $data['source']);
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;
    $this->islandId = (string) $request->query->get('from', NULL);

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_UPDATE,
      NULL,
      $node_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function thirdPartySettingsUpdate(Request $request, InstanceInterface $display_builder_instance, string $node_id, string $island_id): array {
    $body = $request->getPayload()->all();

    if (!isset($body['form_id'])) {
      $message = $this->t('[thirdPartySettingsUpdate] Missing payload!');
      $debug = [
        'node_id' => $node_id,
        'island_id' => $island_id,
        'request' => $request->request->all(),
        'body' => $body,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }

    $islandDefinition = $this->islandPluginManager->getDefinition($island_id);
    // Load the instance to properly alter the form data into config data.
    $node = $display_builder_instance->getNode($node_id);
    unset($body['form_build_id'], $body['form_token'], $body['form_id']);

    $form_state = new FormState();
    // Default values are the existing values from the state.
    $form_state->addBuildInfo('args', [
      [
        'island_id' => $island_id,
        'builder_id' => (string) $display_builder_instance->id(),
        'instance' => $node,
      ],
      [],
    ]);

    // phpcs:disable Drupal.Files.LineLength.TooLong
    // @todo should context be injected for third party settings?
    // $form_state->setTemporaryValue('gathered_contexts', $display_builder_instance->getAvailableContexts());
    // phpcs:enable Drupal.Files.LineLength.TooLong
    // The body received corresponds to raw form values.
    // We need to set them in the form state to properly
    // take them into account.
    $form_state->setValues($body);

    $formClass = ($islandDefinition['class'])::getFormClass();
    $values = $this->validateIslandForm($formClass, $form_state);
    // We update the state with the new data.
    $display_builder_instance->setThirdPartySettings($node_id, $island_id, $values);
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;
    $this->islandId = $island_id;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_UPDATE,
      NULL,
      $node_id,
      NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function undo(Request $request, InstanceInterface $display_builder_instance): array {
    /** @var \Drupal\display_builder\Entity\InstanceStorage $storage */
    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface $display_builder_instance */
    $display_builder_instance = $storage->undo($display_builder_instance);

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * {@inheritdoc}
   */
  public function redo(Request $request, InstanceInterface $display_builder_instance): array {
    /** @var \Drupal\display_builder\Entity\InstanceStorage $storage */
    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface $display_builder_instance */
    $display_builder_instance = $storage->redo($display_builder_instance);

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(Request $request, InstanceInterface $display_builder_instance): array {
    $display_builder_instance->clear();
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * Attach a pattern preset to root.
   *
   * Presets are "resolved" after attachment, so they are never moved around.
   *
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $preset_id
   *   Pattern preset ID.
   * @param int $position
   *   Position.
   *
   * @return array
   *   A renderable array.
   */
  protected function attachPresetToRoot(InstanceInterface $display_builder_instance, string $preset_id, int $position): array {
    $presetStorage = $this->entityTypeManager()->getStorage('pattern_preset');

    /** @var \Drupal\display_builder\Entity\PatternPresetInterface $preset */
    $preset = $presetStorage->load($preset_id);
    $data = $preset->getSources();

    if (!isset($data['source_id']) || !isset($data['source'])) {
      $message = $this->t('[attachToRoot] Missing preset source_id data');
      $debug = [
        'preset_id' => $preset_id,
        'position' => $position,
        'data' => $data,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }
    $node_id = $display_builder_instance->attachToRoot($position, $data['source_id'], $data['source']);

    foreach ($data['third_party_settings'] ?? [] as $provider => $settings) {
      $display_builder_instance->setThirdPartySettings($node_id, $provider, $settings ?? []);
    }
    $display_builder_instance->save();
    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $node_id,
    );
  }

  /**
   * Attach a pattern preset to a slot .
   *
   * Presets are "resolved" after attachment, so they are never moved around.
   *
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $preset_id
   *   Pattern preset ID.
   * @param string $parent_id
   *   Parent instance ID.
   * @param string $slot
   *   Slot.
   * @param int $position
   *   Position.
   *
   * @return array
   *   A renderable array.
   */
  protected function attachPresetToSlot(InstanceInterface $display_builder_instance, string $preset_id, string $parent_id, string $slot, int $position): array {
    $presetStorage = $this->entityTypeManager()->getStorage('pattern_preset');

    /** @var \Drupal\display_builder\Entity\PatternPresetInterface $preset */
    $preset = $presetStorage->load($preset_id);
    $data = $preset->getSources();

    if (!isset($data['source_id']) || !isset($data['source'])) {
      $message = $this->t('[attachToSlot] Missing preset source_id data');
      $debug = [
        'preset_id' => $preset_id,
        'parent_id' => $parent_id,
        'slot' => $slot,
        'position' => $position,
        'data' => $data,
        'instance' => $display_builder_instance,
      ];

      return $this->responseMessageError((string) $display_builder_instance->id(), $message, $debug);
    }
    $node_id = $display_builder_instance->attachToSlot($parent_id, $slot, $position, $data['source_id'], $data['source']);

    foreach ($data['third_party_settings'] ?? [] as $provider => $settings) {
      $display_builder_instance->setThirdPartySettings($node_id, $provider, $settings ?? []);
    }

    $display_builder_instance->save();
    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $node_id,
    );
  }

  /**
   * Determines which island (if any) to skip from the event dispatch fan-out.
   *
   * Builder, Wireframe, and Tree share the same Sortable group (@see
   * components/dropzone/dropzone.js), so an existing node can be dragged
   * from one panel's dropzone straight into another's. Sortable only
   * relocates the DOM node - it never re-renders it - so after a
   * cross-panel move the dropped element is still wearing whichever
   * panel originally rendered it (@see
   * BuilderPanel::buildNodeAttributes(), display_builder.js's addVals()).
   * Skipping the destination island's own re-render is only safe when the
   * move stayed within that same island; otherwise its dropzone would be
   * left showing another island's markup until the next full reload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param bool $is_move
   *   Whether this attach is actually moving an existing node.
   *
   * @return string|null
   *   The island ID to skip, or NULL to skip none.
   */
  private function resolveSkipIslandId(Request $request, bool $is_move): ?string {
    if (!$is_move) {
      return NULL;
    }

    $destination_island = (string) $request->query->get('from', '');
    $source_island = (string) $request->request->get('source_island', '');

    return ($source_island !== '' && $source_island === $destination_island) ? $destination_island : NULL;
  }

  /**
   * Validates an island form.
   *
   * @param string $formClass
   *   The form class.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The validated values.
   */
  private function validateIslandForm(string $formClass, FormStateInterface $form_state): array {
    /** @var \Drupal\Core\Form\FormBuilder $formBuilder */
    $formBuilder = $this->formBuilder();

    try {
      $triggering_element = $form_state->getTriggeringElement();

      if (!$triggering_element && !isset($form_state->getValues()['_triggering_element_name'])) {
        // We set a fake triggering element to avoid form API error.
        $form_state->setTriggeringElement([
          '#type' => 'submit',
          '#limit_validation_errors' => FALSE,
          '#value' => (string) $this->t('Submit'),
        ]);
      }
      $form = $formBuilder->buildForm($formClass, $form_state);
      $formErrors = $form_state->getErrors();

      if (!empty($formErrors)) {
        $first_error = \reset($formErrors);

        throw new FormValidationException((string) $first_error);
      }
      $formBuilder->validateForm($formClass, $form, $form_state);
      $formErrors = $form_state->getErrors();

      if (!empty($formErrors)) {
        $first_error = \reset($formErrors);

        throw new FormValidationException((string) $first_error);
      }
    }
    catch (FormAjaxException $e) {
      throw $e;
    }
    // Those values are the validated values, produced by the form.
    // with all Form API processing.
    $values = $form_state->getValues();

    // We clean the values from form API keys.
    if (isset($values['form_build_id'])) {
      unset($values['form_build_id'], $values['form_token'], $values['form_id']);
    }

    return $values;
  }

  /**
   * Render an error message in the display builder.
   *
   * @param string $display_builder_instance_id
   *   The builder ID.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The error message.
   * @param array $debug
   *   The debug code related to the error.
   *
   * @return array
   *   A renderable array.
   */
  private function responseMessageError(
    string $display_builder_instance_id,
    string|TranslatableMarkup $message,
    array $debug,
  ): array {
    // Reduce verbosity.
    unset($debug['request']['ajax_page_state']['libraries']);

    $instance = $debug['instance'] ?? NULL;

    if ($instance) {
      $tree = new SourceTree($debug['instance']->getCurrentState());
      unset($debug['instance']);
      $debug['tree'] = $tree->getNormalizedStructure()['structure'];
    }

    $this->getLogger('display_builder')->error('@message <pre>@debug</pre>', [
      '@message' => $message,
      '@debug' => \print_r($debug, TRUE),
    ]);

    $message = new TranslatableMarkup('An error occurred, refresh the current page to continue from a valid state.<br>Check logs for more details.');

    return $this->buildError($display_builder_instance_id, $message, TRUE);
  }

}
