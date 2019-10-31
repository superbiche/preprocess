<?php

namespace Drupal\test_preprocess_theme\Plugin\Preprocess;

use Drupal\preprocess\PreprocessPluginBase;

/**
 * Test preprocessor provided by theme.
 */
class ThemePreprocessNodeArticleTestPlugin extends PreprocessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $variables['content'][] = ['#markup' => 'Hello world.'];
    return $variables;
  }

}
