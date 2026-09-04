<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

/**
 * Interface for generating complete test CSS from every utility class.
 *
 * Defines the contract for the service that enumerates every utility class
 * UtiliKit can produce and writes them to a single stylesheet, for testing,
 * debugging, or external validation of CSS generation.
 *
 * The implementation depends on the utilikit_test module for the class
 * enumeration and degrades to an explicit failure result when it is absent,
 * rather than throwing.
 */
interface UtilikitTestCssGeneratorInterface {

  /**
   * Generates a complete test CSS file with all possible utility classes.
   *
   * The generated file is saved as 'utilikit-test-complete.css' in the
   * standard UtiliKit CSS directory.
   *
   * @return array
   *   An associative array containing the operation result:
   *   - 'success': (bool) TRUE if CSS generation succeeded, FALSE otherwise
   *   - 'error': (string) Error message if success is FALSE
   *   - 'count': (int) Number of utility classes included (success only)
   *   - 'size': (int) Size of generated CSS in bytes (success only)
   *   - 'file_url': (string) Absolute URL to the generated file (success only)
   */
  public function generateCompleteTestCss(): array;

  /**
   * Generates and returns all possible utility classes.
   *
   * Retrieves every possible utility class without generating CSS. Useful for
   * enumerating classes for external tools or validation.
   *
   * @return array
   *   Array of all possible utility class names, or an empty array when the
   *   test generator is not available.
   */
  public function generateAllUtilityClasses(): array;

  /**
   * Deletes the test CSS file.
   *
   * @return bool
   *   TRUE if the file was deleted successfully or does not exist, FALSE if
   *   deletion failed.
   */
  public function deleteTestCssFile(): bool;

  /**
   * Gets information about the test CSS file.
   *
   * @return array
   *   An associative array with file information:
   *   - 'exists': (bool) Whether the file exists
   *   - 'size': (int|null) File size in bytes, NULL if the file is absent
   *   - 'url': (string|null) Public URL to the file, NULL if absent
   *   - 'modified': (int|null) Unix timestamp of last modification, NULL if
   *     absent.
   */
  public function getTestCssFileInfo(): array;

}
