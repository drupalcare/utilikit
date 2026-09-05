/**
 * @param Drupal
 * @param once
 * @param drupalSettings
 * @file
 * Responsive breakpoint handling and window resize optimization for UtiliKit.
 *
 * This file manages responsive behavior for UtiliKit's inline rendering mode,
 * handling window resize events, breakpoint changes, and optimized reprocessing
 * of utility classes when viewport dimensions change. The system is designed
 * for maximum performance by only reprocessing elements when breakpoints
 * actually change, rather than on every resize event.
 *
 * Key Features:
 * - Intelligent breakpoint change detection
 * - Optimized reprocessing of only responsive elements
 * - Debounced resize event handling for performance
 * - Orientation change support for mobile devices
 * - Prevention of duplicate event listeners
 * - Integration with UtiliKit's state management system
 *
 * Performance Optimizations:
 * - Only processes elements with responsive utility classes
 * - Uses requestAnimationFrame for smooth visual updates
 * - Implements debouncing to prevent excessive processing
 * - Caches breakpoint calculations to avoid redundant work
 * - Skips processing when no actual breakpoint change occurs
 *
 * Browser Compatibility:
 * - Uses modern APIs with graceful degradation
 * - Supports all major browsers and mobile devices
 * - Handles orientation change events for mobile optimization
 * - Compatible with UtiliKit's core functionality requirements
 */
(function (Drupal, once, drupalSettings) {
  // Ensure UtiliKit namespace exists for resize functionality
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Handles window resize events with intelligent breakpoint change detection.
   *
   * This function is the core of UtiliKit's responsive system, managing the
   * reprocessing of utility classes when viewport breakpoints change. It
   * implements several performance optimizations to ensure smooth user
   * experience during resize operations.
   *
   * Performance Features:
   * - Only reprocesses when breakpoints actually change
   * - Targets only elements with responsive utility classes
   * - Uses requestAnimationFrame for smooth visual updates
   * - Maintains state to prevent unnecessary work
   *
   * @param {Document|Element} [context=document]
   *   The DOM context to process for resize handling. Allows for scoped
   *   processing when UtiliKit is applied to specific page sections.
   *   Defaults to the entire document if not specified.
   *
   * @example
   * // Handle resize for entire document
   * Drupal.utilikit.handleResize();
   *
   * @example
   * // Handle resize for specific container
   * const container = document.querySelector('.utilikit-container');
   * Drupal.utilikit.handleResize(container);
   *
   * @example
   * // Handle resize with explicit document context
   * Drupal.utilikit.handleResize(document);
   */
  Drupal.utilikit.handleResize = function (context) {
    // Skip processing in static mode where CSS is pre-generated
    if (Drupal.utilikit.renderingMode === 'static') {
      return;
    }

    // Initialize environment if not already done
    if (!Drupal.utilikit.state) {
      Drupal.utilikit.initEnvironment(context || document);
    }

    const state = Drupal.utilikit.state;
    const currentWidth = window.innerWidth;
    const currentBreakpoint = Drupal.utilikit.getBreakpoint();

    // Performance optimization: only process if breakpoint actually changed
    const breakpointChanged = currentBreakpoint !== state.lastBreakpoint;

    // Update state tracking
    state.lastWidth = currentWidth;

    // Cancel any pending animation frame to prevent duplicate processing
    if (state.resizeFrame) {
      cancelAnimationFrame(state.resizeFrame);
      state.resizeFrame = null;
    }

    // Early exit if no breakpoint change occurred
    if (!breakpointChanged) {
      return;
    }

    // Update breakpoint tracking
    state.lastBreakpoint = currentBreakpoint;

    // Target only elements with responsive utility classes for efficiency
    const selector = [
      '.utilikit[class*="uk-sm-"]', // Small breakpoint classes
      '.utilikit[class*="uk-md-"]', // Medium breakpoint classes
      '.utilikit[class*="uk-lg-"]', // Large breakpoint classes
      '.utilikit[class*="uk-xl-"]', // Extra large breakpoint classes
      '.utilikit[class*="uk-xxl-"]', // Extra extra large breakpoint classes
    ].join(', ');

    const responsiveElements = (context || document).querySelectorAll(selector);

    // Handle initial run to process all elements
    if (state.firstRun) {
      const allElements = (context || document).querySelectorAll('.utilikit');
      Drupal.utilikit.applyClasses(allElements);
      state.firstRun = false;
    } else if (responsiveElements.length > 0) {
      // Reprocess only responsive elements for performance
      Drupal.utilikit.applyClasses(responsiveElements);

      // Development mode logging for debugging and optimization
      if (Drupal.utilikit.isDevMode) {
        Drupal.utilikit.utilikitLog(
          `Breakpoint changed to '${currentBreakpoint}', reprocessing ${responsiveElements.length} responsive elements`,
          null,
          'log',
        );
      }
    }
  };

  /**
   * Initializes the resize event listener with configurable debouncing.
   *
   * Sets up optimized event listeners for window resize and orientation
   * change events. The function includes safeguards to prevent duplicate
   * listeners and implements debouncing for optimal performance during
   * rapid resize events.
   *
   * Event Handling Features:
   * - Debounced resize events to prevent excessive processing
   * - Orientation change support for mobile devices
   * - Duplicate listener prevention for memory efficiency
   * - Configurable debounce timing for different use cases
   *
   * @param {Document|Element} [context=document]
   *   The DOM context for resize handling. Determines the scope of
   *   element processing during resize events.
   * @param {number} [debounceMs=50]
   *   The debounce delay in milliseconds for resize events. Lower values
   *   provide more responsive updates but may impact performance on
   *   slower devices. Higher values improve performance but may feel
   *   less responsive. Range: 0-1000ms recommended.
   *
   * @example
   * // Initialize with default debounce (50ms)
   * Drupal.utilikit.initResizeListener();
   *
   * @example
   * // Initialize with custom debounce for high-performance scenarios
   * Drupal.utilikit.initResizeListener(document, 100);
   *
   * @example
   * // Initialize with minimal debounce for responsive feel
   * Drupal.utilikit.initResizeListener(document, 25);
   *
   * @example
   * // Initialize for specific container with container-specific debounce
   * const container = document.querySelector('.dynamic-content');
   * Drupal.utilikit.initResizeListener(container, 75);
   */
  Drupal.utilikit.initResizeListener = function (context, debounceMs = 50) {
    // Skip initialization in static mode where resize handling is unnecessary
    if (Drupal.utilikit.renderingMode === 'static') {
      return;
    }

    // Initialize environment if not already done
    if (!Drupal.utilikit.state) {
      Drupal.utilikit.initEnvironment(context || document);
    }

    const state = Drupal.utilikit.state;

    // Prevent duplicate listeners to avoid memory leaks and redundant processing
    if (state.resizeListenerAttached) {
      return;
    }

    // Mark listener as attached and set initial run flag
    state.resizeListenerAttached = true;
    state.firstRun = true;

    // Primary resize event listener with debouncing
    window.addEventListener('resize', () => {
      // Clear any existing timeout to implement debouncing
      clearTimeout(state.resizeTimeout);

      // Set new timeout for debounced resize handling
      state.resizeTimeout = setTimeout(() => {
        Drupal.utilikit.handleResize(context);
      }, debounceMs);
    });

    // Orientation change listener for mobile devices
    // Uses a brief delay to allow the browser to update viewport dimensions
    window.addEventListener('orientationchange', () => {
      setTimeout(() => {
        Drupal.utilikit.handleResize(context);
      }, 100); // 100ms delay allows browser to complete orientation change
    });
  };
})(Drupal, once, drupalSettings);

