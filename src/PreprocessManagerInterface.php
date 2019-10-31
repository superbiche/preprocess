<?php

namespace Drupal\preprocess;

/**
 * Interface definition for managing preprocessing.
 *
 * @package Drupal\preprocess
 */
interface PreprocessManagerInterface {

  /**
   * Get suggestions for a given hook.
   *
   * @param string $hook
   *   The name of the hook.
   * @param array $variables
   *   The variables to preprocess.
   *
   * @return array
   *   The hook's suggestions.
   */
  public function getSuggestions(string $hook, array $variables): array;

  /**
   * Preprocesses variables for a given hook.
   *
   * @param string $hook
   *   The preprocess hook.
   * @param array $variables
   *   The variables to preprocess.
   *
   * @return array
   *   The preprocessed variables.
   */
  public function preprocess(string $hook, array $variables): array;

}
