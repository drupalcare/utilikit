<?php

namespace Drupal\Tests\utilikit\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\utilikit\Traits\UtilikitTestCleanupTrait;

/**
 * Base class for UtiliKit kernel tests.
 *
 * Provides automatic cleanup for kernel tests.
 */
abstract class UtilikitKernelTestBase extends KernelTestBase {
  use UtilikitTestCleanupTrait;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up files (not part of DB transaction).
    $this->cleanupUtilikitArtifacts();

    parent::tearDown();
  }

}
