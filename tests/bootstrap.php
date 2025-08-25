<?php

/**
 * @file
 * Bootstrap file for UtiliKit PHPUnit tests.
 *
 * This file is automatically loaded by PHPUnit before running tests.
 * It sets up the testing environment and autoloading.
 */

// Determine DRUPAL_ROOT if it's not already defined.
if (!defined('DRUPAL_ROOT')) {
  // Get current directory (fix: was using **DIR** instead of __DIR__)
  $dir = __DIR__;

  // Try to find the Drupal root by looking for core/lib/Drupal.php.
  $drupal_root_candidates = [
    // Standard Drupal installation (modules/custom/utilikit/tests)
    dirname($dir, 4),
    // Alternative structure (modules/contrib/utilikit/tests)
    dirname($dir, 4),
    // Composer-based structure with web directory.
    dirname($dir, 4) . '/web',
    dirname($dir, 5) . '/web',
    // Additional fallbacks for different directory structures.
    dirname($dir, 3),
    dirname($dir, 3) . '/web',
  ];

  $drupal_root_found = FALSE;
  foreach ($drupal_root_candidates as $candidate) {
    if (file_exists($candidate . '/core/lib/Drupal.php')) {
      define('DRUPAL_ROOT', realpath($candidate));
      $drupal_root_found = TRUE;
      break;
    }
  }

  if (!$drupal_root_found) {
    throw new \RuntimeException(
      'Could not find Drupal root directory. Please ensure this module is installed in a Drupal site. ' .
      'Searched in: ' . implode(', ', $drupal_root_candidates)
    );
  }
}

// Verify DRUPAL_ROOT is valid.
if (!file_exists(DRUPAL_ROOT . '/core/lib/Drupal.php')) {
  throw new \RuntimeException(
    'Invalid Drupal root directory: ' . DRUPAL_ROOT . '. Core files not found.'
  );
}

// Load Composer's autoloader.
$autoloader_path = DRUPAL_ROOT . '/autoload.php';
if (!file_exists($autoloader_path)) {
  throw new \RuntimeException(
    'Composer autoloader not found at: ' . $autoloader_path .
    '. Please run "composer install" in the Drupal root.'
  );
}

// Load the autoloader - PHPUnit will handle the class loading.
require $autoloader_path;

// Set up the testing environment variables.
if (!isset($_SERVER['REQUEST_TIME'])) {
  $_SERVER['REQUEST_TIME'] = time();
}

if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
  $_SERVER['REQUEST_TIME_FLOAT'] = microtime(TRUE);
}

// Set up a consistent testing environment
// Note: In test bootstrap, we set $_SERVER directly before Drupal bootstrap
// This is necessary as Drupal's API functions are not yet available.
$default_server_vars = [
  'REMOTE_ADDR' => '127.0.0.1',
  'REQUEST_METHOD' => 'GET',
  'HTTP_USER_AGENT' => 'Drupal PHPUnit test',
  'HTTP_HOST' => 'localhost',
  'SERVER_NAME' => 'localhost',
  'REQUEST_URI' => '/',
];

foreach ($default_server_vars as $key => $default_value) {
  if (!isset($_SERVER[$key])) {
    $_SERVER[$key] = $default_value;
  }
}

// Bootstrap Drupal's testing environment for kernel and functional tests.
if (in_array(PHP_SAPI, ['cli', 'phpdbg'], TRUE)) {
  // Set testing environment.
  if (getenv('SIMPLETEST_DB') || getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    putenv('DRUPAL_ENVIRONMENT=testing');
  }

  // Set default database for testing if not already set.
  if (!getenv('SIMPLETEST_DB') && !getenv('SIMPLETEST_BASE_URL')) {
    // Provide helpful error message for missing test database configuration.
    if (getenv('UTILIKIT_TEST_VERBOSE')) {
      echo "Warning: SIMPLETEST_DB not set. Kernel and functional tests may fail.\n";
      echo "Set SIMPLETEST_DB environment variable, e.g.:\n";
      echo "export SIMPLETEST_DB='mysql://user:pass@localhost/test_db'\n\n";
    }
  }
}

// Define testing constants if not already defined.
if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
  define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
}

// Load any additional test utilities or helpers specific to UtiliKit.
$utilikit_test_helpers = __DIR__ . '/TestHelpers.php';
if (file_exists($utilikit_test_helpers)) {
  require_once $utilikit_test_helpers;
}

// Load custom test traits if they exist.
$traits_directory = __DIR__ . '/traits';
if (is_dir($traits_directory)) {
  $trait_files = glob($traits_directory . '/*.php');
  foreach ($trait_files as $trait_file) {
    require_once $trait_file;
  }
}

// Output helpful information when running tests in verbose mode.
if (getenv('UTILIKIT_TEST_VERBOSE')) {
  echo "UtiliKit Test Bootstrap\n";
  echo "======================\n";
  echo "DRUPAL_ROOT: " . DRUPAL_ROOT . "\n";
  echo "Module Path: " . dirname(__DIR__) . "\n";
  echo "PHP Version: " . PHP_VERSION . "\n";
  echo "Memory Limit: " . ini_get('memory_limit') . "\n";

  // Check if PHPUnit is available.
  if (class_exists('\PHPUnit\Framework\TestCase')) {
    echo "PHPUnit: Available\n";
  }
  else {
    echo "PHPUnit: Not detected\n";
  }

  // Database configuration.
  $simpletest_db = getenv('SIMPLETEST_DB');
  if ($simpletest_db) {
    // Parse and display database info (without credentials)
    $db_parts = parse_url($simpletest_db);
    echo "Test Database Host: " . ($db_parts['host'] ?? 'Unknown') . "\n";
    echo "Test Database Name: " . (ltrim($db_parts['path'] ?? '', '/') ?: 'Unknown') . "\n";
  }
  else {
    echo "Test Database: Not configured\n";
  }

  echo "Base URL: " . (getenv('SIMPLETEST_BASE_URL') ?: 'Not configured') . "\n";
  echo "\n";
}

// Validate critical dependencies for UtiliKit tests.
$required_classes = [
  'Drupal\Tests\UnitTestCase',
  'Drupal\KernelTests\KernelTestBase',
];

$missing_dependencies = [];
foreach ($required_classes as $class) {
  if (!class_exists($class)) {
    $missing_dependencies[] = $class;
  }
}

if (!empty($missing_dependencies) && getenv('UTILIKIT_TEST_VERBOSE')) {
  echo "Warning: Missing test dependencies:\n";
  foreach ($missing_dependencies as $dependency) {
    echo "  - $dependency\n";
  }
  echo "\n";
}

// Set error reporting for tests.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent timeout issues during long-running tests.
if (function_exists('set_time_limit')) {
  set_time_limit(0);
}
