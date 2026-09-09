<?php

namespace Drupal\utilikit_help\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for utilikit_help.
 */
class UtilikitHelpHooks {

  /**
   * Implements hook_help().
   *
   * Provides contextual help throughout the Drupal admin interface.
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.utilikit_help') {
      return _utilikit_helper_render_readme();
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'utilikit_help_page' => [
        'variables' => [],
        'template' => 'utilikit-help-page',
      ],
    ];
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (\Drupal::routeMatch()->getRouteName() !== 'utilikit_help.documentation') {
      return;
    }
    _utilikit_attach_inline_settings($attachments, 'utilikit_help/help');
  }

}
