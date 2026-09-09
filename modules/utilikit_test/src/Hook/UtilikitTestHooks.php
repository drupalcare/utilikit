<?php

namespace Drupal\utilikit_test\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for utilikit_test.
 */
class UtilikitTestHooks {

  /**
   * Implements hook_help().
   *
   * Provides contextual help throughout the Drupal admin interface.
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.utilikit_test') {
      return _utilikit_helper_render_readme();
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   *
   * Registers theme definitions for the UtiliKit test suite interface,
   * providing the template and variable structure needed for comprehensive
   * test result presentation and user interaction.
   *
   * The theme registration enables the TestSuiteController to render
   * organized test data through the Drupal theme system, creating a
   * structured interface for test execution, monitoring, and result analysis.
   *
   * @see TestSuiteController::render()
   * @see utilikit-test-suite.html.twig
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'utilikit_test_suite' => [
        'variables' => [
          'test_groups' => [],
          'statistics' => [],
        ],
        'template' => 'utilikit-test-suite',
      ],
    ];
  }

  /**
   * Implements hook_page_attachments().
   *
   * Forces inline rendering mode for test suite pages to ensure consistent
   * and predictable test execution regardless of global settings.
   *
   * @see _utilikit_attach_inline_settings()
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (\Drupal::routeMatch()->getRouteName() !== 'utilikit_test.suite') {
      return;
    }
    _utilikit_attach_inline_settings($attachments, 'utilikit_test/test.suite');
  }

}
