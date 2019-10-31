<?php

namespace Drupal\Tests\preprocess\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\preprocess\PreprocessInterface;
use Drupal\Core\Theme\MissingThemeDependencyException;
use Drupal\user\Entity\User;
use Exception;
use function array_diff;
use function array_key_exists;
use function array_map;
use function array_merge;

/**
 * Tests Preprocess functionality.
 *
 * @group Preprocess
 */
class PreprocessTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'preprocess',
    'preprocess_test',
  ];

  /**
   * The preprocess plugin manager.
   *
   * @var \Drupal\preprocess\PreprocessPluginManagerInterface
   */
  private $preprocessPluginManager;

  /**
   * The article node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $articleNode;

  /**
   * The node view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $nodeViewBuilder;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Theme initialization service.
   *
   * @var \Drupal\Core\Theme\ThemeInitializationInterface
   */
  protected $themeInitializer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->preprocessPluginManager = $this->container->get('preprocess.plugin.manager');
    $this->themeManager = $this->container->get('theme.manager');
    $this->themeInitializer = $this->container->get('theme.initialization');
    $this->nodeViewBuilder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $this->container->get('theme_installer')->install(['test_preprocess_theme']);

    $type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $type->save();

    $this->installSchema('node', 'node_access');
    $this->installConfig(['system', 'node']);
    $this->config('system.theme')->set('default', 'test_preprocess_theme')->save();
  }

  /**
   * Test the discovery of plugins in themes and modules.
   *
   * @param string $plugin_id
   *   The id of the plugin that should be discovered.
   * @param string $theme_name
   *   The name of the theme to use as the active theme.
   * @param bool $expected
   *   Whether or not the plugin is expected to be discovered.
   *
   * @dataProvider discoveryData
   */
  public function testDiscovery(string $plugin_id, string $theme_name, bool $expected): void {
    try {
      $active_theme = $this->themeInitializer->getActiveThemeByName($theme_name);
    }
    catch (MissingThemeDependencyException $exception) {
      $this->fail($exception->getMessage());
      return;
    }

    $this->themeManager->setActiveTheme($active_theme);
    self::assertSame($expected, array_key_exists($plugin_id, $this->preprocessPluginManager->getDefinitions()));
  }

  /**
   * Test that only the processors for a hook are retrieved if they define it.
   *
   * @param string $hook
   *   The hook to get preprocessors for.
   * @param array $expected_plugin_ids
   *   The expected preprocessor plugin ids.
   *
   * @dataProvider getPreprocessorsData
   */
  public function testGetPreprocessors(string $hook, array $expected_plugin_ids): void {
    $preprocessors = $this->preprocessPluginManager->getPreprocessors($hook);
    $plugin_ids = array_map(static function (PreprocessInterface $preprocessor) {
      return $preprocessor->getPluginId();
    }, $preprocessors);

    $diff = array_merge(array_diff($plugin_ids, $expected_plugin_ids), array_diff($expected_plugin_ids, $plugin_ids));
    self::assertEmpty($diff);
  }

  /**
   * Test preprocessing.
   *
   * @param array $element
   *   The element to render.
   * @param string $expected_class
   *   The class we expect the preprocessor to add to the element.
   *
   * @dataProvider preprocessData
   */
  public function testPreprocess(array $element, string $expected_class): void {
    try {
      $this->render($element);
      $this->assertRaw($expected_class, $this->getRawContent());
    }
    catch (Exception $exception) {
      $this->fail($exception->getMessage());
      return;
    }
  }

  /**
   * Test node preprocessing.
   */
  public function testNodePreprocess(): void {
    $account = User::create([
      'name' => $this->randomString(),
    ]);
    $account->save();

    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'uid' => $account->id(),
    ]);
    $node->save();

    $build = $this->nodeViewBuilder->view($node);
    $this->render($build);
    $this->assertRaw('Hello world.', $this->getRawContent());
  }

  /**
   * Data provider for testDiscovery().
   */
  public function discoveryData(): array {
    return [
      'preprocessor_theme_test_image' => [
        'test_preprocess_theme_image.preprocessor',
        'test_preprocess_theme',
        TRUE,
      ],
      'preprocessor_theme_test_node__article' => [
        'test_preprocess_theme_node__article.preprocessor',
        'test_preprocess_theme',
        TRUE,
      ],
      'preprocessor_theme_core_theme' => [
        'test_preprocess_theme.preprocessor',
        'core',
        FALSE,
      ],
      'preprocessor_module_test_theme' => [
        'preprocess_test.preprocessor',
        'test_preprocess_theme',
        TRUE,
      ],
      'preprocessor_module_core_theme' => [
        'preprocess_test.preprocessor',
        'core',
        TRUE,
      ],
    ];
  }

  /**
   * Data provider for testGetPreprocessors().
   */
  public function getPreprocessorsData(): array {
    return [
      'hook_preprocess_input' => [
        'input',
        ['preprocess_test.preprocessor'],
      ],
      'hook_preprocess_image' => [
        'image',
        ['test_preprocess_theme_image.preprocessor'],
      ],
      'hook_preprocess_node__article' => [
        'node__article',
        ['test_preprocess_theme_node__article.preprocessor'],
      ],
      'hook_preprocess_fake_hook' => [
        'fake_hook',
        [],
      ],
    ];
  }

  /**
   * Data provider for testPreprocess().
   */
  public function preprocessData(): array {
    return [
      'preprocess_input' => [
        ['#type' => 'button', '#value' => $this->randomMachineName()],
        'my-test-input-class',
      ],
      'preprocess_image' => [
        ['#theme' => 'image', '#uri' => 'logo.svg'],
        'my-test-image-class',
      ],
    ];
  }

}
