<?php

namespace Drupal\Tests\utilikit\Traits;

use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Provides cleanup methods for UtiliKit tests.
 *
 * This trait follows Drupal best practices by using the file_system
 * service and state API for cleanup operations. It ensures that test
 * artifacts, generated CSS files, and state data are properly cleaned
 * up between test runs to prevent test pollution and ensure reliable
 * test execution.
 *
 * Usage: Include this trait in test classes that need to clean up
 * UtiliKit-specific data during tearDown() or other cleanup phases.
 */
trait UtilikitTestCleanupTrait {

  /**
   * Cleans up UtiliKit test artifacts including files and state data.
   *
   * This method is safe to call multiple times and will only clean up
   * test-specific data. It handles both file cleanup (CSS files, test
   * artifacts) and state cleanup (known classes, timestamps, etc.).
   *
   * The method gracefully handles different test environments:
   * - Functional tests with injected services
   * - Kernel tests with container access
   * - Unit tests with limited service availability
   */
  protected function cleanupUtilikitArtifacts(): void {
    // Use existing service if available (Functional/Kernel tests).
    if (isset($this->fileManager)) {
      // Reuse the existing cleanupStaticFiles method.
      $this->fileManager->cleanupStaticFiles();
    }
    elseif ($this->container && $this->container->has('utilikit.file_manager')) {
      // For tests that have container but not the service injected.
      $this->container->get('utilikit.file_manager')->cleanupStaticFiles();
    }

    // Clean state for all test types.
    $this->cleanupUtilikitState();
  }

  /**
   * Cleans up UtiliKit state data from the state API.
   *
   * Removes all UtiliKit-related state variables including known classes,
   * generated CSS content, timestamps, and cleanup tracking data. This
   * ensures that each test starts with a clean state and prevents data
   * from previous tests from affecting current test execution.
   *
   * Uses the state manager service when available, or falls back to
   * direct state API access for environments where services aren't
   * fully available.
   */
  protected function cleanupUtilikitState(): void {
    if (!$this->container || !$this->container->has('state')) {
      return;
    }

    $stateManager = $this->container->has('utilikit.state_manager')
      ? $this->container->get('utilikit.state_manager')
      : NULL;

    if ($stateManager) {
      // Use the service's built-in cleanup method.
      $stateManager->clearUtiliKitData();
    }
    else {
      // Fallback for tests without the service.
      $state = $this->container->get('state');
      $state->deleteMultiple([
        UtilikitConstants::STATE_KNOWN_CLASSES,
        UtilikitConstants::STATE_GENERATED_CSS,
        UtilikitConstants::STATE_CSS_TIMESTAMP,
        UtilikitConstants::STATE_LAST_CLEANUP,
      ]);
    }
  }

}
