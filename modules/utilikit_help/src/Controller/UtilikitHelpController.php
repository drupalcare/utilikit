<?php

declare(strict_types=1);

namespace Drupal\utilikit_help\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * UtiliKit Help documentation controller.
 *
 * Provides comprehensive, interactive documentation for UtiliKit module
 * with user-friendly examples, responsive design, and educational content.
 * This controller manages the rendering of the main help interface which
 * includes tabbed navigation, live examples, and detailed reference
 * materials for learning and using UtiliKit effectively.
 *
 * The documentation system includes:
 * - Getting started tutorials with step-by-step guidance
 * - Interactive examples and live demonstrations
 * - Comprehensive reference documentation for all utilities
 * - Responsive design testing and breakpoint explanations
 * - Developer tools and debugging information
 * - Troubleshooting guides and common solutions
 * - Best practices and advanced techniques
 *
 * Controller Features:
 * - Lightweight rendering with minimal server processing
 * - Efficient caching strategy for optimal performance
 * - Integration with UtiliKit's core library system
 * - Responsive vertical tab interface for organized content
 * - Accessible design with proper ARIA support
 *
 * Performance Considerations:
 * - Cached for 1 hour to reduce server load
 * - Static content with client-side interactivity
 * - Optimized asset loading through Drupal's library system
 * - Context-aware caching with proper cache tags
 */
class UtilikitHelpController extends ControllerBase {

  /**
   * Displays the main UtiliKit Help documentation page.
   *
   * Renders the comprehensive help documentation interface with tabbed
   * navigation, interactive examples, and detailed reference materials.
   * The page provides a complete learning environment for UtiliKit users
   * with progressive disclosure of information through vertical tabs.
   *
   * Render Array Structure:
   * The method returns a structured render array that integrates with
   * Drupal's theme system to provide:
   * - Theme template integration for consistent presentation
   * - Library attachment for JavaScript interactivity
   * - Caching configuration for optimal performance
   * - Cache context and tags for proper invalidation
   *
   * Content Organization:
   * The help page includes multiple content sections:
   * - Getting Started: Basic concepts and first steps
   * - How It Works: Technical explanation of UtiliKit operation
   * - Developer Mode: Debugging tools and development features
   * - Real Examples: Live component demonstrations
   * - Responsive Design: Breakpoint guides and responsive utilities
   * - Troubleshooting: Common issues and solutions
   * - Pro Tips: Advanced techniques and best practices
   * - Reference Materials: Complete utility and unit documentation
   * - Quick Reference: Cheat sheet for common patterns
   * - Limitations: Known constraints and unsupported features
   *
   * Caching Strategy:
   * - 1-hour cache for static documentation content
   * - URL path context to handle different access patterns
   * - Cache tags for selective invalidation when related data changes
   * - Efficient serving of frequently accessed documentation
   *
   * @return array
   *   A structured Drupal render array containing:
   *   - #theme: The template name for the help page interface
   *   - #attached: Asset attachments including required libraries
   *     - library: JavaScript and CSS libraries for interactivity
   *   - #cache: Caching configuration for performance optimization
   *     - max-age: Cache duration in seconds (3600 = 1 hour)
   *     - contexts: Cache contexts for request differentiation
   *     - tags: Cache tags for selective invalidation
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
        // Static documentation can be cached, but include user.permissions context
        // since page access is permission-protected.
        'max-age' => 3600,
        'contexts' => ['user.permissions', 'url.path'],
        'tags' => ['utilikit_help', 'config:utilikit.settings'],
      ],
    ];
  }

}
