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
    private IslandPluginManagerInterface $islandPluginManager,
  ) {
    parent::__construct($eventDispatcher, $renderer, $time, $sharedTempStoreFactory, $session);
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
    $this->islandId = $is_move ? (string) $request->query->get('from', NULL) : NULL;

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
  public function paste(Request $request, InstanceInterface $display_builder_instance, string $node_id, string $parent_id, string $slot_id, string $slot_position): array {
    $this->builder = $display_builder_instance;
    $dataToCopy = $display_builder_instance->getNode($node_id);

    // Keep flag for move or attach to root.
    $is_paste_root = FALSE;
    $new_node_id = NULL;

    if (isset($dataToCopy['source_id'], $dataToCopy['source'])) {
      $source_id = $dataToCopy['source_id'];
      // Use reference to ensure modifications by recursiveRefreshNodeId
      // persist.
      $data = &$dataToCopy['source'];

      // Refresh nested node_ids.
      self::recursiveRefreshNodeId($data);

      // If no parent we are on root.
      // @todo for duplicate and not parent root seems not detected and copy is inside the slot.
      if ($parent_id === '__root__') {
        $is_paste_root = TRUE;
        $new_node_id = $display_builder_instance->attachToRoot(0, $source_id, $data, $dataToCopy['third_party_settings'] ?? []);
      }
      else {
        $new_node_id = $display_builder_instance->attachToSlot($parent_id, $slot_id, (int) $slot_position, $source_id, $data, $dataToCopy['third_party_settings'] ?? []);
      }
    }
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(
      $is_paste_root ? DisplayBuilderEvents::ON_ATTACH_TO_ROOT : DisplayBuilderEvents::ON_ATTACH_TO_SLOT,
      NULL,
      $new_node_id,
      $is_paste_root ? NULL : $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete(Request $request, InstanceInterface $display_builder_instance, string $node_id): array {
    $parent_id = $display_builder_instance->getParentId($node_id);
    $display_builder_instance->remove($node_id);
    $display_builder_instance->save();
    $this->builder = $display_builder_instance;

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
  public function saveAsPreset(Request $request, InstanceInterface $display_builder_instance, string $node_id): array {
    $label = (string) $this->t('New preset');
    $data = $display_builder_instance->getNode($node_id);
    self::cleanPreset($data);

    $preset_storage = $this->entityTypeManager()->getStorage('pattern_preset');
    $label = $request->headers->get('hx-prompt', $label) ?: $label;
    // In HTTP headers, only ASCII is guaranteed to work but historically,
    // HTTP has allowed header values with the ISO-8859-1 charset.
    $label = \mb_convert_encoding($label, 'UTF-8', 'ISO-8859-1');

    // Build a valid config-entity machine name from the label. Config IDs
    // must match [a-z0-9_] and must not start with a digit.
    $base_id = 'preset_' . \preg_replace('/[^a-z0-9_]+/', '_', \mb_strtolower($label));
    $base_id = \trim($base_id, '_');
    $id = $base_id;
    $suffix = 1;

    while ($preset_storage->load($id) !== NULL) {
      $id = $base_id . '_' . $suffix++;
    }

    $preset = $preset_storage->create([
      'id' => $id,
      'label' => $label,
      'status' => TRUE,
      'description' => '',
      'sources' => $data,
    ]);
    $preset->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_PRESET_SAVE);
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

    $message = new TranslatableMarkup('Error: @error, check logs for more details.', ['@error' => $message]);

    return $this->buildError($display_builder_instance_id, $message, TRUE);
  }

  /**
   * Recursively regenerate the node_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function recursiveRefreshNodeId(array &$array): void {
    if (isset($array['node_id'])) {
      $array['node_id'] = \bin2hex(\random_bytes(8));
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::recursiveRefreshNodeId($value);
      }
    }
  }

  /**
   * Recursively clean the node data for export or preset saving.
   *
   * Unset node_id and remove empty values.
   *
   * @param array $array
   *   The array reference.
   */
  private static function cleanPreset(array &$array): void {
    unset($array['node_id']);

    foreach ($array as $key => &$value) {
      if (\is_array($value)) {
        self::cleanPreset($value);

        // Remove empty values to reduce size and noise in the exported preset.
        if (isset($value['source_id'], $value['source']['value']) && $value['source']['value'] === '') {
          unset($array[$key]);
        }
      }

      if ($key === 'extra' && empty($value)) {
        unset($array[$key]);
      }

      if ($key === 'third_party_settings' && empty($value)) {
        unset($array[$key]);
      }

      if ($key === 'variant_id' && $value === NULL) {
        unset($array[$key]);
      }
    }
  }

}
