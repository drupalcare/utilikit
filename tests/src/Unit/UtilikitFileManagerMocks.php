<?php

/**
 * @file
 * Mock implementations of file system functions for UtilikitFileManager tests.
 *
 * These functions override PHP built-ins within the Drupal\utilikit\Service
 * namespace so that unqualified calls in UtilikitFileManager (e.g.
 * file_get_contents()) are intercepted during testing. Test code controls
 * return values via $GLOBALS entries.
 */

declare(strict_types=1);

namespace Drupal\utilikit\Service;

/**
 * Mock implementation of file_get_contents for testing.
 */
function file_get_contents($filename) {
  return $GLOBALS['_utilikit_test_file_get_contents'] ?? \file_get_contents($filename);
}

/**
 * Mock implementation of file_exists for testing.
 */
function file_exists($filename) {
  return $GLOBALS['_utilikit_test_file_exists'] ?? \file_exists($filename);
}

/**
 * Mock implementation of scandir for testing.
 */
function scandir($directory) {
  return $GLOBALS['_utilikit_test_scandir'] ?? \scandir($directory);
}

/**
 * Mock implementation of rmdir for testing.
 */
function rmdir($dirname) {
  return $GLOBALS['_utilikit_test_rmdir'] ?? \rmdir($dirname);
}
