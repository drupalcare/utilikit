/**
 * @file
 * Utility helper functions for UtiliKit CSS processing and validation.
 *
 * This file provides essential utility functions used throughout the UtiliKit
 * system for color parsing, transform handling, breakpoint validation, and
 * error reporting. These helpers abstract common operations and provide
 * consistent interfaces for complex parsing and validation tasks.
 *
 * Key Helper Categories:
 * - Color Processing: Hex color parsing with alpha channel support
 * - Responsive Utilities: Breakpoint validation and screen size checking
 * - CSS Transform Management: Safe transform property manipulation
 * - Value Conversion: Decimal notation and unit conversion utilities
 * - Error Handling: Comprehensive validation error reporting system
 *
 * Integration Points:
 * - Used by CSS generation engine for value parsing and validation
 * - Integrates with logging system for consistent error reporting
 * - Supports development mode with enhanced debugging capabilities
 * - Provides cross-browser compatibility for CSS property handling
 * - Enables consistent validation across all UtiliKit utility classes
 *
 * Design Principles:
 * - Defensive programming with comprehensive input validation
 * - Graceful error handling that doesn't break functionality
 * - Performance optimization through efficient parsing algorithms
 * - Development-friendly error messages with actionable feedback
 * - Cross-browser compatibility for all supported operations
 */
