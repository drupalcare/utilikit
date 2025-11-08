<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit;

use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;

/**
 * @file
 * Helper functions and utilities for UtiliKit tests.
 *
 * Provides comprehensive testing utilities for UtiliKit module including
 * entity creation with utility classes, CSS validation, rate limiting tests,
 * and mock configuration helpers. These utilities support unit tests,
 * kernel tests, and functional tests across the UtiliKit testing suite.
 */

/**
 * Provides helper methods for UtiliKit tests.
 *
 * This trait contains common testing utilities used across UtiliKit test
 * classes. It includes methods for creating test entities with utility
 * classes, generating HTML content, validating CSS output, testing rate
 * limits, and creating mock configurations.
 *
 * Usage:
 * @code
 * class MyUtilikitTest extends UnitTestCase {
 *   use UtilikitTestHelpers;
 *
 *   public function testSomething() {
 *     $classes = $this->getTestUtilityClasses('responsive');
 *     $entity = $this->createEntityWithUtilityClasses('node', [], $classes);
 *     // ... test logic
 *   }
 * }
 * @endcode
 */
trait UtilikitTestHelpers {

  /**
   * Creates a sample entity with UtiliKit classes for testing.
   *
   * This method creates an entity of the specified type and populates it
   * with HTML content containing UtiliKit utility classes. Useful for
   * testing class detection, CSS generation, and entity processing workflows.
   *
   * @param string $entity_type
   *   The entity type to create (e.g., 'node', 'block_content').
   * @param array $values
   *   Base values for the entity. If 'body' is not provided, it will be
   *   automatically generated with utility classes.
   * @param array $utility_classes
   *   Array of utility classes to include in the entity's body field.
   *   These should be valid UtiliKit class names.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created and saved entity with utility classes.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the entity type definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when the entity type is not found.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity cannot be saved.
   *
   * @example
   * ```php
   * // Create a node with responsive utility classes
   * $classes = ['uk-pd--20', 'uk-md-pd--30', 'uk-lg-pd--40'];
   * $entity = $this->createEntityWithUtilityClasses('node', [
   *   'type' => 'article',
   *   'title' => 'Test Article',
   * ], $classes);
   * ```
   */
  protected function createEntityWithUtilityClasses(string $entity_type, array $values, array $utility_classes): EntityInterface {
    // Validate utility classes before creating entity.
    $validated_classes = $this->validateUtilityClasses($utility_classes);

    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    // Add utility classes to a body field if not already provided.
    if (!isset($values['body'])) {
      $values['body'] = [
        'value' => $this->generateHtmlWithClasses($validated_classes),
        'format' => 'full_html',
      ];
    }

    $entity = $storage->create($values);
    $entity->save();

    return $entity;
  }

