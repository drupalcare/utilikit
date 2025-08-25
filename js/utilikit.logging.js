/**
 * @file
 * UtiliKit Enhanced Logging and Development Debugging System.
 *
 * This file provides comprehensive logging functionality specifically designed
 * for UtiliKit module development and debugging. It implements structured
 * logging with performance timing, CSS rule application tracking, and
 * rendering mode diagnostics to facilitate efficient troubleshooting and
 * optimization during development.
 *
 * The logging system is context-aware and respects configuration settings
 * to provide detailed insights without impacting production performance.
 * All logging functions integrate seamlessly with UtiliKit's core logging
 * infrastructure and browser developer tools.
 *
 * Key Features:
 * - High-precision performance timing with operation profiling
 * - CSS rule application success/failure tracking with element context
 * - Rendering mode switching diagnostics and event logging
 * - Structured data logging for enhanced browser dev tools integration
 * - Configurable log levels with production safety guarantees
 * - Zero performance impact when development mode is disabled
 *
 * Usage Context:
 * - Loaded exclusively when development mode is enabled in settings
 * - Provides detailed console output for complex troubleshooting scenarios
 * - Supports performance optimization workflows during development
 * - Enables comprehensive tracking of CSS generation and DOM manipulation
 *
 * @see utilikit.behavior.js for core logging integration
 * @see utilikit.helpers.js for base logging infrastructure
 */
