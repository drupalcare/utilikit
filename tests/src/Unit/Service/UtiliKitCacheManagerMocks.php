<?php

declare(strict_types=1);

/**
 * @file
 * Mock implementations for Drupal functions used in UtiliKitCacheManager tests.
 */

namespace Drupal\utilikit\Service;

/**
 * Mock implementation of drupal_flush_all_caches for testing.
 */
function drupal_flush_all_caches(): void {
  $GLOBALS['_utilikit_test_drupal_flush_all_caches_called'] = TRUE;
}
