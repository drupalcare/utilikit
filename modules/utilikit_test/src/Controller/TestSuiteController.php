<?php

declare(strict_types=1);

namespace Drupal\utilikit_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\utilikit_test\Service\TestGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the UtiliKit Test Suite administrative interface.
 *
 * Renders the test suite page that validates all utility classes and
 * displays CSS generation behavior organized by category.
 */
class TestSuiteController extends ControllerBase {

  /**
   * Constructs a new TestSuiteController object.
   *
   * @param \Drupal\utilikit_test\Service\TestGenerator $testGenerator
   *   The UtiliKit test generator service.
   */
  public function __construct(
    protected readonly TestGenerator $testGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('utilikit_test.test_generator'),
    );
  }

  /**
   * Renders the UtiliKit test suite page.
   *
   * Generates test cases, calculates statistics, and groups tests
   * by utility category for the test suite interface.
   *
   * @return array
   *   A Drupal render array with test groups and statistics.
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
        // Admin-only test suite - max-age 0 so tests reflect current config.
        'max-age' => 0,
        'contexts' => ['user.permissions', 'url.path'],
        'tags' => ['utilikit_test_suite', 'config:utilikit.settings'],
      ],
    ];
  }

}