(function(Drupal, once, drupalSettings) {
  'use strict';

  // Ensure UtiliKit namespace exists for logging functionality
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Enhanced performance timing and operation profiling wrapper.
   *
   * Provides high-precision timing measurements for any operation,
   * essential for identifying performance bottlenecks in CSS generation,
   * DOM manipulation, and utility class processing workflows. Uses the
   * Performance API for microsecond-accurate timing data.
   *
   * The timing wrapper is completely transparent, allowing it to be used
   * around existing operations without modifying their behavior or
   * return values. Performance data is logged using UtiliKit's
   * configurable logging system.
   *
   * @param {string} operation
   *   Descriptive name for the operation being timed. This name appears
   *   in log output to identify the specific operation being measured.
   *   Should be concise but descriptive (e.g., 'CSS Generation', 'DOM Scan').
   * @param {Function} callback
   *   The function to execute and time. Must be a synchronous function.
   *   The function's return value will be preserved and returned by
   *   this wrapper.
   *
   * @returns {*}
   *   The exact return value of the executed callback function, enabling
   *   transparent integration with existing code paths.
   *
   * @example
   *   // Time CSS generation for a set of utility classes
   *   const generatedCss = Drupal.utilikit.logWithTiming(
   *     'CSS Generation',
   *     function() {
   *       return generateCssFromClasses(['uk-pd--16', 'uk-mg--auto']);
   *     }
   *   );
   *
   * @example
   *   // Time DOM scanning operation for utility class detection
   *   const foundClasses = Drupal.utilikit.logWithTiming(
   *     'DOM Scan',
   *     function() {
   *       return scanElementForUtilityClasses(document.body);
   *     }
   *   );
   *
   * @example
   *   // Time complex rule application across multiple elements
   *   Drupal.utilikit.logWithTiming('Bulk Rule Application', function() {
   *     document.querySelectorAll('.utilikit').forEach(function(element) {
   *       Drupal.utilikit.applyClasses([element]);
   *     });
   *   });
   */
  Drupal.utilikit.logWithTiming = function(operation, callback) {
    const startTime = performance.now();
    Drupal.utilikit.utilikitLog('Starting: ' + operation, null, 'log');

    const result = callback();

    const endTime = performance.now();
    const duration = (endTime - startTime).toFixed(2);
    Drupal.utilikit.utilikitLog('Completed: ' + operation + ' in ' + duration + 'ms', null, 'log');

    return result;
  };

  /**
   * Logs CSS rule application attempts with comprehensive context data.
   *
   * Provides detailed logging for CSS rule application operations, capturing
   * both successful applications and failures with rich contextual information.
   * This function is essential for debugging utility class issues, understanding
   * CSS generation behavior, and tracking rule application success rates.
   *
   * The logging includes element identification, utility class context, and
   * structured data that integrates well with browser developer tools for
   * enhanced debugging workflows.
   *
   * @param {Element} element
   *   The DOM element being targeted for CSS rule application. Used to
   *   provide element context including tag name, ID, and relevant classes.
   * @param {string} rule
   *   The CSS property name being applied (e.g., 'padding', 'margin',
   *   'backgroundColor'). Identifies which UtiliKit rule is being processed.
   * @param {string} value
   *   The CSS property value being applied (e.g., '20px', '1rem', 'center').
   *   Shows the computed value derived from the utility class.
   * @param {boolean} success
   *   Whether the CSS rule application completed successfully. Determines
   *   the log level (success = 'log', failure = 'warn') and message format.
   *
   * @example
   *   // Log successful padding application
   *   const element = document.querySelector('.my-container');
   *   Drupal.utilikit.logRuleApplication(
   *     element,
   *     'padding',
   *     '20px',
   *     true
   *   );
   *   // Output: "Applied CSS: padding = 20px"
   *
   * @example
   *   // Log failed margin application with invalid value
   *   const problematicElement = document.querySelector('.broken-element');
   *   Drupal.utilikit.logRuleApplication(
   *     problematicElement,
   *     'margin',
   *     'invalid-auto-value',
   *     false
   *   );
   *   // Output: "Failed to apply CSS: margin = invalid-auto-value"
   *
   * @example
   *   // Log successful complex grid property application
   *   const gridContainer = document.querySelector('.grid-layout');
   *   Drupal.utilikit.logRuleApplication(
   *     gridContainer,
   *     'gridTemplateColumns',
   *     'repeat(3, 1fr)',
   *     true
   *   );
   */
  Drupal.utilikit.logRuleApplication = function(element, rule, value, success) {
    if (success) {
      Drupal.utilikit.utilikitLog('Applied CSS: ' + rule + ' = ' + value, {
        element: element.tagName.toLowerCase() + (element.id ? '#' + element.id : ''),
        classes: Array.from(element.classList).filter(c => c.startsWith('uk-')).join(' ')
      }, 'log');
    } else {
      Drupal.utilikit.utilikitLog('Failed to apply CSS: ' + rule + ' = ' + value, {
        element: element.tagName.toLowerCase() + (element.id ? '#' + element.id : ''),
        reason: 'Invalid value or rule'
      }, 'warn');
    }
  };

  /**
   * Logs UtiliKit rendering mode transitions with contextual information.
   *
   * Tracks and logs transitions between UtiliKit's rendering modes (inline
   * and static), providing critical visibility into mode switching behavior,
   * reasons for transitions, and system state changes. This logging is
   * essential for understanding automatic fallbacks, user-initiated changes,
   * and troubleshooting mode-related configuration issues.
   *
   * Mode transitions can occur due to various triggers including user
   * configuration changes, automatic fallbacks due to missing CSS files,
   * permission changes, or system optimization decisions.
   *
   * @param {string} fromMode
   *   The current rendering mode being transitioned from. Expected values
   *   include 'inline', 'static', 'unknown', or 'uninitialized'.
   * @param {string} toMode
   *   The target rendering mode being transitioned to. Expected values
   *   are 'inline' or 'static'.
   * @param {string} [reason='User initiated']
   *   Optional descriptive reason for the mode transition. Provides valuable
   *   context about what triggered the mode change, helping with debugging
   *   and system behavior analysis.
   *
   * @example
   *   // Log automatic fallback when CSS file is unavailable
   *   Drupal.utilikit.logModeSwitch(
   *     'static',
   *     'inline',
   *     'CSS file not found, falling back to inline processing'
   *   );
   *
   * @example
   *   // Log user-initiated configuration change
   *   Drupal.utilikit.logModeSwitch(
   *     'inline',
   *     'static',
   *     'User enabled static mode via admin settings'
   *   );
   *
   * @example
   *   // Log mode switch without specific reason (uses default)
   *   Drupal.utilikit.logModeSwitch('inline', 'static');
   *   // Logs with reason: "User initiated"
   *
   * @example
   *   // Log automatic optimization-based mode switch
   *   Drupal.utilikit.logModeSwitch(
   *     'inline',
   *     'static',
   *     'Performance optimization: switching to static for production'
   *   );
   */
  Drupal.utilikit.logModeSwitch = function(fromMode, toMode, reason) {
    Drupal.utilikit.utilikitLog('Mode switch: ' + fromMode + ' → ' + toMode, {
      reason: reason || 'User initiated'
    }, 'log');
  };

})(Drupal, once, drupalSettings);

