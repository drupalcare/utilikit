<?php

namespace Drupal\utilikit_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\utilikit_test\Service\TestGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the UtiliKit Test Suite administrative interface.
 *
 * This controller manages the rendering and presentation of the comprehensive
 * UtiliKit test suite, which validates all utility classes and provides
 * detailed feedback about CSS generation behavior. The test suite is
 * organized by utility categories and includes statistical analysis
 * for development and debugging purposes.
 *
 * The controller coordinates between the TestGenerator service (which
 * produces test cases) and the theme system (which renders the interactive
 * test interface) to provide a seamless testing experience for developers.
 *
 * Access Control:
 * - Requires 'access utilikit tools' permission
 * - Intended for developers and site administrators
 * - Provides detailed technical information about UtiliKit behavior
 *
 * Performance Considerations:
 * - Generates comprehensive test suites that may include hundreds of tests
 * - Uses efficient grouping and organization for optimal rendering
 * - Caches test generation results where appropriate
 * - Designed for development environments rather than production use
 */
class TestSuiteController extends ControllerBase {

  /**
   * The UtiliKit test generator service.
   *
   * This service is responsible for generating comprehensive test cases
   * for all UtiliKit utility rules. It provides systematic test coverage
   * across all value types, responsive breakpoints, and edge cases to
   * ensure robust validation of the UtiliKit CSS generation system.
   *
   * @var \Drupal\utilikit_test\Service\TestGenerator
   */
  protected TestGenerator $testGenerator;

  /**
   * Creates a new TestSuiteController instance with dependency injection.
   *
   * This factory method implements Drupal's dependency injection pattern
   * to ensure the controller receives all required services. The method
   * creates the parent controller instance and injects the TestGenerator
   * service for test case generation.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The dependency injection container containing all registered services.
   *
   * @return static
   *   A fully configured TestSuiteController instance with all dependencies
   *   properly injected and ready for use.
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->testGenerator = $container->get('utilikit_test.test_generator');
    return $instance;
  }

  /**
   * Renders the comprehensive UtiliKit test suite page.
   *
   * Generates and organizes all UtiliKit test cases into a structured
   * interface that allows developers to validate utility class behavior,
   * view test statistics, and identify potential issues with CSS generation.
   *
   * The method performs the following operations:
   * 1. Generates all available test cases using the TestGenerator service
   * 2. Calculates comprehensive statistics about test coverage
   * 3. Organizes tests by utility category for improved navigation
   * 4. Prepares render array for theme system processing
   *
   * Test Organization:
   * - Groups tests by utility category (Box Model, Typography, Colors, etc.)
   * - Maintains test metadata for detailed analysis
   * - Preserves test case ordering for consistent presentation
   * - Includes statistical summaries for each category
   *
   * @return array
   *   A Drupal render array containing the complete test suite interface:
   *   - #theme: References the utilikit_test_suite theme template
   *   - #test_groups: Organized test cases grouped by utility category
   *   - #statistics: Comprehensive statistics for test coverage & distribution
   *
   * @throws \Exception
   *   May throw exceptions if test generation fails or if required services
   *   are unavailable during the test case generation process.
   *
   * @see TestGenerator::generateAllTests()
   * @see TestGenerator::getTestStatistics()
   */
  public function render(): array {
    // Generate comprehensive test cases for all UtiliKit rules.
    $allTests = $this->testGenerator->generateAllTests();

    // Calculate detailed statistics about test coverage and distribution.
    $statistics = $this->testGenerator->getTestStatistics();

    // Organize tests by utility category for improved navigation and
    // presentation.
    $groupedTests = [];
    foreach ($allTests as $ruleTest) {
      $group = $ruleTest['group'];
      if (!isset($groupedTests[$group])) {
        $groupedTests[$group] = [];
      }
      $groupedTests[$group][] = $ruleTest;
    }

    // Return structured render array for theme system processing.
    return [
      '#theme' => 'utilikit_test_suite',
      '#test_groups' => $groupedTests,
      '#statistics' => $statistics,
      '#cache' => [
    // Cache for 1 hour during development.
        'max-age' => 3600,
        'contexts' => ['url.path'],
        'tags' => ['utilikit_test_suite', 'utilikit_settings'],
      ],
    ];
  }

}
