<?php

namespace Drupal\preprocess;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Manages preprocessing.
 *
 * @package Drupal\preprocess
 */
class PreprocessManager implements PreprocessManagerInterface {

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The plugin manager.
   *
   * @var \Drupal\preprocess\PreprocessPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The theme registry.
   *
   * @var \Drupal\core\Theme\Registry
   */
  protected $themeRegistry;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs a PreprocessManager object.
   *
   * @param \Drupal\preprocess\PreprocessPluginManagerInterface $plugin_manager
   *   The plugin manager.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(PreprocessPluginManagerInterface $plugin_manager, Registry $theme_registry, ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager) {
    $this->pluginManager = $plugin_manager;
    $this->themeRegistry = $theme_registry;
    $this->moduleHandler = $module_handler;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions(string $hook, array $variables): array {
    $suggestions = $this->moduleHandler->invokeAll('theme_suggestions_' . $hook, [$variables]);
    $hooks = [
      'theme_suggestions',
      'theme_suggestions_' . $hook,
    ];
    $this->moduleHandler->alter($hooks, $suggestions, $variables, $hook);
    $this->themeManager->alter($hooks, $suggestions, $variables, $hook);

    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(string $hook, array $variables): array {
    $suggestions = $this->getSuggestions($hook, $variables);
    $variables = $this->doPreprocess($hook, $variables);

    if (empty($suggestions)) {
      return $variables;
    }

    $theme_registry = $this->themeRegistry->getRuntime();
    foreach ($suggestions as $suggestion) {
      if (!$theme_registry->has($suggestion)) {
        continue;
      }

      $variables = $this->doPreprocess($suggestion, $variables);
    }

    return $variables;
  }

  /**
   * Handles the preprocessing of a single hook.
   *
   * @param string $hook
   *   The preprocess hook.
   * @param array $variables
   *   The variables to preprocess.
   *
   * @return array
   *   The processed variables.
   */
  protected function doPreprocess(string $hook, array $variables): array {
    foreach ($this->pluginManager->getPreprocessors($hook) as $preprocessor) {
      $variables = $preprocessor->preprocess($variables);
    }

    return $variables;
  }

}
