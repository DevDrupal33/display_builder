<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Entity\ProfileInterface;

/**
 * Interface for plugins managing a buildable display.
 */
interface DisplayBuildableInterface extends ContainerFactoryPluginInterface, ContextProviderInterface, PluginInspectionInterface {

  // Storage property for the profile config entity ID.
  // This will we used in some schema.yml, careful if you change it.
  public const PROFILE_PROPERTY = 'profile';

  // Storage property for the nestable list of UI Patterns 2 sources.
  // This will we used in some schema.yml, careful if you change it.
  public const SOURCES_PROPERTY = 'sources';

  // Request attribute naming the instance whose unsaved draft state a preview
  // sub-request must render (@see ::getPreviewPagePath()). Deliberately a
  // request attribute and never a query argument: an attribute exists only in
  // memory, for the duration of one sub-request issued from an access-checked
  // admin route, so the previewed page's public URL gains no second rendering
  // mode and no cache entry can ever key on it.
  public const PREVIEW_INSTANCE_ATTRIBUTE = '_display_builder_preview_instance';

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the human name of the specific display being built.
   *
   * ::label() answers "what kind of thing is this?" and is the same for every
   * display of a plugin. This answers "which one?", and is built from the
   * config or content entity the plugin wraps.
   *
   * The format is '<subject> (<display>)', with no separator invented per
   * plugin: 'Article (Teaser)', 'My node title (Full)', 'Frontpage (Page)'.
   * The subject comes first because it is what a list is scanned for.
   * Implementations compose it with
   * DisplayBuildablePluginBase::composeDisplayLabel(), never by hand.
   *
   * @return string|null
   *   The display name, or NULL when the plugin has no specific name to give,
   *   or when the entity it wraps is gone. Callers fall back to their own
   *   naming then, they never print an empty label.
   */
  public function getDisplayLabel(): ?string;

  /**
   * Build form for integration with Display Builder.
   *
   * @param bool $mandatory
   *   (Optional) Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   (Optional) The Select title, default to 'Profile'.
   * @param bool $link
   *   (Optional) Display link to build the display.
   *
   * @return array
   *   A form renderable array.
   */
  public function buildInstanceForm(bool $mandatory = TRUE, ?TranslatableMarkup $title = NULL, bool $link = TRUE): array;

  /**
   * Checks access for an instance for a user account.
   *
   * @param string $instance_id
   *   Instance entity ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\display_builder\InstanceAccessControlHandler
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface;

  /**
   * Check if instance ID can be used with the interface implementation.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return array|null
   *   The parts we checked, extracted from the instance ID string.
   */
  public static function checkInstanceId(string $instance_id): ?array;

  /**
   * Collect instances related to this buildable.
   *
   * Answered by a plugin built with no configuration: it is a question about
   * the kind of display, not about one of them. Not static, so the services
   * doing the collecting are the injected ones.
   *
   * @return array<string, \Drupal\display_builder\InstanceInterface>
   *   A associative array of Instance entities.
   */
  public function collectInstances(): array;

  /**
   * Collect every display this buildable could build, built or not.
   *
   * Unlike ::collectInstances(), which only sees displays that already have an
   * Instance entity, this lists the displays a user might need to travel to.
   * The nesting chain usually runs through one that is not built with Display
   * Builder yet, and a navigation panel that hides those fails exactly when it
   * is needed.
   *
   * Implementations must be read-only: no ::initInstanceIfMissing(), no
   * Instance entity loading, nothing written to storage. Rendering a
   * navigation panel used to create and save rows nobody asked for.
   *
   * Implementations of unbounded collections must honor 'limit' rather than
   * returning everything: a site with a few thousand overridden nodes must not
   * try to list a few thousand rows.
   *
   * Answered by a plugin built with no configuration, @see
   * ::collectInstances().
   *
   * @param array $options
   *   (Optional) Supported keys:
   *   - limit (int): maximum rows for collections that have no natural bound.
   *
   * @return \Drupal\display_builder\DisplayReference[]
   *   The displays, in no particular order.
   */
  public function collectDisplays(array $options = []): array;

  /**
   * What ::collectDisplays() left out, in words, for the user to read.
   *
   * A bounded collection is a lie unless it says it is bounded: a user who
   * cannot find their override in a list that silently stops at a limit
   * concludes the panel is broken, or worse, that the override is gone.
   * Implementations that return everything they have say nothing.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   A short sentence naming the bound, or NULL when the listing is complete.
   */
  public function collectDisplaysBound(): ?TranslatableMarkup;

  /**
   * Get profiles allowed for the user.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Optional user account. Current user if empty.
   *
   * @return array
   *   The list of allowed profiles.
   */
  public function getAllowedProfiles(?AccountInterface $account = NULL): array;

  /**
   * Get display builder instance URL.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public function getBuilderUrl(): Url;

  /**
   * Get the display url that use this instance.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url;

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  public function getInstance(): ?InstanceInterface;

  /**
   * Get instance ID.
   *
   * Will be used as HTML id & class attributes and Javascript variables names
   * (because of HTMX) so must follow the intersection between:
   * - https://developer.mozilla.org/en-US/docs/Web/CSS/ident
   * - https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Lexical_grammar#identifiers
   * Characters can be any of the following:
   * - any ASCII character in the ranges A-Z and a-z
   * - any decimal digit (0 to 9), except for the first character
   * - an underscore (_)
   *
   * @return string|null
   *   The instance ID for the display builder, or NULL if the entity is new.
   */
  public function getInstanceId(): ?string;

  /**
   * Get display builder profile config entity.
   *
   * If NULL, the Display Builder is not activated for this entity.
   *
   * @return ?ProfileInterface
   *   The display builder profile config entity.
   */
  public function getProfile(): ?ProfileInterface;

  /**
   * Get sources tree.
   *
   * @return array
   *   A list of nestable sources.
   */
  public function getSources(): array;

  /**
   * Get the site path this display can be previewed on, when there is one.
   *
   * Some sources only resolve inside a real page request: the page's own main
   * content and title are substituted by the page pipeline, not by the source
   * plugin, so a preview rendering sources on their own can only show a
   * placeholder for them. A buildable that is pinned to one concrete page can
   * name it here, and the preview renders that page instead - through the real
   * pipeline, serving this instance's draft state.
   *
   * Only a single, unambiguous path qualifies. A display that can appear on
   * many pages has no one page to preview against and must return NULL.
   *
   * @return string|null
   *   A path ('<front>' or '/some/path'), or NULL to preview the sources on
   *   their own.
   *
   * @see \Drupal\display_builder\Controller\ApiPreviewController::getDisplayPreview()
   */
  public function getPreviewPagePath(): ?string;

  /**
   * Get display builder instance URL from an instance ID.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public static function getUrlFromInstanceId(string $instance_id): Url;

  /**
   * Init instance if missing.
   *
   * Init an display_builder_instance entity if:
   * - ::getProfile() is not null
   * - the instance is not already existing in storage.
   */
  public function initInstanceIfMissing(): void;

  /**
   * Is the user allowed to use display builder.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Optional user account. Current user if empty.
   *
   * @return bool
   *   Allowed or not.
   */
  public function isAllowed(?AccountInterface $account = NULL): bool;

  /**
   * Save sources tree retrieved from the Instance entity to config or content.
   */
  public function saveSources(): void;

}
