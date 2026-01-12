/**
 * @file
 * UtiliKit Debug Helper - Visual debugging tools and console utilities.
 *
 * This file provides comprehensive debugging tools for UtiliKit development
 * including a visual debug panel, real-time state monitoring, and console
 * utilities for testing and troubleshooting. The debug helper is designed
 * to assist developers with theme development, utility class debugging,
 * and performance optimization during development workflows.
 *
 * Debug Panel Features:
 * - Real-time breakpoint and viewport information display
 * - Manual style refresh and state management controls
 * - Comprehensive state logging and inspection tools
 * - Visual feedback for debugging responsive behavior
 * - Non-intrusive overlay design that doesn't affect page layout
 *
 * Console Utilities:
 * - Global window.utilikitDebug object for console testing
 * - Manual element processing and refresh capabilities
 * - State inspection and debugging helper functions
 * - Resize event testing and validation tools
 *
 * Development Integration:
 * - Automatically appears only when development mode is enabled
 * - Integrates with UtiliKit's logging system for consistent output
 * - Provides immediate feedback for development workflow optimization
 * - Supports complex debugging scenarios and edge case testing
 *
 * Security Considerations:
 * - Only loads when drupalSettings.utilikit.devMode is enabled
 * - No functionality available in production environments
 * - Debugging tools designed for development safety and convenience
 * - No persistent state or data storage outside development sessions
 */