(function(Drupal, once, drupalSettings) {
  'use strict';

  // Ensure UtiliKit namespace exists for helper functions
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Parses hex color strings with optional alpha channel into valid CSS colors.
   *
   * This function handles both 3-digit and 6-digit hex color formats with
   * optional alpha transparency values. It supports UtiliKit's custom syntax
   * for specifying alpha values as percentages (0-100) appended with a dash.
   * The function automatically converts between hex and rgba formats based
   * on whether alpha transparency is specified.
   *
   * Supported Formats:
   * - 6-digit hex: "ff0000" -> "#ff0000"
   * - 6-digit hex with alpha: "ff0000-50" -> "rgba(255, 0, 0, 0.5)"
   * - 3-digit hex: "f00" -> "#f00" (expanded to #ff0000)
   * - 3-digit hex with alpha: "f00-25" -> "rgba(255, 0, 0, 0.25)"
   *
   * @param {string} suffix
   *   The color value suffix from a utility class, containing the hex color
   *   and optional alpha value. Examples: "ff0000", "ff0000-50", "f00-25"
   *
   * @returns {string|null}
   *   Returns a valid CSS color string ("#hexvalue" or "rgba(r, g, b, a)")
   *   or null if the input format is invalid or cannot be parsed.
   *
   * @example
   * // Solid red color (6-digit hex)
   * parseColorWithAlpha('ff0000')
   * // Returns: "#ff0000"
   *
   * @example
   * // Red with 50% transparency
   * parseColorWithAlpha('ff0000-50')
   * // Returns: "rgba(255, 0, 0, 0.5)"
   *
   * @example
   * // Short hex format with transparency
   * parseColorWithAlpha('f00-25')
   * // Returns: "rgba(255, 0, 0, 0.25)"
   *
   * @example
   * // Invalid format returns null
   * parseColorWithAlpha('invalid')
   * // Returns: null
   */
  Drupal.utilikit.parseColorWithAlpha = function(suffix) {
    let match;

    // Match 6-digit hex color with optional alpha (0-100) and optional
    // pseudo-state suffix (-h, -f, -a). In inline mode the pseudo-state
    // is silently ignored; it only takes effect in static/head modes
    // where the PHP generator appends :hover/:focus/:active to the selector.
    if ((match = suffix.match(/^([0-9a-fA-F]{6})(?:-(\d{1,3}))?(?:-(h|f|a))?$/))) {
      const hex = match[1];
      const alpha = match[2] !== undefined && match[2] !== '' ? Math.min(parseInt(match[2], 10), 100) / 100 : 1;
      const r = parseInt(hex.substring(0, 2), 16);
      const g = parseInt(hex.substring(2, 4), 16);
      const b = parseInt(hex.substring(4, 6), 16);
      return alpha < 1 ? `rgba(${r}, ${g}, ${b}, ${alpha})` : `#${hex}`;
    }

    // Match 3-digit hex color with optional alpha and pseudo-state suffix
    if ((match = suffix.match(/^([0-9a-fA-F]{3})(?:-(\d{1,3}))?(?:-(h|f|a))?$/))) {
      // Expand 3-digit hex to 6-digit by doubling each character
      const hex = match[1].split('').map(ch => ch + ch).join('');
      const alpha = match[2] !== undefined && match[2] !== '' ? Math.min(parseInt(match[2], 10), 100) / 100 : 1;
      const r = parseInt(hex.substring(0, 2), 16);
      const g = parseInt(hex.substring(2, 4), 16);
      const b = parseInt(hex.substring(4, 6), 16);
      return alpha < 1 ? `rgba(${r}, ${g}, ${b}, ${alpha})` : `#${hex}`;
    }

    return null; // Invalid format
  };

  /**
   * Checks if a responsive breakpoint applies to the current viewport width.
   *
   * This function implements mobile-first breakpoint logic by checking if
   * the current viewport width meets or exceeds the minimum width threshold
   * for the specified breakpoint. It provides the foundation for responsive
   * utility class application and ensures consistent behavior across the
   * UtiliKit system.
   *
   * The function uses window.innerWidth for accurate viewport detection
   * and integrates with UtiliKit's breakpoint configuration system for
   * consistent behavior with CSS media queries.
   *
   * @param {string} bp
   *   The breakpoint identifier to check. Must be one of the configured
   *   breakpoint names: 'sm', 'md', 'lg', 'xl', 'xxl'.
   *
   * @returns {boolean}
   *   TRUE if the current viewport width is greater than or equal to
   *   the breakpoint's minimum width threshold, FALSE otherwise.
   *
   * @example
   * // Check if medium breakpoint applies (viewport >= 768px)
   * if (Drupal.utilikit.breakpointApplies('md')) {
   *   // Apply medium-screen specific logic
   * }
   *
   * @example
   * // Conditional processing based on breakpoint
   * const isLargeScreen = Drupal.utilikit.breakpointApplies('lg');
   * const columnCount = isLargeScreen ? 4 : 2;
   *
   * @example
   * // Validate breakpoint before processing utility class
   * if (responsivePrefix && !Drupal.utilikit.breakpointApplies(responsivePrefix)) {
   *   return; // Skip processing for inapplicable breakpoint
   * }
   */
  Drupal.utilikit.breakpointApplies = function(bp) {
    const minWidth = Drupal.utilikit.breakpoints[bp];
    return typeof minWidth === 'number' && window.innerWidth >= minWidth;
  };

  /**
   * Converts UtiliKit's decimal notation using 'd' to standard CSS decimal format.
   *
   * UtiliKit uses 'd' as a decimal separator in utility class names to avoid
   * conflicts with CSS class naming conventions that don't support dots.
   * This function converts the custom notation back to standard decimal
   * format for CSS value generation.
   *
   * The conversion is essential for numeric values in utility classes
   * such as fractional units (0.5fr becomes 0d5fr in class names) and
   * decimal measurements that need standard CSS syntax.
   *
   * @param {string} value
   *   A value string that may contain 'd' characters as decimal separators.
   *   Examples: "0d5", "1d25", "2d75fr"
   *
   * @returns {string}
   *   The same value with all 'd' characters replaced by '.' for standard
   *   CSS decimal notation. Examples: "0.5", "1.25", "2.75fr"
   *
   * @example
   * // Convert fractional unit notation
   * convertDecimalNotation('0d5fr')
   * // Returns: "0.5fr"
   *
   * @example
   * // Convert decimal measurement
   * convertDecimalNotation('1d25rem')
   * // Returns: "1.25rem"
   *
   * @example
   * // Handle multiple decimal places
   * convertDecimalNotation('2d75')
   * // Returns: "2.75"
   *
   * @example
   * // No change needed for values without 'd'
   * convertDecimalNotation('100px')
   * // Returns: "100px"
   */
  Drupal.utilikit.convertDecimalNotation = function(value) {
    // Only replace digit-d-digit (decimal notation) with digit.digit; do not replace every 'd'.
    return value.replace(/(\d)d(\d)/g, '$1.$2');
  };

  /**
   * Safely applies CSS transform functions without overwriting existing transforms.
   *
   * This function manages CSS transform properties by preserving existing
   * transform functions while updating or adding specific transform types.
   * It prevents conflicts between multiple transform utilities applied to
   * the same element by maintaining a composite transform value.
   *
   * The function parses existing transform values, removes any transforms
   * of the same type being applied, and appends the new transform while
   * preserving all other transform functions. This ensures predictable
   * behavior when multiple transform utilities are combined.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the transform to. Must be a valid HTMLElement
   *   with access to the style property for CSS manipulation.
   * @param {string} type
   *   The type of transform to apply. Supported values: 'rotate', 'scale'.
   *   Additional transform types can be added by extending the function logic.
   * @param {number} value
   *   The numeric value for the transform. For 'rotate', this represents
   *   degrees of rotation. For 'scale', this represents a percentage where
   *   100 equals normal size (1.0 scale factor).
   *
   * @example
   * // Apply 45-degree rotation to an element
   * const element = document.querySelector('.my-element');
   * Drupal.utilikit.parseTransform(element, 'rotate', 45);
   * // Result: element.style.transform includes "rotate(45deg)"
   *
   * @example
   * // Scale element to 150% while preserving existing rotation
   * Drupal.utilikit.parseTransform(element, 'scale', 150);
   * // Result: element.style.transform includes both transforms
   *
   * @example
   * // Update existing transform of same type
   * Drupal.utilikit.parseTransform(element, 'rotate', 90);
   * // Result: Previous rotation is replaced, scale is preserved
   */
  Drupal.utilikit.parseTransform = function(el, type, value) {
    let transform = el.style.transform || '';

    // Remove any existing transform of the same type to prevent duplicates
    const regex = new RegExp(`${type}\\([^)]*\\)`, 'g');
    transform = transform.replace(regex, '').trim();

    // Append the new transform function based on type
    if (type === 'rotate') {
      transform += ` rotate(${value}deg)`;
    }
    if (type === 'scale') {
      // Convert percentage to decimal scale factor (100% = 1.0)
      transform += ` scale(${value / 100})`;
    }

    // Apply the composite transform value, removing extra whitespace
    el.style.transform = transform.trim();
  };

  /**
   * Logs validation errors with comprehensive context and optional UI feedback.
   *
   * This function provides centralized error reporting for utility class
   * validation failures, with support for both console logging and optional
   * user interface notifications in development mode. It ensures consistent
   * error messaging across the UtiliKit system and provides actionable
   * feedback for developers debugging utility class issues.
   *
   * The function respects development mode configuration and can display
   * errors both in the browser console and as Drupal messages when
   * appropriate settings are enabled. This dual approach supports different
   * development workflows and debugging preferences.
   *
   * @param {string} message
   *   The primary error description explaining what validation failed.
   *   Should be clear and actionable for developers. Examples: "Invalid unit",
   *   "Unknown color format", "Value out of range".
   * @param {string} suffix
   *   The specific invalid value that caused the validation failure.
   *   This provides context about what the developer attempted to use.
   * @param {Object} rule
   *   The UtiliKit rule definition object containing metadata about the
   *   utility class being processed, including the CSS property name.
   * @param {string} className
   *   The complete utility class name that triggered the validation error.
   *   Provides full context for debugging and error identification.
   *
   * @example
   * // Log invalid unit error
   * Drupal.utilikit.logInvalid(
   *   'Invalid unit',
   *   'x100',
   *   { css: 'padding' },
   *   'uk-pd--x100'
   * );
   *
   * @example
   * // Log color format error
   * Drupal.utilikit.logInvalid(
   *   'Invalid color format',
   *   'notacolor',
   *   { css: 'background-color' },
   *   'uk-bg--notacolor'
   * );
   *
   * @example
   * // Log range validation error
   * Drupal.utilikit.logInvalid(
   *   'Value out of range (0-100)',
   *   '150',
   *   { css: 'opacity' },
   *   'uk-op--150'
   * );
   */
  Drupal.utilikit.logInvalid = function(message, suffix, rule, className) {
    const errorMsg = `UtiliKit: ${message} "${suffix}" for "${rule.css}" in class "${className}".`;
    const settings = drupalSettings.utilikit || {};
    const isDevMode = !!settings.devMode;
    const showPageErrors = !!settings.showPageErrors;

    // Only process errors in development mode for security and performance
    if (isDevMode) {
      // Console logging with UtiliKit's logging system integration
      if (typeof Drupal.utilikit.utilikitLog === 'function') {
        Drupal.utilikit.utilikitLog(errorMsg, null, 'warn');
      } else {
        // Fallback to direct console warning if logging system unavailable
        console.warn(errorMsg);
      }

      // Optional UI notifications for immediate visual feedback
      if (showPageErrors && typeof Drupal.Message === 'function') {
        const messages = new Drupal.Message();
        messages.add(errorMsg, {
          type: 'warning'
        });
      }
    }
  };

})(Drupal, once, drupalSettings);

/**
 * Integration Notes for UtiliKit Helper Functions:
 *
 * Color Processing Integration:
 * - parseColorWithAlpha integrates with CSS property generation for color utilities
 * - Supports both solid colors and transparency for modern design requirements
 * - Handles browser compatibility for rgba() vs hex color formats
 * - Validates color input to prevent invalid CSS generation
 * - Provides consistent color handling across all UtiliKit color utilities
 *
 * Responsive System Integration:
 * - breakpointApplies provides foundation for responsive utility class logic
 * - Integrates with UtiliKit's breakpoint configuration and detection system
 * - Ensures consistent mobile-first behavior across all responsive utilities
 * - Supports dynamic breakpoint checking during resize events
 * - Enables performance optimization by skipping inapplicable breakpoints
 *
 * CSS Transform Management:
 * - parseTransform enables safe composition of multiple transform utilities
 * - Prevents conflicts between different transform types on same element
 * - Maintains existing transforms while updating specific transform functions
 * - Supports extensible transform types for future utility class additions
 * - Provides consistent transform syntax generation for cross-browser compatibility
 *
 * Value Conversion and Validation:
 * - convertDecimalNotation enables CSS-compliant decimal values from class names
 * - Supports fractional units and precise numeric values in utility classes
 * - Maintains readability in class names while generating valid CSS
 * - Provides foundation for complex numeric utility class patterns
 * - Enables consistent value parsing across all numeric utility types
 *
 * Error Handling and Development Support:
 * - logInvalid provides comprehensive error context for debugging utility classes
 * - Integrates with UtiliKit's logging system for consistent error reporting
 * - Supports both console and UI-based error notifications for different workflows
 * - Respects development mode configuration for security in production
 * - Provides actionable error messages for rapid debugging and resolution
 *
 * Performance and Efficiency Considerations:
 * - Helper functions are optimized for frequent usage during CSS generation
 * - Input validation prevents expensive operations on invalid data
 * - Efficient regular expressions and string operations for parsing
 * - Minimal DOM manipulation and memory allocation in processing loops
 * - Graceful degradation when optional dependencies are unavailable
 *
 * Browser Compatibility and Standards:
 * - Cross-browser viewport detection using standard window.innerWidth
 * - Standards-compliant CSS value generation for all supported properties
 * - Graceful handling of browser-specific transform syntax requirements
 * - Compatible color format generation supporting legacy and modern browsers
 * - Robust parsing algorithms that handle edge cases and invalid input
 *
 * Development Workflow Integration:
 * - Helper functions support real-time validation during development
 * - Error reporting integrates with browser developer tools for debugging
 * - Comprehensive logging supports troubleshooting complex utility combinations
 * - Development mode features enhance productivity without affecting production
 * - Consistent error messaging helps developers quickly identify and resolve issues
 */
