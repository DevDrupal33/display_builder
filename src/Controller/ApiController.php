<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Render\BareHtmlPageRenderer;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\HtmlResponseAttachmentsProcessor;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\Plugin\display_builder\Island\InstanceFormPanel;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Display builder routes.
 */
class ApiController extends ApiControllerBase implements ApiControllerInterface {

  use RenderableBuilderTrait;

  /**
   * The bare html page renderer.
   */
  private BareHtmlPageRenderer $bareHtmlPageRenderer;

  public function __construct(
    EventDispatcherInterface $eventDispatcher,
    MemoryCacheInterface $memoryCache,
    RendererInterface $renderer,
    TimeInterface $time,
    #[Autowire(service: 'tempstore.shared')] SharedTempStoreFactory $sharedTempStoreFactory,
    SessionInterface $session,
    private IslandPluginManagerInterface $islandPluginManager,
    #[Autowire(service: 'html_response.attachments_processor')] private HtmlResponseAttachmentsProcessor $htmlResponseAttachmentsProcessor,
  ) {
    parent::__construct($eventDispatcher, $memoryCache, $renderer, $time, $sharedTempStoreFactory, $session);
    $this->bareHtmlPageRenderer = new BareHtmlPageRenderer($this->renderer, $this->htmlResponseAttachmentsProcessor);
  }

