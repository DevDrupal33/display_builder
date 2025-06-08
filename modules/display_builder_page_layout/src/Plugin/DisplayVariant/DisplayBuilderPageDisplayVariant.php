<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\DisplayVariant;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder_page_layout\DisplayBuilderPageLayout;

/**
 * Provides a page display variant that simply renders the main content.
 */
#[PageDisplayVariant(
  id: 'display_builder_page',
  admin_label: new TranslatableMarkup('Display Builder (Full page)')
)]
class DisplayBuilderPageDisplayVariant extends DisplayBuilderDisplayVariant implements PageVariantInterface {

  private const SOURCE_CONTENT_ID = 'main_page_content';

  private const SOURCE_TITLE_ID = 'page_title';

  /**
   * The render array representing the main content.
   *
   * @var array
   */
  protected $mainContent;

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected $title = '';

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // We can render either the page layout configuration or the current page
    // manager variant.
    $builder_id = $this->configuration['display_builder_id'] ?? NULL;

    // This is the default page layout.
    $is_page_layout = FALSE;

    if ($builder_id === NULL) {
      $builder_id = $this->config->get('builder_id');
      // Config data is the saved state by default.
      $builder_data = $this->config->get('sources');
      $is_page_layout = TRUE;
    }
    else {
      $builder_data = $this->configuration['display_builder_sources'] ?? [];
    }

    // If no data, stop here, set a message to inform and link the display
    // builder.
    if (empty($builder_data)) {
      // Generate url if this is the main page layout or a page manager.
      if ($is_page_layout && $this->config->getName() === DisplayBuilderPageLayout::PAGE_LAYOUT_CONFIG) {
        $url = Url::fromRoute('display_builder_page_layout.manage');
      }
      else {
        $url = Url::fromRoute('display_builder_page_layout.page_manager.manage', ['builder_id' => $builder_id]);
      }
      $this->messenger()->addStatus($this->t('This Display builder is empty, you can <a href="@url">edit this layout here</a> to add content.', ['@url' => $url->toString()]));

      return [
        'status_messages' => [
          '#type' => 'status_messages',
          '#weight' => -1000,
          '#include_fallback' => TRUE,
        ],
      ];
    }

    $this->replaceTitleAndContent($builder_data, $this->title, $this->mainContent);

    $contexts = $this->stateManager->getContexts($builder_id) ?? [];
    // @todo merge contexts from page manager.
    // phpcs:ignore-next-line
    // $contexts = \array_merge($contexts, $this->getContexts());
    $display_builder_data = [];

    foreach ($builder_data as $source_data) {
      $build = $this->componentElementBuilder->buildSource($display_builder_data, 'content', [], $source_data, $contexts);
      $display_builder_data[] = $build['#slots']['content'][0] ?? [];
    }

    return [
      'content' => [
        'status_messages' => [
          '#type' => 'status_messages',
          '#weight' => -1000,
          '#include_fallback' => TRUE,
        ],
        'display_builder' => [
          'data' => $display_builder_data,
          '#weight' => -800,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content): self {
    $this->mainContent = $main_content;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title): self {
    $this->title = $title;

    return $this;
  }

  /**
   * Replace title and content blocks.
   *
   * @param array $data
   *   The Display Builder data to alter.
   * @param mixed $title
   *   The title to set.
   * @param array|null $content
   *   The content to set.
   *
   * @todo replace multiple in one pass?
   */
  private function replaceTitleAndContent(array &$data, mixed $title, ?array $content): void {
    if ($content !== NULL) {
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => self::SOURCE_CONTENT_ID], $content);
    }

    // Try to handle specific title cases.
    if (\is_string($title)) {
      $title = $title;
    }
    elseif ($title instanceof MarkupInterface) {
      $title = (string) $title;
    }
    elseif (isset($title['#markup'])) {
      $title = $title['#markup'];
    }

    if ($title !== NULL && \is_string($title)) {
      // @todo avoid arbitrary classes.
      $title = ['#markup' => '<h1 class="title page-title">' . $title . '</h1>'];
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => self::SOURCE_TITLE_ID], $title);
    }
  }

}
