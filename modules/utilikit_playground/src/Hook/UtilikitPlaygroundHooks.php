<?php

namespace Drupal\utilikit_playground\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for utilikit_playground.
 */
class UtilikitPlaygroundHooks {

  /**
   * Implements hook_help().
   *
   * Provides contextual help throughout the Drupal admin interface.
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.playground_help') {
      return _utilikit_helper_render_readme();
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   *
   * Defines theme templates for the UtiliKit Playground interface.
   * Registers the main playground template with render element support
   * for flexible content rendering.
   * An array of theme hook definitions with template configurations.
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'utilikit_playground' => [
        'render element' => 'elements',
        'template' => 'utilikit-playground',
      ],
    ];
  }

  /**
   * Implements hook_page_attachments().
   *
   * Forces inline rendering mode for playground pages for immediate
   * visual feedback during utility class experimentation.
   *
   * @see _utilikit_attach_inline_settings()
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (\Drupal::routeMatch()->getRouteName() !== 'utilikit_playground.page') {
      return;
    }
    _utilikit_attach_inline_settings($attachments, 'utilikit_playground/playground');
  }

}
