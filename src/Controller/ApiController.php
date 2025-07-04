<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BareHtmlPageRenderer;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\HtmlResponseAttachmentsProcessor;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\Plugin\display_builder\Island\InstanceFormPanel;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Display builder routes.
 */
class ApiController extends ControllerBase implements ApiControllerInterface, ContainerInjectionInterface {

  use RenderableBuilderTrait;

  /**
   * The bare html page renderer.
   */
  private BareHtmlPageRenderer $bareHtmlPageRenderer;

  public function __construct(
    private IslandPluginManagerInterface $islandPluginManager,
    private StateManagerInterface $stateManager,
    private EventDispatcherInterface $eventDispatcher,
    #[Autowire(service: 'html_response.attachments_processor')]
    private HtmlResponseAttachmentsProcessor $htmlResponseAttachmentsProcessor,
    private RendererInterface $renderer,
    private MemoryCacheInterface $memoryCache,
  ) {
    $this->bareHtmlPageRenderer = new BareHtmlPageRenderer($this->renderer, $this->htmlResponseAttachmentsProcessor);
  }

  /**
   * {@inheritdoc}
   */
  public function attachToRoot(Request $request, string $builder_id): HtmlResponse {
    $position = (int) $request->request->get('position', 0);
    $is_move = FALSE;

    if ($request->request->has('instance_id')) {
      $instance_id = (string) $request->request->get('instance_id');
      $this->stateManager->moveToRoot($builder_id, $instance_id, $position);
      $is_move = TRUE;
    }
    elseif ($request->request->has('source_id')) {
      $source_id = (string) $request->request->get('source_id');
      $data = $request->request->has('source') ? json_decode((string) $request->request->get('source'), TRUE) : [];
      $instance_id = $this->stateManager->attachSourceToRoot($builder_id, $position, $source_id, $data);
    }
    elseif ($request->request->has('preset_id')) {
      $preset_id = (string) $request->request->get('preset_id');
      $presetStorage = $this->entityTypeManager()->getStorage('pattern_preset');

      /** @var \Drupal\display_builder\PatternPresetInterface $preset */
      $preset = $presetStorage->load($preset_id);
      $data = $preset->getSources();
      if (!isset($data['source_id']) || !isset($data['source'])) {
        $message = $this->t('[attachToRoot] Missing preset source_id data');
        return $this->responseMessageError($builder_id, $message, $data);
      }
      $instance_id = $this->stateManager->attachSourceToRoot($builder_id, $position, $data['source_id'], $data['source']);
    }
    else {
      $message = \sprintf('[attachToRoot] Missing content (source_id, instance_id or preset_id)');
      return $this->responseMessageError($builder_id, $message, $request->request->all());
    }

    return $this->dispatchDisplayBuilderEvent(
      $is_move ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      $builder_id,
      NULL,
      $instance_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function attachToSlot(Request $request, string $builder_id, string $instance_id, string $slot): HtmlResponse {
    $parent_id = $instance_id;
    $position = (int) $request->request->get('position', 0);
    $is_move = FALSE;

    // First, we update the data state.
    if ($request->request->has('instance_id')) {
      $instance_id = (string) $request->request->get('instance_id');
      $this->stateManager->moveToSlot($builder_id, $instance_id, $parent_id, $slot, $position);
      $is_move = TRUE;
    }
    elseif ($request->request->has('source_id')) {
      $source_id = (string) $request->request->get('source_id');
      $data = $request->request->has('source') ? json_decode((string) $request->request->get('source'), TRUE) : [];
      $instance_id = $this->stateManager->attachSourceToSlot($builder_id, $parent_id, $slot, $position, $source_id, $data);
    }
    elseif ($request->request->has('preset_id')) {
      $preset_id = (string) $request->request->get('preset_id');
      $presetStorage = $this->entityTypeManager()->getStorage('pattern_preset');

      /** @var \Drupal\display_builder\PatternPresetInterface $preset */
      $preset = $presetStorage->load($preset_id);
      $data = $preset->getSources();
      if (!isset($data['source_id']) || !isset($data['source'])) {
        $message = $this->t('[attachToSlot] Missing preset source_id data');
        return $this->responseMessageError($builder_id, $message, $data);
      }
      $instance_id = $this->stateManager->attachSourceToSlot($builder_id, $parent_id, $slot, $position, $data['source_id'], $data['source']);
    }
    else {
      $message = $this->t('[attachToSlot] Missing content (component_id, block_id or instance_id)');
      $debug = [
        'instance_id' => $instance_id,
        'slot' => $slot,
        'request' => $request->request->all(),
      ];
      return $this->responseMessageError($builder_id, $message, $debug);
    }

    return $this->dispatchDisplayBuilderEvent(
      $is_move ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      $builder_id,
      NULL,
      $instance_id,
      $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(Request $request, string $builder_id, string $instance_id): array {
    $data = $this->stateManager->get($builder_id, $instance_id);

    return $this->dispatchDisplayBuilderEventWithRenderApi(
      DisplayBuilderEvents::ON_ACTIVE,
      $builder_id,
      $data
    );
  }

  /**
   * {@inheritdoc}
   *
   * @todo factorize with thirdPartySettingsUpdate.
   */
  public function updateInstance(Request $request, string $builder_id, string $instance_id): array {
    $body = $request->getPayload()->all();

    if (!isset($body['form_id'])) {
      // $message = $this->t('[updateInstance] Missing payload form_id!');
      // return $this->responseMessageError($builder_id, $message, $body);
      return [];
    }

    // Load the instance to properly alter the form data into config data.
    $instance = $this->stateManager->get($builder_id, $instance_id);

    if (isset($body['source']['form_build_id'])) {
      unset($body['source']['form_build_id'], $body['source']['form_token'], $body['source']['form_id']);
    }

    if (isset($body['form_build_id'])) {
      unset($body['form_build_id'], $body['form_token'], $body['form_id']);
    }
    $form_state = new FormState();
    // Default values are the existing values from the state.
    $form_state->addBuildInfo('args', [$instance, [], $this->stateManager->getContexts($builder_id)]);
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

    $this->stateManager->setSource($builder_id, $instance_id, $instance['source_id'], $data['source']);

    return $this->dispatchDisplayBuilderEventWithRenderApi(
      DisplayBuilderEvents::ON_UPDATE,
      $builder_id,
      NULL,
      $instance_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function thirdPartySettingsUpdate(Request $request, string $builder_id, string $instance_id, string $island_id): HtmlResponse {
    $body = $request->getPayload()->all();

    if (!isset($body['form_id'])) {
      $message = $this->t('[thirdPartySettingsUpdate] Missing payload');
      return $this->responseMessageError($builder_id, $message, $body);
    }

    $islandDefinition = $this->islandPluginManager->getDefinition($island_id);
    // Load the instance to properly alter the form data into config data.
    $instance = $this->stateManager->get($builder_id, $instance_id);
    unset($body['form_build_id'], $body['form_token'], $body['form_id']);

    $form_state = new FormState();
    // Default values are the existing values from the state.
    $form_state->addBuildInfo('args', [$instance['_third_party_settings'][$island_id] ?? [], []]);
    // The body received corresponds to raw form values.
    // We need to set them in the form state to properly
    // take them into account.
    $form_state->setValues($body);

    $formClass = ($islandDefinition['class'])::getFormClass();
    $values = $this->validateIslandForm($formClass, $form_state);
    // We update the state with the new data.
    $this->stateManager->setThirdPartySettings($builder_id, $instance_id, $island_id, $values);

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_UPDATE,
      $builder_id,
      NULL,
      $instance_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function pasteInstance(Request $request, string $builder_id, string $instance_id, string $parent_id, string $slot_id, string $slot_position): HtmlResponse {
    $dataToCopy = $this->stateManager->get($builder_id, $instance_id);
    // Keep flag for move or attach to root.
    $is_paste_root = FALSE;
    if (isset($dataToCopy['source_id']) && isset($dataToCopy['source'])) {
      $source_id = $dataToCopy['source_id'];
      $data = $dataToCopy['source'];
      self::recursiveRefreshInstanceId($data);
      // If no parent we are on root.
      if ('__root__' === $parent_id) {
        $is_paste_root = TRUE;
        $this->stateManager->attachSourceToRoot($builder_id, 0, $source_id, $data);
      }
      else {
        $this->stateManager->attachSourceToSlot($builder_id, $parent_id, $slot_id, (int) $slot_position, $source_id, $data);
      }
    }

    return $this->dispatchDisplayBuilderEvent(
      $is_paste_root ? DisplayBuilderEvents::ON_MOVE : DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
      $builder_id,
      NULL,
      $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deleteInstance(Request $request, string $builder_id, string $instance_id): HtmlResponse {
    $current = $this->stateManager->getCurrentState($builder_id);
    $parent_id = $this->stateManager->getParentId($builder_id, $current, $instance_id);
    $this->stateManager->remove($builder_id, $instance_id);

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_DELETE,
      $builder_id,
      NULL,
      $instance_id,
      $parent_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function saveInstanceAsPreset(Request $request, string $builder_id, string $instance_id): HtmlResponse {
    $label = $this->t('New preset');
    foreach ($request->headers as $key => $value) {
      if ($key === 'hx-prompt' && !empty($value[0])) {
        $label = $value[0];
        break;
      }
    }
    // phpcs:ignore
    $theme = \Drupal::configFactory()->get('system.theme')->get('default');
    $data = $this->stateManager->get($builder_id, $instance_id);
    self::cleanInstanceId($data);

    $preset_storage = $this->entityTypeManager()->getStorage('pattern_preset');
    $preset = $preset_storage->create([
      'id' => uniqid(),
      'label' => (string) $label,
      'theme' => $theme,
      'status' => TRUE,
      'group' => '',
      'description' => '',
      'sources' => Yaml::encode($data),
    ]);
    $preset->save();

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_PRESET_SAVE,
      $builder_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(Request $request, string $builder_id): HtmlResponse {
    $this->stateManager->setSave($builder_id, $this->stateManager->getCurrentState($builder_id));

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_SAVE,
      $builder_id,
      $this->stateManager->getContexts($builder_id)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function restore(Request $request, string $builder_id): HtmlResponse {
    $this->stateManager->restore($builder_id);

    // @todo on history change is closest to a data change that we need here
    // without any instance id. Perhaps we need a new event?
    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_HISTORY_CHANGE,
      $builder_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function undo(Request $request, string $builder_id): HtmlResponse {
    $this->stateManager->undo($builder_id);

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_HISTORY_CHANGE,
      $builder_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function redo(Request $request, string $builder_id): HtmlResponse {
    $this->stateManager->redo($builder_id);

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_HISTORY_CHANGE,
      $builder_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function clear(Request $request, string $builder_id): HtmlResponse {
    $this->stateManager->clear($builder_id);

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_HISTORY_CHANGE,
      $builder_id,
    );
  }

  /**
   * Dispatches a display builder event.
   *
   * @param string $event_id
   *   The event ID.
   * @param string $builder_id
   *   The builder ID.
   * @param array|null $data
   *   The data.
   * @param string|null $instance_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  protected function dispatchDisplayBuilderEvent(
    string $event_id,
    string $builder_id,
    ?array $data = NULL,
    ?string $instance_id = NULL,
    ?string $parent_id = NULL,
  ): HtmlResponse {
    $result = $this->createEventWithEnabledIsland($event_id, $builder_id, $data, $instance_id, $parent_id);

    return $this->bareHtmlPageRenderer->renderBarePage($result, '', 'markup');
  }

  /**
   * Dispatches a display builder event with HTML only response.
   *
   * @param string $event_id
   *   The event ID.
   * @param string $builder_id
   *   The builder ID.
   * @param array|null $data
   *   (Optional) The data.
   * @param string|null $instance_id
   *   (Optional) instance ID.
   * @param string|null $parent_id
   *   (Optional) parent ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   *
   * @phpcs:disable DrupalPractice.Objects.UnusedPrivateMethod.UnusedMethod
   */
  private function dispatchDisplayBuilderEventRaw(
    string $event_id,
    string $builder_id,
    ?array $data = NULL,
    ?string $instance_id = NULL,
    ?string $parent_id = NULL,
  ): HtmlResponse {
    $result = $this->createEventWithEnabledIsland($event_id, $builder_id, $data, $instance_id, $parent_id);

    $html = $this->renderer->renderInIsolation($result);
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
  }

  /**
   * Dispatches a display builder event with render API.
   *
   * @param string $event_id
   *   The event ID.
   * @param string $builder_id
   *   The builder ID.
   * @param array|null $data
   *   The data.
   * @param string|null $instance_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return array
   *   The render array result of the event.
   */
  protected function dispatchDisplayBuilderEventWithRenderApi(
    string $event_id,
    string $builder_id,
    ?array $data = NULL,
    ?string $instance_id = NULL,
    ?string $parent_id = NULL,
  ): array {
    return $this->createEventWithEnabledIsland($event_id, $builder_id, $data, $instance_id, $parent_id);
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
    $build = $this->buildError($builder_id, $message, print_r($debug, TRUE), NULL, TRUE);

    $html = $this->renderer->renderInIsolation($build);
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
  }

  /**
   * Validates a island form.
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
    $formBuilder = $this->formBuilder();

    try {
      /** @var \Drupal\Core\Form\FormBuilder $formBuilder */
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
   * Creates a display builder event with enabled islands only.
   *
   * Use a cache to avoid loading all the builder configuration.
   *
   * @param string $event_id
   *   The event ID.
   * @param string $builder_id
   *   The builder ID.
   * @param array|null $data
   *   The data.
   * @param string|null $instance_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return array
   *   The event result.
   */
  private function createEventWithEnabledIsland($event_id, $builder_id, $data, $instance_id, $parent_id): array {
    $key = \sprintf('db_%s_island_enable', $builder_id);
    $island_enabled = $this->memoryCache->get($key);

    if ($island_enabled === FALSE) {
      $builder_config_id = $this->stateManager->getEntityConfigId($builder_id);
      $builder_config = $this->entityTypeManager()->getStorage('display_builder')->load($builder_config_id);
      /** @var \Drupal\display_builder\DisplayBuilderInterface $builder_config */
      $island_enabled = $builder_config->getIslandEnabled();
      $this->memoryCache->set($key, $island_enabled);
    }
    else {
      $island_enabled = $island_enabled->data;
    }

    $event = new DisplayBuilderEvent($builder_id, $island_enabled, $data, $instance_id, $parent_id);
    $this->eventDispatcher->dispatch($event, $event_id);

    return $event->getResult();
  }

  /**
   * Recursively regenerate the _instance_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function recursiveRefreshInstanceId(array &$array): void {
    if (isset($array['_instance_id'])) {
      $array['_instance_id'] = uniqid();
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::recursiveRefreshInstanceId($value);
      }
    }
  }

  /**
   * Recursively regenerate the _instance_id key.
   *
   * @param array $array
   *   The array reference.
   *
   * @todo set as utils because clone in ExportForm.php?
   */
  private static function cleanInstanceId(array &$array): void {
    unset($array['_instance_id']);
    foreach ($array as $key => &$value) {
      if (\is_array($value)) {
        self::cleanInstanceId($value);
        if (isset($value['source_id']) && isset($value['source']['value']) && empty($value['source']['value'])) {
          unset($array[$key]);
        }
      }
    }
  }

}
