<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\utilikit\Traits\UtilikitTestCleanupTrait;

/**
 * Base class for UtiliKit functional tests.
 *
 * Provides automatic cleanup following Drupal best practices.
 */
abstract class UtilikitFunctionalTestBase extends BrowserTestBase {
  use UtilikitTestCleanupTrait;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Drupal's BrowserTestBase already provides test isolation,
    // but we clean up our specific artifacts.
    $this->cleanupUtilikitArtifacts();

    parent::tearDown();
  }

}
