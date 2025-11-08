<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Traits;

/**
 * Provides cleanup methods for Utilikit tests.
 *
 * This trait handles cleanup of generated CSS files, cache entries,
 * and other test artifacts after test execution.
 */
trait UtilikitTestCleanupTrait {

  /**
   * Cleans up test artifacts after test execution.
   *
   * This method should be called in tearDown() to ensure proper cleanup
   * of CSS files, cache entries, and other test data.
   */
  protected function cleanupTestArtifacts(): void {
    // Clean up generated CSS files.
    $this->cleanupGeneratedCss();

    // Clear relevant caches.
    $this->cleanupCaches();
  }

  /**
   * Removes generated CSS files from the file system.
   */
  protected function cleanupGeneratedCss(): void {
    $file_system = \Drupal::service('file_system');
    $public_path = \Drupal::service('file_system')->realpath('public://');
    $css_directory = $public_path . '/css/utilikit';

    if (is_dir($css_directory)) {
      $this->recursiveDelete($css_directory);
    }
  }

  /**
   * Clears Utilikit-related caches.
   */
  protected function cleanupCaches(): void {
    // Clear render cache.
    \Drupal::cache('render')->deleteAll();

    // Clear discovery cache.
    \Drupal::cache('discovery')->deleteAll();

    // Clear CSS cache if it exists.
    if (\Drupal::hasService('cache.utilikit_css')) {
      \Drupal::cache('utilikit_css')->deleteAll();
    }
  }

  /**
   * Recursively deletes a directory and its contents.
   *
   * @param string $dir
   *   The directory path to delete.
   */
  protected function recursiveDelete(string $dir): void {
    if (!is_dir($dir)) {
      return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      $path = $dir . '/' . $file;
      is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
    }
    rmdir($dir);
  }

}
