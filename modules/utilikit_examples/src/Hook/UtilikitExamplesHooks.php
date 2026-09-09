<?php

namespace Drupal\utilikit_examples\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for utilikit_examples.
 */
class UtilikitExamplesHooks {

  /**
   * Implements hook_help().
   *
   * Provides contextual help throughout the Drupal admin interface.
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.utilikit_examples') {
      return _utilikit_helper_render_readme();
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   *
   * Defines theme templates for UtiliKit examples display.
   *
   *   An array of existing implementations that may be used for override
   *   purposes. This is primarily useful for themes that may wish to
   *   examine existing implementations to extract data (such as arguments)
   *   so that it may properly register its own, higher priority
   *   implementations.
   *   Whether a theme, module, etc. is being processed.
   *   The actual name of theme, module, etc. that is being being processed.
   *   The directory path of the theme or module, so that it doesn't need to
   *   be looked up.
   *
   *   An associative array of theme hook information. The key is the theme
   *   hook name, and the value is an associative array containing:
   *   - variables: An array of variables that the template uses.
   *   - template: The name of the template file to use.
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'utilikit_examples' => [
        'variables' => [],
        'template' => 'utilikit-examples',
      ],
    ];
  }

  /**
   * Implements hook_page_attachments().
   *
   * Forces inline rendering mode for examples pages regardless of global
   * settings to ensure consistent demonstration display.
   *
   * @see _utilikit_attach_inline_settings()
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (\Drupal::routeMatch()->getRouteName() !== 'utilikit_examples.page') {
      return;
    }
    _utilikit_attach_inline_settings($attachments, 'utilikit_examples/utilikit.examples');
  }

}