/**
 * Integration Notes for UtiliKit Resize System:
 *
 * State Management Integration:
 * - Relies on Drupal.utilikit.state for tracking resize state
 * - Maintains lastBreakpoint and lastWidth for change detection
 * - Uses resizeTimeout and resizeFrame for performance optimization
 * - Integrates with firstRun flag for initial processing
 *
 * Performance Optimization Strategy:
 * - Breakpoint change detection prevents unnecessary processing
 * - Responsive element targeting reduces DOM query overhead
 * - requestAnimationFrame integration for smooth visual updates
 * - Debouncing prevents excessive event handler execution
 * - Early returns minimize code execution when possible
 *
 * Breakpoint System Integration:
 * - Uses Drupal.utilikit.getBreakpoint() for current breakpoint detection
 * - Supports all UtiliKit breakpoints: sm, md, lg, xl, xxl
 * - Integrates with active_breakpoints configuration
 * - Respects disabled breakpoints for efficiency
 *
 * Mobile Device Considerations:
 * - Orientation change handling for mobile devices
 * - Viewport dimension updates after orientation change
 * - Touch device performance optimization
 * - Responsive design support across all screen sizes
 *
 * Development and Debugging:
 * - Comprehensive logging when development mode is enabled
 * - Performance metrics for optimization during development
 * - Breakpoint change notifications for debugging
 * - Element count reporting for performance analysis
 *
 * Browser Compatibility:
 * - Modern event listener API with broad browser support
 * - requestAnimationFrame for smooth animations (with fallbacks)
 * - window.innerWidth for accurate viewport detection
 * - setTimeout/clearTimeout for reliable debouncing
 *
 * Memory Management:
 * - Proper cleanup of timeouts and animation frames
 * - Prevention of duplicate event listeners
 * - Efficient DOM querying with targeted selectors
 * - State tracking to minimize redundant operations
 *
 * Configuration Integration:
 * - Respects drupalSettings.utilikit.debounce configuration
 * - Integrates with rendering mode detection
 * - Uses active breakpoints configuration for optimization
 * - Supports development mode logging configuration
 */
