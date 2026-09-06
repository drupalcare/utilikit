<?php

declare(strict_types=1);

namespace Drupal\utilikit_playground\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides the UtiliKit Playground interactive development interface.
 *
 * Renders the playground page for real-time utility class experimentation,
 * live preview, and code generation.
 */
class PlaygroundController extends ControllerBase {

  /**
   * Renders the UtiliKit Playground interactive interface.
   *
   * @return array<string, mixed>
   *   A Drupal render array for the playground page.
   */
  public function preview(): array {
    return [
      '#theme' => 'utilikit_playground',
      '#attached' => [
        'library' => [
          'utilikit_playground/playground',
        ],
      ],
      '#cache' => [
        // Admin-only; max-age 0 so playground reflects current state.
        'max-age' => 0,
        'contexts' => ['user.permissions', 'url.path'],
        'tags' => ['utilikit_playground', 'config:utilikit.settings'],
      ],
    ];
  }

}
