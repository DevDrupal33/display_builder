<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ConfigurablePluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Base class for display_buildable plugins.
 */
abstract class DisplayBuildablePluginBase extends ConfigurablePluginBase implements DisplayBuildableInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The plugin manager, to build one sibling plugin per display listed.
   *
   * On the base rather than on each implementation: ::collectDisplays() and
   * ::collectInstances() are both interface methods, and both answer by
   * building a plugin per row, so every implementation needs this by contract.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * A tiny hint to remember where the initial data comes from.
   *
   * See ::getInitialSources() and ::getInitializationMessage().
   */
  protected string $initialDataSource = '';

  /**
   * {@inheritdoc}
   *
   * Services are assigned after the constructor has run, so a subclass needing
   * more of them overrides this method and assigns them on the parent result:
   *
   * @code
   * public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
   *   $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
   *   $instance->myService = $container->get('my.service');
   *
   *   return $instance;
   * }
   *
   * @endcode
   *
   * The ordering is why a constructor must never resolve the display it wraps:
   * nothing is injected yet at that point. Keep the identifier the plugin was
   * built with in ::$configuration and load lazily from an accessor instead.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    /** @var array $definition */
    $definition = $this->pluginDefinition;

    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $definition['label'];
  }

  /**
   * {@inheritdoc}
   *
   * A plugin with no underlying config or content entity to name has nothing
   * specific to say, so it says nothing rather than repeating its kind.
   */
  public function getDisplayLabel(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Opting in is deliberate. A buildable that has not thought about the
   * read-only contract simply does not appear in the navigation panel, which
   * is the safe direction: the alternative default would be to derive the list
   * from ::collectInstances(), and that one writes to storage.
   */
  public function collectDisplays(array $options = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function collectDisplaysBound(): ?TranslatableMarkup {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionUrl(): ?Url {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddUrl(): ?Url {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->getInstance();

    if (!$instance) {
      $instance = $this->createDisplayBuilderInstance();
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(): ?InstanceInterface {
    if ($this->getInstanceId() === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface|null $instance */
    $instance = $storage->load($this->getInstanceId());

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    // Plugins will override this method.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    // Plugins will override this method.
    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewPagePath(): ?string {
    // Most displays are fragments that can appear on any number of pages, so
    // they have no single page to preview against.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildInstanceForm(bool $mandatory = TRUE, ?TranslatableMarkup $title = NULL, bool $link = TRUE): array {
    $profile = $this->getProfile();
    $allowed = $this->isAllowed();

    if (!$allowed && !$profile) {
      return [
        self::PROFILE_PROPERTY => [
          '#markup' => $this->t('You are not allowed to use Display Builder.'),
        ],
      ];
    }

    if (!$allowed && $profile) {
      return [
        self::PROFILE_PROPERTY => $this->buildDisabledSelect($profile),
      ];
    }

    $form = [
      self::PROFILE_PROPERTY => $this->buildSelect($profile, $mandatory, $title),
    ];

    // Add the builder link to edit.
    if ($this->getInstanceId() && $profile && $link) {
      $form['link'] = $this->buildLink();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedProfiles(?AccountInterface $account = NULL): array {
    $account ??= $this->currentUser;
    $options = [];
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');
    $entity_ids = $storage->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    /** @var \Drupal\display_builder\Entity\ProfileInterface[] $display_builders */
    $display_builders = $storage->loadMultiple($entity_ids);

    // Entity query doesn't execute access control handlers for config
    // entities. So we need to do an extra check here.
    foreach ($display_builders as $entity_id => $entity) {
      // We don't execute $entity->access() to not catch admin permission
      // 'administer display builder profile'.
      // @see ProfileAccessControlHandler.
      // Administrators can use any profile, but it is better to only propose
      // them the ones related to their permissions.
      if ($account->hasPermission($entity->getPermissionName())) {
        $options[$entity_id] = $entity->label();
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(?AccountInterface $account = NULL): bool {
    $options = $this->getAllowedProfiles($account);

    if (empty($options)) {
      return FALSE;
    }
    $profile = $this->getProfile();

    if (!$profile) {
      return TRUE;
    }

    return isset($options[(string) $profile->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

  /**
   * {@inheritdoc}
   *
   * A buildable that has not thought about the question is assumed to be a
   * fragment, previewed inside the site's normal page wrapper.
   */
  public function previewWithChrome(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCardinality(): int {
    // By default, buildables allow an unlimited number of sources at the
    // root level.
    return self::CARDINALITY_UNLIMITED;
  }

  /**
   * Get the instance ID prefix this plugin's IDs start with.
   *
   * Static, and staying that way until there is a manager to ask: every
   * ::checkInstanceId() needs the prefix to recognize an ID it has not built a
   * plugin from yet, so there is no $this to read a definition off. Protected,
   * and not on DisplayBuildableInterface, because every caller of the *method*
   * is a plugin asking about itself; anything outside builds the plugin and
   * asks it for ::getInstanceId().
   *
   * The prefix itself is not private: it is a plain key on the plugin
   * definition, and three callers scan definitions for it directly rather than
   * building a plugin per candidate. Those three hand-roll the same loop, and
   * folding it into DisplayBuildablePluginManager would let this method read
   * the definition instead of the attribute.
   *
   * @see \Drupal\display_builder\InstanceAccessControlHandler
   * @see \Drupal\display_builder\Event\PageVariantSubscriber
   * @see \Drupal\display_builder\Plugin\display_builder\Island\BackButton
   *
   * Memoized per class because the attribute is the source of truth but
   * reading it is not free: ReflectionAttribute::newInstance() rebuilds the
   * whole attribute, label included, and the label is a TranslatableMarkup.
   * The value is fixed at compile time, and access handlers call this on
   * routes, so paying that once per class per request is the point.
   *
   * @return string
   *   The instance prefix, from the plugin attribute.
   */
  protected static function getPrefix(): string {
    static $prefixes = [];

    return $prefixes[static::class] ??= (new \ReflectionClass(static::class))
      ->getAttributes(DisplayBuildable::class)[0]
      ->newInstance()
      ->instance_prefix;
  }

  /**
   * Whether a route is registered, asked once per route name per request.
   *
   * A listing probes this once per row, and the route provider memoizes the
   * routes it finds but not the ones it does not: a missing name costs two
   * queries every time it is asked. Uninstalling field_ui or views_ui is what
   * makes the answer NULL, and those are exactly the sites that would pay the
   * probe on every row of a listing that then drops them all.
   *
   * Static because its callers compose URLs from an instance ID alone, which
   * is a static question in every buildable.
   *
   * @param string $route_name
   *   The route to look for.
   *
   * @return bool
   *   TRUE when the route is registered.
   */
  protected static function routeExists(string $route_name): bool {
    static $known = [];

    if (isset($known[$route_name])) {
      return $known[$route_name];
    }

    try {
      \Drupal::service('router.route_provider')->getRouteByName($route_name);
      $known[$route_name] = TRUE;
    }
    catch (RouteNotFoundException) {
      $known[$route_name] = FALSE;
    }

    return $known[$route_name];
  }

  /**
   * Create a display builder instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function createDisplayBuilderInstance(): EntityInterface {
    $data = $this->getInitialSources();
    $tree = new SourceTree($data);
    $data = $tree->getTree();
    $data = [
      'id' => $this->getInstanceId(),
      'buildable' => [
        'plugin_id' => $this->getPluginId(),
        'configuration' => $this->getConfiguration(),
      ],
      'sources' => $data,
      'hash' => Instance::getUniqId($data),
      'revision_log_message' => $this->getInitializationMessage(),
      'revision_created' => \time(),
      'revision_user' => (int) $this->currentUser->id(),
    ];

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->create($data);

    return $instance;
  }

  /**
   * Compose a display name in the format ::getDisplayLabel() promises.
   *
   * The format is a contract on the interface, not a per-plugin choice, so it
   * lives here rather than in each implementation: three hand-rolled copies of
   * the same sprintf are three chances for the next change to miss one.
   *
   * @param string $subject
   *   What the display belongs to: a bundle, an entity, a view.
   * @param string $display
   *   Which display of it: a view mode, a Views display title.
   *
   * @return string
   *   The composed name.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface::getDisplayLabel()
   */
  protected function composeDisplayLabel(string $subject, string $display): string {
    return \sprintf('%s (%s)', $subject, $display);
  }

  /**
   * Get the message to put in the first log step.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The log message.
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    return $this->t('Initialize display');
  }

  /**
   * Initialize sources for this implementation.
   *
   * @return array
   *   The data.
   */
  protected function getInitialSources(): array {
    return $this->getSources();
  }

  /**
   * Build profile select when user is allowed to select one.
   *
   * @param \Drupal\display_builder\Entity\ProfileInterface|null $profile
   *   Display Builder profile (or not)
   * @param bool $mandatory
   *   (Optional) Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   (Optional) The Select title, default to 'Profile'.
   *
   * @return array
   *   A renderable form array.
   */
  protected function buildSelect(?ProfileInterface $profile, bool $mandatory = TRUE, ?TranslatableMarkup $title = NULL): array {
    $select = [
      '#type' => 'select',
      '#title' => $title ?? $this->t('Profile'),
      '#description' => $this->t('The profile defines the features available in the builder. It can be changed anytime after creation.'),
      '#options' => $this->getAllowedProfiles(),
    ];

    if ($profile) {
      $select['#default_value'] = (string) $profile->id();
    }
    elseif (isset($select['#options']['default']) && $mandatory) {
      $select['#default_value'] = 'default';
    }

    if ($mandatory) {
      $select['#required'] = TRUE;
    }
    else {
      $select['#empty_option'] = $this->t('- Disabled -');
    }

    // Add admin information to link the profiles.
    if ($this->isCurrentUserAllowedToAdministrate()) {
      $select['#description'] = [
        [
          '#markup' => $select['#description'] . '<br>',
        ],
        [
          '#type' => 'link',
          '#title' => $this->t('Add and configure display builder profiles'),
          '#url' => Url::fromRoute('entity.display_builder_profile.collection'),
          '#suffix' => '.',
        ],
      ];
    }

    return $select;
  }

  /**
   * Build link to Display Builder.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildLink(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'class' => ['form-item__description'],
      ],
      'content' => [
        '#type' => 'link',
        '#title' => $this->t('Build the display'),
        '#url' => $this->getBuilderUrl(),
        '#attributes' => [
          'class' => ['button', 'button--small'],
        ],
      ],
    ];
  }

  /**
   * Build disabled profile select when user is not allowed to select one.
   *
   * @param \Drupal\display_builder\Entity\ProfileInterface $profile
   *   Display Builder profile (or not)
   *
   * @return array
   *   A renderable form array.
   */
  protected function buildDisabledSelect(ProfileInterface $profile): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#description' => $this->t('You are not allowed to use Display Builder here.'),
      '#options' => [
        (string) $profile->id() => $profile->label(),
      ],
      '#disabled' => TRUE,
    ];
  }

  /**
   * Is the current user allowed to use administrate display builder profiles?
   *
   * @return bool
   *   Allowed or not.
   */
  protected function isCurrentUserAllowedToAdministrate(): bool {
    return $this->moduleHandler->moduleExists('display_builder_ui') && $this->currentUser->hasPermission('administer display builder profile');
  }

}