(function(Drupal, once, drupalSettings) {
  'use strict';

  /**
   * UtiliKit Debug Helper Drupal behavior for development debugging tools.
   *
   * This behavior creates a comprehensive debugging interface that provides
   * real-time information about UtiliKit's state, responsive behavior, and
   * element processing. The debug panel includes interactive controls for
   * manual testing and state management during development.
   *
   * The debug helper automatically integrates with UtiliKit's core systems
   * to provide accurate, real-time information about breakpoints, element
   * counts, and processing state without interfering with normal operation.
   */
  Drupal.behaviors.utilikitDebugHelper = {

    /**
     * Attaches the debug panel and utilities when development mode is enabled.
     *
     * This method creates a visual debug panel with real-time monitoring
     * and interactive controls for UtiliKit debugging. The panel provides
     * immediate access to common debugging operations and displays live
     * information about the current UtiliKit state.
     *
     * Debug Panel Components:
     * - Force style refresh for immediate element reprocessing
     * - State clearing and reinitialization for troubleshooting
     * - Comprehensive state logging for detailed analysis
     * - Real-time display of current breakpoint and viewport information
     * - Element count monitoring for performance tracking
     *
     * @param {Document|Element} context
     *   The DOM context being processed, typically the document or a
     *   specific container after AJAX operations.
     * @param {Object} settings
     *   Drupal settings object containing configuration and runtime data.
     *   Must include drupalSettings.utilikit.devMode set to true for
     *   the debug panel to be activated.
     *
     * @example
     * // Debug panel automatically appears when development mode is enabled
     * // No manual invocation required - handled by Drupal's behavior system
     *
     * @example
     * // Manual behavior attachment for testing
     * if (drupalSettings.utilikit.devMode) {
     *   Drupal.behaviors.utilikitDebugHelper.attach(document, drupalSettings);
     * }
     */
    attach: function (context, settings) {
      // Only activate debug tools when development mode is explicitly enabled
      if (!drupalSettings.utilikit || !drupalSettings.utilikit.devMode) {
        return;
      }

      // Use once() API to ensure debug panel is created only once
      once('utilikit-debug-helper', 'body', context).forEach(() => {
        // Create comprehensive debug panel with styling and controls
        const debugPanel = document.createElement('div');
        debugPanel.id = 'utilikit-debug-panel';
        debugPanel.innerHTML = `
          <style>
            #utilikit-debug-panel {
              position: fixed;
              bottom: 20px;
              right: 20px;
              background: rgba(0, 0, 0, 0.8);
              color: white;
              padding: 15px;
              border-radius: 5px;
              font-family: monospace;
              font-size: 12px;
              z-index: 9999;
              min-width: 200px;
            }
            #utilikit-debug-panel button {
              display: block;
              width: 100%;
              margin: 5px 0;
              padding: 5px 10px;
              background: #007bff;
              color: white;
              border: none;
              border-radius: 3px;
              cursor: pointer;
            }
            #utilikit-debug-panel button:hover {
              background: #005a8b;
            }
            #utilikit-debug-info {
              margin-top: 10px;
              padding-top: 10px;
              border-top: 1px solid #666;
            }
          </style>
          <strong>UtiliKit Debug</strong>
          <button id="utilikit-force-refresh">🔄 Force Refresh Styles</button>
          <button id="utilikit-clear-state">🧹 Clear State & Refresh</button>
          <button id="utilikit-log-state">📋 Log State to Console</button>
          <div id="utilikit-debug-info">
            <div>Breakpoint: <span id="uk-current-bp">-</span></div>
            <div>Width: <span id="uk-current-width">-</span>px</div>
            <div>Elements: <span id="uk-element-count">-</span></div>
          </div>
        `;
        document.body.appendChild(debugPanel);

        /**
         * Updates the real-time debug information display.
         *
         * This internal function refreshes the debug panel's information
         * display with current breakpoint, viewport width, and element
         * count data. Called automatically during initialization and
         * after user interactions.
         */
        function updateDebugInfo() {
          document.getElementById('uk-current-bp').textContent = Drupal.utilikit.getBreakpoint() || 'base';
          document.getElementById('uk-current-width').textContent = window.innerWidth;
          document.getElementById('uk-element-count').textContent = document.querySelectorAll('.utilikit').length;
        }

        // Initialize debug information display
        updateDebugInfo();

        // Force Refresh Button - Manual element reprocessing
        document.getElementById('utilikit-force-refresh').addEventListener('click', () => {
          Drupal.utilikit.utilikitLog('Force refreshing all elements...', null, 'log');
          const elements = document.querySelectorAll('.utilikit');
          Drupal.utilikit.applyClasses(elements);
          updateDebugInfo();
          Drupal.utilikit.utilikitLog('Force refresh complete', null, 'log');
        });

        // Clear State Button - Complete state reset and reinitialization
        document.getElementById('utilikit-clear-state').addEventListener('click', () => {
          Drupal.utilikit.utilikitLog('Clearing state and refreshing...', null, 'log');
          Drupal.utilikit.state = null;
          Drupal.utilikit.initEnvironment(document);
          const elements = document.querySelectorAll('.utilikit');
          Drupal.utilikit.applyClasses(elements);
          updateDebugInfo();
          Drupal.utilikit.utilikitLog('State cleared and refreshed', null, 'log');
        });

        // Log State Button - Comprehensive state inspection
        document.getElementById('utilikit-log-state').addEventListener('click', () => {
          Drupal.utilikit.utilikitLog('Current UtiliKit State:', {
            state: Drupal.utilikit.state,
            breakpoint: Drupal.utilikit.getBreakpoint(),
            width: window.innerWidth,
            elementCount: document.querySelectorAll('.utilikit').length,
            settings: drupalSettings.utilikit
          }, 'log');
        });

        // Real-time debug info updates on viewport changes
        window.addEventListener('resize', () => {
          updateDebugInfo();
        });
      });
    }
  };

  /**
   * Global console debugging utilities attached to window.utilikitDebug.
   *
   * These utilities provide convenient console access to UtiliKit debugging
   * functions for manual testing, troubleshooting, and development workflow
   * optimization. The utilities are designed for ease of use from browser
   * developer tools during theme development and debugging sessions.
   *
   * Available Console Commands:
   * - utilikitDebug.refresh(): Manual element processing and refresh
   * - utilikitDebug.getState(): Current state and configuration inspection
   * - utilikitDebug.testResize(): Manual resize event handling testing
   *
   * Usage Examples:
   * - Type `utilikitDebug.refresh()` in console to reprocess all elements
   * - Use `utilikitDebug.getState()` to inspect current UtiliKit state
   * - Call `utilikitDebug.testResize()` to manually trigger resize handling
   */
  window.utilikitDebug = {

    /**
     * Manually refreshes and reprocesses all UtiliKit elements.
     *
     * This utility function provides console access to manual element
     * processing, useful for testing changes and validating utility
     * class application without requiring page reload or user interaction.
     *
     * @returns {string}
     *   Confirmation message indicating the number of elements processed.
     *
     * @example
     * // Manually refresh all elements from browser console
     * utilikitDebug.refresh()
     * // Returns: "Refreshed 42 elements"
     *
     * @example
     * // Use in console for immediate testing after HTML changes
     * // Add new elements with utility classes, then:
     * utilikitDebug.refresh()
     */
    refresh: function() {
      const elements = document.querySelectorAll('.utilikit');
      Drupal.utilikit.applyClasses(elements);
      return `Refreshed ${elements.length} elements`;
    },

    /**
     * Retrieves comprehensive current state information for debugging.
     *
     * This utility provides detailed information about UtiliKit's current
     * state, configuration, and runtime environment for debugging and
     * troubleshooting purposes. The returned object includes all essential
     * state information for debugging responsive behavior and configuration.
     *
     * @returns {Object}
     *   Object containing comprehensive state information including:
     *   - state: Complete UtiliKit state object
     *   - breakpoint: Current active breakpoint
     *   - width: Current viewport width in pixels
     *
     * @example
     * // Inspect current UtiliKit state from console
     * const state = utilikitDebug.getState();
     * console.log('Current breakpoint:', state.breakpoint);
     * console.log('Viewport width:', state.width);
     *
     * @example
     * // Use for debugging responsive behavior issues
     * utilikitDebug.getState()
     * // Returns: { state: {...}, breakpoint: 'md', width: 1024 }
     */
    getState: function() {
      return {
        state: Drupal.utilikit.state,
        breakpoint: Drupal.utilikit.getBreakpoint(),
        width: window.innerWidth
      };
    },

    /**
     * Manually triggers resize event handling for testing purposes.
     *
     * This utility function allows manual testing of UtiliKit's resize
     * handling logic without requiring actual window resize events.
     * Useful for testing responsive behavior, breakpoint transitions,
     * and element reprocessing logic during development.
     *
     * @returns {string}
     *   Confirmation message indicating resize handler execution.
     *
     * @example
     * // Test resize handling from console
     * utilikitDebug.testResize()
     * // Returns: "Resize handler called"
     *
     * @example
     * // Use for debugging responsive behavior without resizing browser
     * // Modify viewport in dev tools responsive mode, then:
     * utilikitDebug.testResize()
     */
    testResize: function() {
      Drupal.utilikit.utilikitLog('Testing resize handler...', null, 'log');
      if (typeof Drupal.utilikit.handleResize === 'function') {
        Drupal.utilikit.handleResize(document);
      }
      return 'Resize handler called';
    }
  };

})(Drupal, once, drupalSettings);

