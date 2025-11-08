<?php

declare(strict_types=1);

namespace Drupal\utilikit_playground\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides the UtiliKit Playground interactive development interface.
 *
 * This controller manages the rendering of the UtiliKit Playground, an
 * interactive tool designed for experimenting with utility classes in
 * real-time. The playground serves as both a learning environment and
 * a practical development aid for users working with UtiliKit's
 * utility-first CSS system.
 *
 * The playground interface provides:
 * - Live editing capabilities with immediate preview
 * - Interactive utility class experimentation
 * - Real-time CSS application and validation
 * - Code generation for production implementation
 * - Responsive design testing across breakpoints
 * - Educational guidance and usage examples
 *
 * Controller Responsibilities:
 * - Renders the playground interface template
 * - Attaches necessary JavaScript libraries and assets
 * - Provides structured render array for theme system
 * - Ensures proper integration with UtiliKit's core systems
 *
 * Access Control:
 * - Requires 'access utilikit tools' permission
 * - Intended for developers, designers, and content creators
 * - Provides educational and development-focused functionality
 * - Safe environment for experimentation without production impact
 *
 * Performance Considerations:
 * - Lightweight controller with minimal processing overhead
 * - Relies on client-side JavaScript for interactive functionality
 * - Efficient asset loading and caching through Drupal's library system
 * - Optimized for development and learning environments
 */
class PlaygroundController extends ControllerBase {

  /**
   * Renders the UtiliKit Playground interactive interface.
   *
   * Generates the playground page with all necessary assets and configuration
   * for interactive utility class testing and experimentation. The method
   * creates a structured render array that integrates with Drupal's theme
   * system and attaches the playground-specific JavaScript library.
   *
   * The playground interface includes:
   * - Interactive HTML/CSS editor with live preview
   * - Utility class testing and validation environment
   * - Real-time visual feedback and code generation
   * - Responsive breakpoint testing capabilities
   * - Educational resources and usage examples
   * - Integration with UtiliKit's core CSS application system
   *
   * Rendering Process:
   * 1. Creates render array with playground theme template
   * 2. Attaches playground JavaScript library for interactivity
   * 3. Integrates with UtiliKit's core systems for CSS processing
   * 4. Provides structured interface for user experimentation
   *
   * Asset Management:
   * The playground relies on the attached library to provide:
   * - Interactive JavaScript functionality
   * - CSS styles for interface presentation
   * - Integration with UtiliKit's core rendering engine
   * - Responsive design and accessibility features
   *
   * @return array
   *   A Drupal render array containing the playground interface:
   *   - #theme: References the utilikit_playground theme template
   *   - #attached: Includes necessary JavaScript libraries and assets
   *     - library: Array containing the playground library dependency
   *
   * @throws \Exception
   *   May throw exceptions if theme template is not found or if
   *   required assets cannot be loaded during rendering.
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
      // Cache for 1 hour during development.
        'max-age' => 3600,
        'contexts' => ['url.path'],
        'tags' => ['utilikit_playground', 'utilikit_settings'],
      ],
    ];
  }

}
