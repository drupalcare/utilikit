<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Traits;

use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Provides cleanup methods for UtiliKit tests.
 *
 * This trait follows Drupal best practices by using the file_system
 * service and state API for cleanup operations.
 */
trait UtilikitTestCleanupTrait {

  /**
   * Cleans up UtiliKit test artifacts.
   *
   * This method is safe to call multiple times and will only
   * clean up test-specific data.
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
   * Cleans up UtiliKit state data.
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
      $stateManager->clearUtilikitData();
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