  /**
   * Generates HTML content with UtiliKit classes for testing.
   *
   * Creates a structured HTML string with each utility class applied to
   * individual div elements. This is useful for testing class detection
   * algorithms and CSS generation processes.
   *
   * @param array $classes
   *   Array of utility class names to include in the HTML. Each class
   *   will be applied to a separate div element with the 'utilikit' base
   *   class for proper framework detection.
   *
   * @return string
   *   HTML string with properly formatted utility class elements wrapped
   *   in a test container div.
   *
   * @example
   * ```php
   * $classes = ['uk-pd--20', 'uk-bg--ff0000'];
   * $html = $this->generateHtmlWithClasses($classes);
   * // Returns: '<div class="test-wrapper">...</div>'
   * ```
   */
  protected function generateHtmlWithClasses(array $classes): string {
    $html = '<div class="test-wrapper">';

    foreach ($classes as $class) {
      $escaped_class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
      $html .= sprintf(
        '<div class="utilikit %s">Test content with %s</div>',
        $escaped_class,
        $escaped_class
      );
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Asserts that CSS contains expected rules for utility classes.
   *
   * Validates that generated CSS includes proper rules for the specified
   * utility classes. Uses regular expressions to match CSS class selectors
   * and their corresponding rule blocks.
   *
   * @param string $css
   *   The CSS string to validate. Should contain generated UtiliKit styles.
   * @param array $expected_classes
   *   Array of class names that should have corresponding CSS rules.
   *   Class names should not include the leading dot.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   Thrown when expected CSS rules are not found.
   *
   * @example
   * ```php
   * $css = '.uk-pd--20 { padding: 20px; } .uk-mg--10 { margin: 10px; }';
   * $classes = ['uk-pd--20', 'uk-mg--10'];
   * $this->assertCssContainsClasses($css, $classes);
   * ```
   */
  protected function assertCssContainsClasses(string $css, array $expected_classes): void {
    foreach ($expected_classes as $class) {
      // Validate class format before testing.
      if (!$this->isValidUtilityClass($class)) {
        $this->fail("Invalid utility class format: $class");
      }

      $escaped_class = preg_quote($class, '/');
      $this->assertMatchesRegularExpression(
        '/\.' . $escaped_class . '\s*\{[^}]+\}/',
        $css,
        "CSS should contain rules for class: $class"
      );
    }
  }

  /**
   * Gets predefined utility classes for various test scenarios.
   *
   * Returns arrays of utility classes tailored for specific testing
   * scenarios. This centralizes test data management and ensures
   * consistency across different test methods and classes.
   *
   * Available scenarios:
   * - 'basic': Simple padding, margin, background, and text color classes
   * - 'responsive': Classes with breakpoint prefixes
   * - 'complex': Advanced layout classes with multiple values
   * - 'grid': CSS Grid-specific utility classes
   * - 'invalid': Invalid class names for error testing
   * - 'performance': Large array for performance testing (1000 classes)
   * - 'transforms': Transform and positioning utilities
   * - 'colors': Color-related utility classes
   * - 'typography': Text and font utility classes
   *
   * @param string $scenario
   *   The test scenario name. Defaults to 'basic' if not recognized.
   *
   * @return array
   *   Array of utility class names appropriate for the scenario.
   *
   * @example
   * ```php
   * // Get responsive classes for breakpoint testing
   * $responsive_classes = $this->getTestUtilityClasses('responsive');
   *
   * // Get performance test classes
   * $performance_classes = $this->getTestUtilityClasses('performance');
   * ```
   */
  protected function getTestUtilityClasses(string $scenario = 'basic'): array {
    $scenarios = [
      'basic' => [
        'uk-pd--20',
        'uk-mg--t-10',
        'uk-bg--ff0000',
        'uk-tc--333333',
      ],
      'responsive' => [
        'uk-pd--20',
        'uk-md-pd--30',
        'uk-lg-pd--40',
        'uk-xl-pd--50',
      ],
      'complex' => [
        'uk-pd--10-20-30-40',
        'uk-br--t-10',
        'uk-dp--flex',
        'uk-jc--center',
        'uk-ai--center',
        'uk-gp--16',
      ],
      'grid' => [
        'uk-dp--grid',
        'uk-gc--repeat-3-1fr',
        'uk-gr--100px-auto-1fr',
        'uk-gl--1-3',
        'uk-gw--2-4',
      ],
      'transforms' => [
        'uk-rt--45',
        'uk-sc--150',
        'uk-tx--50px',
        'uk-ty--100px',
      ],
      'colors' => [
        'uk-bg--ff0000',
        'uk-tc--000000',
        'uk-bc--00ff00',
        'uk-bg--rgba-255-255-255-0d5',
      ],
      'typography' => [
        'uk-fs--16px',
        'uk-fw--bold',
        'uk-lh--1d5',
        'uk-ta--center',
      ],
      'invalid' => [
        'uk-invalid--class',
        'not-a-utility-class',
        'uk-pd--invalid-value',
        'uk-zz--20',
      ],
      'performance' => array_map(
        fn($i) => "uk-pd--{$i}",
        range(1, 1000)
      ),
    ];

    return $scenarios[$scenario] ?? $scenarios['basic'];
  }

  /**
   * Creates a mock configuration object for testing.
   *
   * Generates a PHPUnit mock of ImmutableConfig that returns predefined
   * values for configuration keys. Useful for testing services and
   * functions that depend on configuration without requiring full
   * configuration setup.
   *
   * @param array $settings
   *   Associative array of configuration key-value pairs. The mock will
   *   return these values when get() is called with the corresponding key.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   A mock configuration object that implements the ImmutableConfig
   *   interface and returns the provided settings when get() is called.
   *
   * @example
   * ```php
   * // Create mock config with specific settings
   * $config = $this->createMockConfig([
   *   'rendering_mode' => 'static',
   *   'dev_mode' => true,
   *   'scope' => 'global',
   * ]);
   *
   * // Use in service constructor or method calls
   * $service = new SomeService($config);
   * ```
   */
  protected function createMockConfig(array $settings): MockObject {
    $config = $this->createMock(ImmutableConfig::class);

    $config->method('get')
      ->willReturnCallback(function ($key) use ($settings) {
        return $settings[$key] ?? NULL;
      });

    return $config;
  }

  /**
   * Asserts that a rate limit is properly enforced.
   *
   * Tests rate limiting functionality by repeatedly calling an action
   * and verifying that it stops accepting requests after the limit is
   * reached. Useful for testing API endpoints, file generation, and
   * other rate-limited operations.
   *
   * @param callable $action
   *   The action to test. Should return FALSE or throw an exception
   *   when rate limited, or return a response object with status 429.
   * @param int $limit
   *   The expected rate limit (maximum allowed calls).
   * @param int $window
   *   The time window in seconds (used for assertion messaging).
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   Thrown when rate limiting is not properly enforced.
   *
   * @example
   * ```php
   * // Test API rate limiting
   * $this->assertRateLimitEnforced(
   *   function() { return $this->apiController->generateCss(); },
   *   5,  // 5 requests per window
   *   60  // 60 second window
   * );
   * ```
   */
  protected function assertRateLimitEnforced(callable $action, int $limit, int $window): void {
    $count = 0;

    // Attempt to exceed the rate limit by calling action multiple times.
    for ($i = 0; $i < $limit + 5; $i++) {
      try {
        $result = $action();

        // Check for rate limit indicators.
        if ($result === FALSE || (is_object($result) && method_exists($result, 'getStatusCode') && $result->getStatusCode() === 429)) {
          // Rate limit hit - stop counting.
          break;
        }
        $count++;
      }
      catch (\Exception $e) {
        // Rate limit might throw exception - stop counting.
        break;
      }
    }

    $this->assertLessThanOrEqual(
      $limit,
      $count,
      "Rate limit of $limit per $window seconds should be enforced"
    );
  }

  /**
   * Waits for AJAX requests to complete in functional tests.
   *
   * Pauses test execution until all jQuery AJAX requests have finished.
   * Essential for functional tests that interact with dynamic content
   * or AJAX-powered interfaces.
   *
   * @param int $timeout
   *   Maximum time to wait in seconds before giving up. Defaults to 10.
   *
   * @throws \Exception
   *   Thrown when AJAX requests don't complete within the timeout period.
   *
   * @example
   * ```php
   * // Click button that triggers AJAX request
   * $this->getSession()->getPage()->clickLink('Update CSS');
   *
   * // Wait for AJAX to complete
   * $this->waitForAjax(15);
   *
   * // Continue with assertions
   * $this->assertSession()->pageTextContains('CSS updated successfully');
   * ```
   */
  protected function waitForAjax(int $timeout = 10): void {
    $this->getSession()->wait(
      $timeout * 1000,
      '(typeof jQuery !== "undefined" && jQuery.active === 0)'
    );
  }

  /**
   * Validates utility class names according to UtiliKit naming conventions.
   *
   * Checks if utility class names follow the expected pattern and filters
   * out invalid classes. This method helps ensure test data integrity and
   * prevents testing with malformed class names.
   *
   * @param array $classes
   *   Array of utility class names to validate.
   *
   * @return array
   *   An array of valid utility class names with invalid ones filtered out.
   *   Only classes matching the UtiliKit naming pattern are returned.
   *
   * @example
   * ```php
   * $classes = ['uk-pd--20', 'invalid-class', 'uk-mg--10'];
   * $valid = $this->validateUtilityClasses($classes);
   * // Returns: ['uk-pd--20', 'uk-mg--10']
   * ```
   */
  protected function validateUtilityClasses(array $classes): array {
    return array_filter($classes, [$this, 'isValidUtilityClass']);
  }

  /**
   * Checks if a single utility class name is valid.
   *
   * Uses regular expression to validate that the class name follows
   * UtiliKit naming conventions with proper prefix, optional breakpoint,
   * property abbreviation, and value.
   *
   * @param string $class
   *   The utility class name to validate.
   *
   * @return bool
   *   TRUE if the class name follows UtiliKit naming conventions with proper
   *   prefix, optional breakpoint, property abbreviation, and value format.
   *   FALSE if the class name is invalid or malformed.
   *
   * @example
   * ```php
   * $this->assertTrue($this->isValidUtilityClass('uk-pd--20'));
   * $this->assertTrue($this->isValidUtilityClass('uk-md-mg--t-10'));
   * $this->assertFalse($this->isValidUtilityClass('invalid-class'));
   * ```
   */
  protected function isValidUtilityClass(string $class): bool {
    // UtiliKit class pattern: uk-[breakpoint-]property--value.
    $pattern = '/^uk-(?:(?:sm|md|lg|xl|xxl)-)?[a-z]{2,4}--[a-zA-Z0-9\-_.%]+$/';
    return preg_match($pattern, $class) === 1;
  }

  /**
   * Creates test CSS content for validation purposes.
   *
   * Generates basic CSS rules for the provided utility classes. Useful
   * for testing CSS validation methods and ensuring proper rule generation.
   *
   * @param array $classes
   *   Array of utility class names to create CSS for.
   *
   * @return string
   *   A CSS string containing basic rules for each provided utility class.
   *   Rules are generated based on common UtiliKit class patterns with
   *   appropriate property values and units.
   *
   * @example
   * ```php
   * $classes = ['uk-pd--20', 'uk-mg--10'];
   * $css = $this->createTestCss($classes);
   * // Returns: '.uk-pd--20 { padding: 20px; } .uk-mg--10 { margin: 10px; }'
   * ```
   */
  protected function createTestCss(array $classes): string {
    $css = '';

    foreach ($classes as $class) {
      // Generate basic CSS rule based on class pattern.
      if (strpos($class, 'uk-pd--') === 0) {
        $value = str_replace('uk-pd--', '', $class);
        $css .= ".{$class} { padding: {$value}px; } ";
      }
      elseif (strpos($class, 'uk-mg--') === 0) {
        $value = str_replace('uk-mg--', '', $class);
        $css .= ".{$class} { margin: {$value}px; } ";
      }
      elseif (strpos($class, 'uk-bg--') === 0) {
        $value = str_replace('uk-bg--', '', $class);
        $css .= ".{$class} { background-color: #{$value}; } ";
      }
      else {
        // Generic rule for other classes.
        $css .= ".{$class} { /* Rule for {$class} */ } ";
      }
    }

    return trim($css);
  }

  /**
   * Asserts that two arrays of utility classes are equivalent.
   *
   * Compares two arrays of utility classes, ignoring order but checking
   * for exact matches. Useful for testing class discovery and generation
   * algorithms.
   *
   * @param array $expected
   *   Expected array of utility classes.
   * @param array $actual
   *   Actual array of utility classes to compare.
   * @param string $message
   *   Optional assertion message.
   *
   * @example
   * ```php
   * $expected = ['uk-pd--20', 'uk-mg--10'];
   * $actual = ['uk-mg--10', 'uk-pd--20'];  // Different order
   * $this->assertUtilityClassesEqual($expected, $actual);
   * ```
   */
  protected function assertUtilityClassesEqual(array $expected, array $actual, string $message = ''): void {
    sort($expected);
    sort($actual);

    $default_message = 'Utility class arrays should be equivalent';
    $this->assertEquals($expected, $actual, $message ?: $default_message);
  }

}