  /**
   * {@inheritdoc}
   */
  public function attachToRoot(Request $request, InstanceInterface $builder): HtmlResponse {
    $position = (int) $request->request->get('position', 0);

    if ($request->request->has('preset_id')) {
      $preset_id = (string) $request->request->get('preset_id');

      return $this->attachPresetToRoot($builder, $preset_id, $position);
    }

    $is_move = FALSE;

    if ($request->request->has('node_id')) {
      $node_id = (string) $request->request->get('node_id');

      if (!$builder->moveToRoot($node_id, $position)) {
        $message = $this->t('[attachToRoot] moveToRoot failed with invalid data');

        return $this->responseMessageError((string) $builder->id(), $message, $request->request->all());
      }

      $is_move = TRUE;
    }
    elseif ($request->request->has('source_id')) {
      $source_id = (string) $request->request->get('source_id');
      $data = $request->request->has('source') ? \json_decode((string) $request->request->get('source'), TRUE) : [];
      $node_id = $builder->attachToRoot($position, $source_id, $data);
    }
    else {
      $message = '[attachToRoot] Missing content (source_id, node_id or preset_id)';

      return $this->responseMessageError((string) $builder->id(), $message, $request->request->all());
    }
    $builder->save();

    $this->builder = $builder;
    // Let's refresh when we add new source to get the placeholder replacement.
    $this->islandId = $is_move ? (string) $request->query->get('from', NULL) : NULL;

    return $this->dispatchDisplayBuilderEvent(
      $is_move ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $node_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function attachToSlot(Request $request, InstanceInterface $builder, string $node_id, string $slot): HtmlResponse {
    $parent_id = $node_id;
    $position = (int) $request->request->get('position', 0);

    if ($request->request->has('preset_id')) {
      $preset_id = (string) $request->request->get('preset_id');

      return $this->attachPresetToSlot($builder, $preset_id, $parent_id, $slot, $position);
    }

    $is_move = FALSE;

    // First, we update the data state.
    if ($request->request->has('node_id')) {
      $node_id = (string) $request->request->get('node_id');

      if (!$builder->moveToSlot($node_id, $parent_id, $slot, $position)) {
        $message = $this->t('[attachToRoot] moveToRoot failed with invalid data');

        return $this->responseMessageError((string) $builder->id(), $message, $request->request->all());
      }

      $is_move = TRUE;
    }
    elseif ($request->request->has('source_id')) {
      $source_id = (string) $request->request->get('source_id');
      $data = $request->request->has('source') ? \json_decode((string) $request->request->get('source'), TRUE) : [];
      $node_id = $builder->attachToSlot($parent_id, $slot, $position, $source_id, $data);
    }
    else {
      $message = $this->t('[attachToSlot] Missing content (component_id, block_id or node_id)');
      $debug = [
        'node_id' => $node_id,
        'slot' => $slot,
        'request' => $request->request->all(),
      ];

      return $this->responseMessageError((string) $builder->id(), $message, $debug);
    }
    $builder->save();

    $this->builder = $builder;
    // Let's refresh when we add new source to get the placeholder replacement.
    $this->islandId = $is_move ? (string) $request->query->get('from', NULL) : NULL;

    return $this->dispatchDisplayBuilderEvent(
      $is_move ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $node_id,
      $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function get(Request $request, InstanceInterface $builder, string $node_id): array {
    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEventWithRenderApi(
      DisplayBuilderEvents::ON_ACTIVE,
      $builder->get($node_id),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @todo factorize with thirdPartySettingsUpdate.
   */
  public function update(Request $request, InstanceInterface $builder, string $node_id): array {
    $this->builder = $builder;
    $body = $request->getPayload()->all();

    if (!isset($body['form_id'])) {
      // @todo log an error.
      return [];
    }

    // Load the instance to properly alter the form data into config data.
    $instance = $builder->get($node_id);

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
        'island_id' => 'instance_form',
        'builder_id' => (string) $builder->id(),
        'instance' => $instance,
      ],
      $builder->getContexts(),
    ]);
    // The body received corresponds to raw form values.
    // We need to set them in the form state to properly
    // take them into account.
    $form_state->setValues($body);

    $formClass = InstanceFormPanel::getFormClass();
    $values = $this->validateIslandForm($formClass, $form_state);
    $data = [
      'source' => $values,
    ];

    if (isset($instance['source']['component']['slots'], $data['source']['component'])
      && ($data['source']['component']['component_id'] === $instance['source']['component']['component_id'])) {
      // We keep the slots.
      $data['source']['component']['slots'] = $instance['source']['component']['slots'];
    }

    $builder->setSource($node_id, $instance['source_id'], $data['source']);
    $builder->save();

    $this->builder = $builder;
    $this->islandId = (string) $request->query->get('from', NULL);

    return $this->dispatchDisplayBuilderEventWithRenderApi(
      DisplayBuilderEvents::ON_UPDATE,
      NULL,
      $node_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function thirdPartySettingsUpdate(Request $request, InstanceInterface $builder, string $node_id, string $island_id): HtmlResponse {
    $body = $request->getPayload()->all();

    if (!isset($body['form_id'])) {
      $message = $this->t('[thirdPartySettingsUpdate] Missing payload!');

      return $this->responseMessageError((string) $builder->id(), $message, $body);
    }

    $islandDefinition = $this->islandPluginManager->getDefinition($island_id);
    // Load the instance to properly alter the form data into config data.
    $instance = $builder->get($node_id);
    unset($body['form_build_id'], $body['form_token'], $body['form_id']);

    $form_state = new FormState();
    // Default values are the existing values from the state.
    $form_state->addBuildInfo('args', [
      [
        'island_id' => $island_id,
        'builder_id' => (string) $builder->id(),
        'instance' => $instance,
      ],
      [],
    ]);
    // The body received corresponds to raw form values.
    // We need to set them in the form state to properly
    // take them into account.
    $form_state->setValues($body);

    $formClass = ($islandDefinition['class'])::getFormClass();
    $values = $this->validateIslandForm($formClass, $form_state);
    // We update the state with the new data.
    $builder->setThirdPartySettings($node_id, $island_id, $values);
    $builder->save();

    $this->builder = $builder;
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
  public function paste(Request $request, InstanceInterface $builder, string $node_id, string $parent_id, string $slot_id, string $slot_position): HtmlResponse {
    $this->builder = $builder;
    $dataToCopy = $builder->get($node_id);

    // Keep flag for move or attach to root.
    $is_paste_root = FALSE;

    if (isset($dataToCopy['source_id'], $dataToCopy['source'])) {
      $source_id = $dataToCopy['source_id'];
      $data = $dataToCopy['source'];

      self::recursiveRefreshNodeId($data);

      // If no parent we are on root.
      // @todo for duplicate and not parent root seems not detected and copy is inside the slot.
      if ($parent_id === '__root__') {
        $is_paste_root = TRUE;
        $builder->attachToRoot(0, $source_id, $data, $dataToCopy['_third_party_settings'] ?? []);
      }
      else {
        $builder->attachToSlot($parent_id, $slot_id, (int) $slot_position, $source_id, $data, $dataToCopy['_third_party_settings'] ?? []);
      }
    }
    $builder->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(
      $is_paste_root ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete(Request $request, InstanceInterface $builder, string $node_id): HtmlResponse {
    $current = $builder->getCurrentState();
    $parent_id = $builder->getParentId($current, $node_id);
    $builder->remove($node_id);
    $builder->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_DELETE,
      NULL,
      $node_id,
      $parent_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function saveAsPreset(Request $request, InstanceInterface $builder, string $node_id): HtmlResponse {
    $label = (string) $this->t('New preset');
    $data = $builder->get($node_id);
    self::cleanNodeId($data);

    $preset_storage = $this->entityTypeManager()->getStorage('pattern_preset');
    $preset = $preset_storage->create([
      'id' => \uniqid(),
      'label' => $request->headers->get('hx-prompt', $label) ?: $label,
      'status' => TRUE,
      'group' => '',
      'description' => '',
      'sources' => $data,
    ]);
    $preset->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_PRESET_SAVE);
  }

  /**
   * {@inheritdoc}
   */
  public function save(Request $request, InstanceInterface $builder): HtmlResponse {
    $builder->setSave($builder->getCurrentState());
    $builder->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_SAVE,
      $builder->getContexts()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function restore(Request $request, InstanceInterface $builder): HtmlResponse {
    $builder->restore();
    $builder->save();

    $this->builder = $builder;

    // @todo on history change is closest to a data change that we need here
    // without any instance id. Perhaps we need a new event?
    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * {@inheritdoc}
   */
  public function revert(Request $request, InstanceInterface $builder): HtmlResponse {
    $instanceInfos = DisplayBuilderItemList::checkInstanceId((string) $builder->id());

    if (isset($instanceInfos['entity_type_id'], $instanceInfos['entity_id'], $instanceInfos['field_name'])) {
      // Do not get the profile entity ID from Instance context because the
      // data stored there is not reliable yet.
      // See: https://www.drupal.org/project/display_builder/issues/3544545
      $entity = $this->entityTypeManager()->getStorage($instanceInfos['entity_type_id'])
        ->load($instanceInfos['entity_id']);

      if ($entity instanceof FieldableEntityInterface) {
        // Remove the saved state as the field values will be deleted.
        $builder->setNewPresent([], 'Revert 1/2: clear overridden data and save');
        $builder->save();
        $builder->setSave($builder->getCurrentState());

        // Clear field value.
        $field = $entity->get($instanceInfos['field_name']);
        $field->setValue(NULL);
        $entity->save();

        // Repopulate the Instance entity from the entity view display config.
        $data = $builder->toArray();

        if (isset($data['contexts']['view_mode'])
          && $data['contexts']['view_mode'] instanceof ContextInterface
        ) {
          $viewMode = $data['contexts']['view_mode']->getContextValue();
          $display_id = "{$instanceInfos['entity_type_id']}.{$entity->bundle()}.{$viewMode}";

          /** @var \Drupal\display_builder\DisplayBuildableInterface|null $display */
          $display = $this->entityTypeManager()->getStorage('entity_view_display')
            ->load($display_id);

          $sources = $display->getSources();
          $builder->setNewPresent($sources, 'Revert 2/2: retrieve existing data from config');
          $builder->save();
        }
      }
    }

    $this->builder = $builder;

    // @todo on history change is closest to a data change that we need here
    // without any instance id. Perhaps we need a new event?
    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * {@inheritdoc}
   */
  public function undo(Request $request, InstanceInterface $builder): HtmlResponse {
    $builder->undo();
    $builder->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * {@inheritdoc}
   */
  public function redo(Request $request, InstanceInterface $builder): HtmlResponse {
    $builder->redo();
    $builder->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(Request $request, InstanceInterface $builder): HtmlResponse {
    $builder->clear();
    $builder->save();

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_HISTORY_CHANGE);
  }

  /**
   * Attach a pattern preset to root.
   *
   * Presets are "resolved" after attachment, so they are never moved around.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $preset_id
   *   Pattern preset ID.
   * @param int $position
   *   Position.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  protected function attachPresetToRoot(InstanceInterface $builder, string $preset_id, int $position): HtmlResponse {
    $presetStorage = $this->entityTypeManager()->getStorage('pattern_preset');

    /** @var \Drupal\display_builder\PatternPresetInterface $preset */
    $preset = $presetStorage->load($preset_id);
    $data = $preset->getSources();

    if (!isset($data['source_id']) || !isset($data['source'])) {
      $message = $this->t('[attachToRoot] Missing preset source_id data');

      return $this->responseMessageError((string) $builder->id(), $message, $data);
    }
    $node_id = $builder->attachToRoot($position, $data['source_id'], $data['source']);

    foreach ($data['_third_party_settings'] ?? [] as $provider => $settings) {
      $builder->setThirdPartySettings($node_id, $provider, $settings ?? []);
    }
    $this->builder = $builder;

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
   * @param \Drupal\display_builder\InstanceInterface $builder
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
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  protected function attachPresetToSlot(InstanceInterface $builder, string $preset_id, string $parent_id, string $slot, int $position): HtmlResponse {
    $presetStorage = $this->entityTypeManager()->getStorage('pattern_preset');

    /** @var \Drupal\display_builder\PatternPresetInterface $preset */
    $preset = $presetStorage->load($preset_id);
    $data = $preset->getSources();

    if (!isset($data['source_id']) || !isset($data['source'])) {
      $message = $this->t('[attachToSlot] Missing preset source_id data');

      return $this->responseMessageError((string) $builder->id(), $message, $data);
    }
    $node_id = $builder->attachToSlot($parent_id, $slot, $position, $data['source_id'], $data['source']);

    foreach ($data['_third_party_settings'] ?? [] as $provider => $settings) {
      $builder->setThirdPartySettings($node_id, $provider, $settings ?? []);
    }

    $this->builder = $builder;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      NULL,
      $node_id,
    );
  }

  /**
   * Dispatches a display builder event.
   *
   * @param string $event_id
   *   The event ID.
   * @param array|null $data
   *   The data.
   * @param string|null $node_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  protected function dispatchDisplayBuilderEvent(
    string $event_id,
    ?array $data = NULL,
    ?string $node_id = NULL,
    ?string $parent_id = NULL,
  ): HtmlResponse {
    $event = $this->createEventWithEnabledIsland($event_id, $data, $node_id, $parent_id);
    $this->saveSseData($event_id);

    return $this->bareHtmlPageRenderer->renderBarePage($event->getResult(), '', 'markup');
  }

  /**
   * Dispatches a display builder event with render API.
   *
   * @param string $event_id
   *   The event ID.
   * @param array|null $data
   *   The data.
   * @param string|null $node_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return array
   *   The render array result of the event.
   */
  protected function dispatchDisplayBuilderEventWithRenderApi(
    string $event_id,
    ?array $data = NULL,
    ?string $node_id = NULL,
    ?string $parent_id = NULL,
  ): array {
    $event = $this->createEventWithEnabledIsland($event_id, $data, $node_id, $parent_id);
    $this->saveSseData($event_id);

    return $event->getResult();
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
  protected function validateIslandForm(string $formClass, FormStateInterface $form_state): array {
    /** @var \Drupal\Core\Form\FormBuilder $formBuilder */
    $formBuilder = $this->formBuilder();

    try {
      $triggering_element = $form_state->getTriggeringElement();

      if (!$triggering_element && !isset($form_state->getValues()['_triggering_element_name'])) {
        // We set a fake triggering element to avoid form API error.
        $form_state->setTriggeringElement(['#type' => 'submit', '#value' => (string) $this->t('Submit')]);
      }
      $form = $formBuilder->buildForm($formClass, $form_state);
      $formBuilder->validateForm($formClass, $form, $form_state);
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
   * @param string $builder_id
   *   The builder ID.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The error message.
   * @param array $debug
   *   The debug code.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The response with the component.
   */
  private function responseMessageError(
    string $builder_id,
    string|TranslatableMarkup $message,
    array $debug,
  ): HtmlResponse {
    $build = $this->buildError($builder_id, $message, \print_r($debug, TRUE), NULL, TRUE);

    $html = $this->renderer->renderInIsolation($build);
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
  }

  /**
   * Recursively regenerate the _node_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function recursiveRefreshNodeId(array &$array): void {
    if (isset($array['_node_id'])) {
      $array['_node_id'] = \uniqid();
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::recursiveRefreshNodeId($value);
      }
    }
  }

  /**
   * Recursively regenerate the _node_id key.
   *
   * @param array $array
   *   The array reference.
   *
   * @todo set as utils because clone in ExportForm.php?
   */
  private static function cleanNodeId(array &$array): void {
    unset($array['_node_id']);

    foreach ($array as $key => &$value) {
      if (\is_array($value)) {
        self::cleanNodeId($value);

        if (isset($value['source_id'], $value['source']['value']) && empty($value['source']['value'])) {
          unset($array[$key]);
        }
      }
    }
  }

}
