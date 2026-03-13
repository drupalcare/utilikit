<?php

declare(strict_types=1);

namespace Drupal\utilikit_examples\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for UtiliKit examples demonstration interface.
 *
 * Renders interactive examples showcasing UtiliKit utility class usage
 * patterns and real-world component implementations.
 */
class ExamplesController extends ControllerBase {

  /**
   * Renders the UtiliKit examples demonstration page.
   *
   * @return array
   *   A Drupal render array for the examples page.
   */
  public function render(): array {
    return [
      '#theme' => 'utilikit_examples',
      '#cache' => [
        // Admin-only examples page - use max-age 0 to prevent caching issues.
        // Content is permission-protected and may contain interactive elements.
        'max-age' => 0,
        'contexts' => ['user.permissions', 'url.path'],
        'tags' => ['utilikit_examples', 'config:utilikit.settings'],
      ],
    ];
  }

}
