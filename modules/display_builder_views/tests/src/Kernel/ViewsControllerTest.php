<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder_views\Controller\ViewsController;
use Drupal\display_builder_views\Routing\DisplayBuilderRoutes;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kernel test for display_builder_views.views.manage route.
 *
 * @internal
 */
#[CoversClass(ViewsController::class)]
#[CoversClass(DisplayBuilderRoutes::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
#[RunTestsInSeparateProcesses]
final class ViewsControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'views_ui',
    'display_builder',
    'display_builder_views',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('view');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'views', 'display_builder', 'display_builder_views', 'ui_patterns']);
  }

  /**
   * Test the display_builder_views.views.manage route's param converter.
   *
   * From Drupal 11.3 to Drupal 11.4, converter route parameter has changed.
   *
   * @see https://www.drupal.org/project/drupal/issues/3436295
   *
   * @todo Remove once Drupal 11.3 is not supported.
   */
  public function testParamConverter(): void {
    $view = View::create([
      'id' => 'test_view',
      'label' => 'Test View',
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [
            'display_extenders' => [
              'display_builder' => [
                'profile' => 'default',
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    $url = Url::fromRoute('display_builder_views.views.manage', [
      'view' => $view->id(),
      'display' => 'default',
    ]);
    $request = Request::create($url->toString(), 'GET', []);
    $http_kernel = $this->container->get('http_kernel');
    $response = $http_kernel->handle($request);
    // We are looking for anything not throwing this InvalidArgumentException:
    // 'No converter has been registered at ParamConverterManager'.
    // A 403 (HTTP Forbidden) is fine for example, because we are not logged
    // in.
    self::assertLessThan(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
  }

}
