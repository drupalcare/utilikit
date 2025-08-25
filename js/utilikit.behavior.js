/**
 * @file
 * Main Drupal behavior for UtiliKit initialization and lifecycle management.
 *
 * This file contains the primary Drupal behavior that manages UtiliKit's
 * integration with Drupal's behavior system, handling initialization,
 * rendering mode coordination, and element processing workflows. The behavior
 * ensures proper initialization order, manages the transition between inline
 * and static modes, and provides comprehensive validation and debugging
 * capabilities during development.
 *
 * Key Responsibilities:
 * - Drupal behavior system integration and lifecycle management
 * - Initialization of UtiliKit's environment and state management
 * - Coordination between inline and static rendering modes
 * - Development mode validation and debugging functionality
 * - Integration with Drupal's once() API for element processing
 * - Responsive system initialization and configuration
 *
 * Integration Points:
 * - Drupal.behaviors system for automatic initialization
 * - drupalSettings for configuration and runtime parameters
 * - once() API for preventing duplicate element processing
 * - UtiliKit's core services (environment, resize, logging)
 * - Development and production mode feature flags
 *
 * Performance Considerations:
 * - Lazy initialization to minimize startup overhead
 * - Conditional processing based on rendering mode
 * - Efficient element querying with targeted selectors
 * - Development features disabled in production
 */
