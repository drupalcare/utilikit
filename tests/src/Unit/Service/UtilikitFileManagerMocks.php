<?php

declare(strict_types=1);

/**
 * @file
 * Mock implementations file system functions used in UtilikitFileManager tests.
 */

namespace Drupal\utilikit\Service;

/**
 * Mock implementation of file_get_contents for testing.
 */
function file_get_contents($filename) {
  return $GLOBALS['_utilikit_test_file_get_contents'] ?? '';
}

/**
 * Mock implementation of file_exists for testing.
 */
function file_exists($filename) {
  return $GLOBALS['_utilikit_test_file_exists'] ?? TRUE;
}

/**
 * Mock implementation of scandir for testing.
 */
function scandir($directory) {
  return $GLOBALS['_utilikit_test_scandir'] ?? ['.', '..'];
}

/**
 * Mock implementation of rmdir for testing.
 */
function rmdir($dirname) {
  return TRUE;
}
