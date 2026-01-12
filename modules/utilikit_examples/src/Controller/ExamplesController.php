<?php

declare(strict_types=1);

namespace Drupal\utilikit_examples\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for UtiliKit examples demonstration interface.
 *
 * Manages the rendering of interactive UtiliKit examples that demonstrate
 * practical usage patterns, component implementations, and real-world
 * applications of the utility-first CSS framework. This controller provides
 * a comprehensive showcase of UtiliKit capabilities through live examples
 * that users can interact with, copy, and adapt for their own projects.
 *
 * The examples interface serves multiple educational purposes:
 * - Demonstrates practical utility class combinations
 * - Shows real-world component implementations
 * - Provides copyable code samples for immediate use
 * - Illustrates responsive design patterns
 * - Showcases advanced techniques and best practices
 *
 * Features:
 * - Live interactive demonstrations with immediate visual feedback
 * - Copy-to-clipboard functionality for all code examples
 * - Responsive design showcases across different breakpoints
 * - Progressive complexity from basic to advanced implementations
 * - Integration with UtiliKit's inline rendering engine
 *
 * Educational Value:
 * - Bridges the gap between documentation and practical implementation
 * - Provides inspiration for common UI patterns and components
 * - Demonstrates effective utility class composition strategies
 * - Shows performance-optimized approaches to styling
 * - Illustrates accessibility best practices with utility classes
 *
 * Performance Considerations:
 * - Lightweight controller with minimal server-side processing
 * - Template-based rendering for optimal caching
 * - Client-side interactivity through JavaScript behaviors
 * - Efficient asset loading through Drupal's library system
 */
class ExamplesController extends ControllerBase {

  /**
   * Renders the UtiliKit examples demonstration page.
   *
   * Creates and returns a render array for the examples interface,
   * which provides interactive demonstrations of UtiliKit utility
   * classes through practical, real-world component examples.
   * The page serves as a comprehensive showcase and learning
   * resource for developers working with UtiliKit.
   *
   * Render Array Structure:
   * The method returns a minimal render array that delegates content
   * rendering to the theme system. This approach ensures:
   * - Consistent presentation through template-based rendering
   * - Separation of concerns between controller logic and presentation
   * - Flexibility for theme customization and override capabilities
   * - Efficient caching and performance optimization
   *
   * Template Integration:
   * The examples page integrates with the 'utilikit_examples' theme
   * template, which should provide:
   * - Organized example categories and navigation
   * - Interactive demonstration areas with live UtiliKit rendering
   * - Code display sections with copy-to-clipboard functionality
   * - Responsive breakpoint testing capabilities
   * - Progressive disclosure of complexity levels
   *
   * Content Organization:
   * Examples are typically organized by:
   * - Component type (buttons, cards, forms, navigation)
   * - Complexity level (basic, intermediate, advanced)
   * - Use case category (layout, typography, effects, responsive)
   * - Implementation pattern (atomic, molecular, organism-level)
   *
   * Interactive Features:
   * The rendered page should support:
   * - Live editing and preview of utility class combinations
   * - Real-time responsive breakpoint testing
   * - One-click copying of complete code examples
   * - Progressive enhancement from basic to advanced patterns
   * - Integration with UtiliKit's debugging and development tools
   *
   * @return array
   *   A Drupal render array containing:
   *   - #theme: The theme template name for examples page rendering
   *     Uses 'utilikit_examples' template for structured presentation
   *     of interactive demonstrations and code samples
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