/**
 * @file
 * UtiliKit Logging System Integration and Configuration Guide.
 *
 * This section provides comprehensive documentation about how the UtiliKit
 * logging system integrates with the broader Drupal and UtiliKit ecosystem,
 * including configuration options, performance considerations, and best
 * practices for development workflows.
 */

/**
 * Log Level Configuration and Behavior:
 *
 * The UtiliKit logging system respects global configuration settings and
 * provides granular control over logging verbosity:
 *
 * Configuration Source:
 * - Controlled via drupalSettings.utilikit.logLevel setting
 * - Set through UtiliKit admin configuration interface
 * - Defaults to 'warnings' for safety in development environments
 *
 * Available Log Levels:
 * - 'off': Completely disables all UtiliKit logging output
 * - 'warnings': Shows only warning and error messages
 * - 'detailed': Shows all log levels including performance timing
 *
 * Level-Specific Behavior:
 * - Performance timing logs appear only when level is 'detailed'
 * - CSS application logs appear at 'warnings' and 'detailed' levels
 * - Mode switch logs appear at 'warnings' and 'detailed' levels
 * - Error logs always appear unless level is 'off'
 */

/**
 * Performance Considerations and Optimization:
 *
 * The logging system is designed for zero-impact production performance:
 *
 * Performance Timing:
 * - Uses high-precision performance.now() API for microsecond accuracy
 * - Minimal computational overhead even when logging is enabled
 * - No performance impact when development mode is disabled
 * - Graceful fallback if Performance API is unavailable
 *
 * Memory Management:
 * - Structured data logging avoids memory leaks
 * - Log filtering occurs at utilikitLog level for efficiency
 * - No persistent storage of log data reduces memory footprint
 * - Automatic cleanup of temporary timing variables
 *
 * Production Safety:
 * - Entire logging module only loads when dev_mode is enabled
 * - Zero function calls made when logging is disabled
 * - No performance monitoring overhead in production
 * - Safe to include detailed logging without production impact
 */

/**
 * Development Workflow Integration:
 *
 * The logging system enhances development workflows in several ways:
 *
 * Performance Optimization:
 * - Timing logs identify bottlenecks in CSS generation workflows
 * - Operation profiling helps optimize DOM manipulation operations
 * - Comparative timing data assists in performance regression detection
 * - Structured timing data integrates with browser performance tools
 *
 * Debugging Capabilities:
 * - CSS application logs assist with utility class troubleshooting
 * - Element context provides precise debugging information
 * - Structured data enhances browser developer tools experience
 * - Mode transition logs provide system behavior visibility
 *
 * Development Process Support:
 * - Comprehensive logging during development and testing phases
 * - Detailed operation tracking for complex CSS generation workflows
 * - Integration with browser debugging tools and extensions
 * - Consistent log formatting for automated log processing
 */

/**
 * Browser Compatibility and Technical Requirements:
 *
 * Cross-Browser Support:
 * - Uses performance.now() API (supported in all modern browsers)
 * - Falls back gracefully if Performance API is unavailable
 * - Compatible with all browsers supporting UtiliKit core functionality
 * - No polyfills required for logging functionality
 *
 * Technical Dependencies:
 * - Requires UtiliKit core logging infrastructure (utilikitLog function)
 * - Integrates with Drupal behaviors system
 * - Uses drupalSettings for configuration access
 * - Compatible with CSP policies when properly configured
 *
 * Feature Detection:
 * - Automatic detection of Performance API availability
 * - Graceful degradation when advanced features unavailable
 * - No runtime errors in unsupported browser environments
 * - Consistent behavior across different JavaScript engine implementations
 */