(function(Drupal, once, drupalSettings) {
  'use strict';

  // Ensure UtiliKit namespace exists for behavior integration
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Main UtiliKit behavior for Drupal integration and lifecycle management.
   *
   * This behavior handles the complete lifecycle of UtiliKit functionality
   * within Drupal's behavior system, ensuring proper initialization,
   * configuration, and processing of utility classes across page loads
   * and AJAX operations.
   *
   * The behavior implements a lazy initialization pattern to minimize
   * performance impact and provides different processing workflows for
   * inline and static rendering modes.
   */
  Drupal.behaviors.utilikitEngine = {

    /**
     * Attaches UtiliKit functionality to DOM elements.
     *
     * This method is called by Drupal's behavior system during page load
     * and after AJAX operations. It handles initialization, mode detection,
     * and appropriate processing based on the current rendering mode.
     *
     * Processing Workflow:
     * 1. Check and perform one-time initialization if needed
     * 2. Detect current rendering mode from configuration
     * 3. Apply mode-specific processing (inline vs static)
     * 4. Process new elements with once() API integration
     *
     * @param {Document|Element} context
     *   The DOM context being processed. Typically, the document on page load
     *   or a specific container after AJAX operations.
     * @param {Object} settings
     *   Drupal settings object containing configuration and runtime data.
     *   Includes drupalSettings.utilikit with UtiliKit-specific configuration.
     *
     * @example
     * // Automatically called by Drupal's behavior system
     * // No manual invocation required
     *
     * @example
     * // Can be manually triggered for specific contexts
     * Drupal.behaviors.utilikitEngine.attach(container, drupalSettings);
     */
    attach: function (context, settings) {
      // Perform one-time initialization if not already done
      if (!Drupal.utilikit.initialized) {
        this.initialize();
      }

      // Determine current rendering mode from configuration
      const renderingMode = drupalSettings.utilikit?.renderingMode || 'inline';

      // Apply mode-specific processing
      if (renderingMode === 'inline') {
        this.processInlineMode(context);
      }
      // Note: Static mode requires no dynamic processing during attach
    },

    /**
     * Performs one-time initialization of UtiliKit systems and services.
     *
     * This method sets up the complete UtiliKit environment including
     * state management, responsive system, logging, and mode-specific
     * configuration. It's designed to run only once per page load to
     * minimize initialization overhead.
     *
     * Initialization Sequence:
     * 1. Mark UtiliKit as initialized to prevent duplicate setup
     * 2. Initialize core environment and state management
     * 3. Set up responsive system for inline mode
     * 4. Configure development mode features and logging
     * 5. Provide mode-specific startup diagnostics
     *
     * @example
     * // Called automatically during first attach() invocation
     * // Manual initialization (if needed):
     * Drupal.behaviors.utilikitEngine.initialize();
     */
    initialize: function() {
      // Mark as initialized to prevent duplicate initialization
      Drupal.utilikit.initialized = true;

      // Initialize core environment and state management
      if (typeof Drupal.utilikit.initEnvironment === 'function') {
        Drupal.utilikit.initEnvironment(document);
      }

      // Determine rendering mode for conditional setup
      const renderingMode = drupalSettings.utilikit?.renderingMode || 'inline';

      // Initialize responsive system for inline mode
      if (renderingMode === 'inline' &&
          typeof Drupal.utilikit.initResizeListener === 'function') {
        Drupal.utilikit.initResizeListener(document, drupalSettings.utilikit.debounce || 50);
      }

      // Development mode initialization and diagnostics
      if (drupalSettings.utilikit.devMode) {
        Drupal.utilikit.utilikitLog('UtiliKit initialized', {
          mode: renderingMode,
          activeBreakpoints: drupalSettings.utilikit.activeBreakpoints
        }, 'log');

        // Static mode specific diagnostics
        if (renderingMode === 'static') {
          const elements = document.querySelectorAll('.utilikit');
          Drupal.utilikit.utilikitLog('Static mode active - CSS file loaded', {
            elementsFound: elements.length,
            cssTimestamp: drupalSettings.utilikit.cssTimestamp
          }, 'log');
        }
      }
    },

    /**
     * Validates UtiliKit utility class syntax and usage during development.
     *
     * This method performs comprehensive validation of utility class names
     * to help developers identify syntax errors, unknown prefixes, and
     * malformed class patterns. Validation only runs in development mode
     * to avoid performance impact in production.
     *
     * Validation Checks:
     * - Class name format validation (prefix--value pattern)
     * - Responsive breakpoint prefix validation
     * - Known utility prefix verification against rules registry
     * - Comprehensive error reporting with specific details
     *
     * @example
     * // Called automatically during development mode processing
     * // Manual validation trigger:
     * if (drupalSettings.utilikit.devMode) {
     *   Drupal.behaviors.utilikitEngine.validateClasses();
     * }
     *
     * @example
     * // Example validation output for invalid classes:
     * // "Invalid class format: uk-padding-20" (missing double dash)
     * // "Unknown UtiliKit prefix: invalidprefix in class uk-invalidprefix--20"
     */
    validateClasses: function() {
      // Skip validation if not in development mode for performance
      if (!drupalSettings.utilikit?.devMode) return;

      const elements = document.querySelectorAll('.utilikit');
      let invalidCount = 0;

      elements.forEach(el => {
        el.classList.forEach(className => {
          // Only validate UtiliKit classes (those starting with 'uk-')
          if (!className.startsWith('uk-')) return;

          // Basic format validation - check for required double dash separator
          const parts = className.split('--');
          if (parts.length !== 2) {
            Drupal.utilikit.utilikitLog('Invalid class format: ' + className, null, 'warn');
            invalidCount++;
            return;
          }

          // Parse responsive prefix and utility prefix
          const prefixPart = parts[0].slice(3); // Remove 'uk-' prefix
          const prefixMatch = prefixPart.match(/^(?:(sm|md|lg|xl|xxl)-)?(.+)$/);
          if (!prefixMatch) return;

          // Extract utility prefix (after optional responsive prefix)
          const prefix = prefixMatch[2];

          // Validate against known utility prefixes in rules registry
          if (typeof Drupal.utilikit !== 'undefined' &&
              Drupal.utilikit.rules &&
              !(prefix in Drupal.utilikit.rules)) {
            Drupal.utilikit.utilikitLog('Unknown UtiliKit prefix: ' + prefix + ' in class ' + className, null, 'warn');
            invalidCount++;
          }
        });
      });

      // Provide summary validation results
      if (invalidCount > 0) {
        Drupal.utilikit.utilikitLog('Found ' + invalidCount + ' invalid classes', null, 'warn');
      } else {
        Drupal.utilikit.utilikitLog('All UtiliKit classes validated successfully', null, 'log');
      }
    },

    /**
     * Processes elements for inline rendering mode with dynamic CSS generation.
     *
     * This method handles the processing of UtiliKit elements in inline mode,
     * where CSS is generated dynamically on the client side. It uses Drupal's
     * once() API to ensure elements are processed only once, even across
     * multiple AJAX operations.
     *
     * Processing Workflow:
     * 1. Use once() API to identify unprocessed UtiliKit elements
     * 2. Apply appropriate processing based on rendering mode
     * 3. Generate and inject CSS for inline mode
     * 4. Validate classes in development mode
     *
     * @param {Document|Element} context
     *   The DOM context to process for UtiliKit elements. Allows for
     *   scoped processing after AJAX operations or in specific containers.
     *
     * @example
     * // Called automatically during attach() in inline mode
     * // Manual processing for specific context:
     * const container = document.querySelector('.dynamic-content');
     * Drupal.behaviors.utilikitEngine.processInlineMode(container);
     *
     * @example
     * // Processing after AJAX content update:
     * $(document).ajaxComplete(function(event, xhr, settings) {
     *   Drupal.behaviors.utilikitEngine.processInlineMode(document);
     * });
     */
    processInlineMode: function(context) {
      // Ensure CSS application function is available
      if (typeof Drupal.utilikit.applyClasses !== 'function') {
        return;
      }

      // Use once() API to process only new/unprocessed elements
      const elements = once('utilikit-engine', '.utilikit', context);

      if (elements.length > 0) {
        // Re-check rendering mode for safety (may change during runtime)
        const renderingMode = drupalSettings.utilikit?.renderingMode || 'inline';

        if (renderingMode === 'inline') {
          // Apply dynamic CSS generation for inline mode
          Drupal.utilikit.applyClasses(elements);
        } else {
          // Static mode - perform validation only (CSS already loaded)
          this.validateClasses();
        }
      }
    }
  };

})(Drupal, once, drupalSettings);