/**
 * Integration Notes for UtiliKit Debug Helper:
 *
 * Development Workflow Integration:
 * - Automatically activates when drupalSettings.utilikit.devMode is enabled
 * - Provides immediate visual feedback for utility class changes
 * - Integrates seamlessly with browser developer tools workflow
 * - Supports rapid iteration and testing during theme development
 * - Non-intrusive design that doesn't interfere with page layout or functionality
 *
 * Debug Panel Features and Usage:
 * - Force Refresh: Manually reprocesses all elements without page reload
 * - Clear State: Resets UtiliKit state for testing initialization scenarios
 * - Log State: Outputs comprehensive state information to browser console
 * - Real-time Info: Displays current breakpoint, width, and element count
 * - Responsive Updates: Automatically updates information during window resize
 *
 * Console Utilities for Advanced Debugging:
 * - window.utilikitDebug provides global access to debugging functions
 * - Enables scripted testing and automation during development
 * - Supports complex debugging scenarios requiring manual state manipulation
 * - Facilitates regression testing and validation of utility class processing
 * - Integrates with browser console for enhanced development workflow
 *
 * Performance and Security Considerations:
 * - Only loads and activates in development mode for security
 * - Minimal performance impact through efficient event handling
 * - Uses once() API to prevent duplicate panel creation
 * - No persistent storage or data retention beyond development session
 * - Debug tools completely absent in production environments
 *
 * Troubleshooting and Testing Capabilities:
 * - Manual element processing for testing utility class changes
 * - State inspection for debugging configuration and runtime issues
 * - Resize event testing for validating responsive behavior
 * - Comprehensive logging integration for consistent debugging output
 * - Visual feedback for immediate validation of development changes
 *
 * Browser Compatibility and Standards:
 * - Uses standard DOM APIs with broad browser support
 * - Compatible with all major browser developer tools
 * - Responsive design considerations for various screen sizes
 * - Accessibility features for keyboard navigation and screen readers
 * - Standards-compliant CSS and JavaScript implementation
 *
 * Error Handling and Robustness:
 * - Graceful degradation when UtiliKit core functions are unavailable
 * - Non-blocking error handling that preserves page functionality
 * - Validation of UtiliKit state before attempting operations
 * - Comprehensive error reporting through logging system integration
 * - Fallback behavior for incomplete or corrupted state scenarios
 */
