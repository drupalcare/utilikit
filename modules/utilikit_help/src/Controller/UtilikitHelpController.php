<?php

declare(strict_types=1);

namespace Drupal\utilikit_help\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * UtiliKit Help documentation controller.
 *
 * Renders the interactive help documentation page with tabbed navigation,
 * live examples, and reference materials for UtiliKit.
 */
class UtilikitHelpController extends ControllerBase {

  /**
   * Displays the main UtiliKit Help documentation page.
   *
   * @return array<string, mixed>
   *   A Drupal render array for the help page.
   */
  public function helpPage(): array {
    return [
      '#theme' => 'utilikit_help_page',
      '#attached' => [
        'library' => [
          'utilikit_help/help',
        ],
      ],
      '#cache' => [
        // Static docs; cache with user.permissions for access control.
        'max-age' => 3600,
        'contexts' => ['user.permissions', 'url.path'],
        'tags' => ['utilikit_help', 'config:utilikit.settings'],
      ],
    ];
  }

}