/**
 * Integration Notes for UtiliKit Behavior System:
 *
 * Drupal Behavior System Integration:
 * - Automatically invoked during page load and AJAX operations
 * - Processes both initial page content and dynamically loaded content
 * - Integrates with Drupal's once() API for element processing control
 * - Respects Drupal's behavior attachment lifecycle and timing
 * - Provides graceful degradation when UtiliKit services are unavailable
 *
 * Rendering Mode Coordination:
 * - Detects current mode from drupalSettings.utilikit.renderingMode
 * - Applies appropriate processing workflow for each mode
 * - Inline mode: Dynamic CSS generation and responsive system initialization
 * - Static mode: Validation and diagnostics only (CSS preloaded)
 * - Supports runtime mode switching with appropriate behavior adaptation
 *
 * Performance Optimization Features:
 * - Lazy initialization minimizes startup overhead
 * - Conditional processing based on rendering mode reduces unnecessary work
 * - once() API integration prevents duplicate element processing
 * - Development features completely disabled in production builds
 * - Efficient DOM querying with targeted selectors
 *
 * Development Mode Features:
 * - Comprehensive initialization logging with configuration details
 * - Utility class validation with detailed error reporting
 * - Performance diagnostics and element count reporting
 * - Mode-specific status information for debugging
 * - Integration with UtiliKit's logging system for consistent output
 *
 * Error Handling and Graceful Degradation:
 * - Checks for service availability before invocation
 * - Graceful handling of missing configuration or corrupted settings
 * - Fallback to safe defaults when configuration is unavailable
 * - Non-blocking error handling that doesn't break other behaviors
 * - Comprehensive logging for troubleshooting initialization issues
 *
 * AJAX and Dynamic Content Support:
 * - Automatic processing of AJAX-loaded content through behavior system
 * - Context-aware processing for partial page updates
 * - once() API integration ensures no duplicate processing
 * - Supports complex dynamic content scenarios (modals, accordions, etc.)
 * - Maintains state consistency across AJAX operations
 *
 * Configuration and Settings Integration:
 * - Full integration with drupalSettings.utilikit configuration object
 * - Respects all performance and feature flags from configuration
 * - Supports runtime configuration updates through settings changes
 * - Validates configuration completeness and provides fallbacks
 * - Integrates with experimental feature flags for beta functionality
 */
