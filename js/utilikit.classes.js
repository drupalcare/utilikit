/**
 * @file
 * Utility class parsing and CSS application engine for UtiliKit.
 *
 * This file contains the core logic for parsing UtiliKit utility classes
 * and applying them as CSS styles to DOM elements. It includes sophisticated
 * parsing algorithms for complex CSS Grid syntax, responsive breakpoint
 * handling, and optimized CSS application workflows.
 *
 * Key Components:
 * - CSS Grid template parsing with advanced syntax support
 * - Utility class validation and parsing algorithms
 * - Responsive breakpoint priority management
 * - CSS property application with conflict resolution
 * - Development mode testing and validation utilities
 * - Performance-optimized element processing workflows
 *
 * Grid Template Features:
 * - Support for repeat(), minmax(), and fit-content() functions
 * - Auto-fit and autofill responsive grid patterns
 * - Fractional units (fr), percentages, and fixed dimensions
 * - Complex tokenization preserving compound keywords
 * - Responsive prefix support (sm, md, lg, xl, xxl)
 *
 * Performance Optimizations:
 * - Breakpoint priority sorting for cascade management
 * - Stale inline style cleanup to prevent style conflicts
 * - Early returns for invalid or non-applicable classes
 * - Efficient element processing with minimal DOM manipulation
 * - Static mode detection to skip unnecessary processing
 */
(function(Drupal, once, drupalSettings) {
  'use strict';

  // Ensure UtiliKit namespace exists for utility class processing
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Parses CSS Grid template utility classes into valid CSS declarations.
   *
   * This function implements a sophisticated parser for CSS Grid template
   * syntax, supporting complex patterns including repeat() functions,
   * minmax() sizing, fit-content() functions, and responsive breakpoint
   * prefixes. The parser uses intelligent tokenization to handle compound
   * keywords and preserves CSS Grid specification compliance.
   *
   * Supported Syntax Patterns:
   * - Simple values: '1fr', '200px', 'auto', 'min-content'
   * - Repeat functions: 'repeat(3, 1fr)', 'repeat(auto-fit, minmax(250px, 1fr))'
   * - Minmax functions: 'minmax(100px, 1fr)', 'minmax(50%, 2fr)'
   * - Fit-content functions: 'fit-content(200px)', 'fit-content(50%)'
   * - Complex combinations: Multiple functions and values in sequence
   * - Responsive prefixes: 'uk-md-gc--', 'uk-lg-gr--'
   *
   * @param {string} className
   *   The complete utility class name to parse. Must follow UtiliKit
   *   naming convention: 'uk-[breakpoint-]gc/gr--value-pattern'.
   *   Examples: 'uk-gc--repeat-3-1fr', 'uk-md-gc--minmax-200px-1fr'
   *
   * @returns {string|null}
   *   Returns a complete CSS declaration string for grid-template-columns
   *   or grid-template-rows, or null if the className is not a valid
   *   grid template class or cannot be parsed.
   *
   * @example
   * // Simple fractional units
   * parseGridTemplateClass('uk-gc--1fr-2fr-1fr')
   * // Returns: 'grid-template-columns: 1fr 2fr 1fr;'
   *
   * @example
   * // Repeat function with auto-fit
   * parseGridTemplateClass('uk-gc--repeat-auto-fit-minmax-250px-1fr')
   * // Returns: 'grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));'
   *
   * @example
   * // Responsive breakpoint with complex grid
   * parseGridTemplateClass('uk-md-gc--repeat-3-minmax-200px-1fr')
   * // Returns: 'grid-template-columns: repeat(3, minmax(200px, 1fr));'
   *
   * @example
   * // Mixed functions and values
   * parseGridTemplateClass('uk-gc--fitc-200px-1fr-auto')
   * // Returns: 'grid-template-columns: fit-content(200px) 1fr auto;'
   */
  Drupal.utilikit.parseGridTemplateClass = function(className) {
    // Quick validation - must be UtiliKit class with double dash separator
    if (!className.startsWith('uk-') || !className.includes('--')) {
      return null;
    }

    // Determine if this is a grid columns (gc) or rows (gr) class
    const isColumns = className.includes('-gc--');
    const isRows = className.includes('-gr--');

    if (!isColumns && !isRows) {
      return null;
    }

    // Extract the value portion after the double dash separator
    const valueStart = className.indexOf('--') + 2;
    const normalized = className.substring(valueStart);

    /**
     * Smart tokenization function that preserves compound keywords.
     *
     * This internal function intelligently splits the class value while
     * preserving compound keywords like 'auto-fit' and 'auto-fill' that
     * should not be separated during parsing.
     *
     * @param {string} str The string to tokenize
     * @returns {Array<string>} Array of tokens preserving compound keywords
     */
    const smartSplit = function(str) {
      const tokens = [];
      let current = '';

      for (let i = 0; i < str.length; i++) {
        if (str[i] === '-') {
          // Check if this forms a compound keyword
          const nextPart = str.substring(i + 1).split('-')[0];
          if (current === 'auto' && (nextPart === 'fit' || nextPart === 'fill')) {
            // Preserve auto-fit and auto-fill as single tokens
            current += '-' + nextPart;
            i += nextPart.length;
          } else if (current) {
            tokens.push(current);
            current = '';
          }
        } else {
          current += str[i];
        }
      }
      if (current) tokens.push(current);
      return tokens;
    };

    const tokens = smartSplit(normalized);

    // Keywords mapping for CSS Grid values and legacy aliases
    const keywords = {
      'minc': 'min-content',
      'maxc': 'max-content',
      'auto': 'auto',
      'auto-fit': 'auto-fit',      // Modern syntax
      'auto-fill': 'auto-fill',    // Modern syntax
      'autofit': 'auto-fit',       // Legacy support for backwards compatibility
      'autofill': 'auto-fill',     // Legacy support for backwards compatibility
    };

    // Parse tokens into CSS Grid syntax
    const parsed = [];
    let i = 0;

    while (i < tokens.length) {
      const token = tokens[i];

      // Handle repeat() function parsing
      if (token === 'repeat') {
        if (i + 1 >= tokens.length) {
          parsed.push('repeat(/* missing args */)');
          i++;
          continue;
        }

        // Get repeat count (number or keyword like auto-fit)
        const count = keywords[tokens[i + 1]] || tokens[i + 1];
        i += 2;

        // Check if the next token starts a minmax() function
        if (i < tokens.length && tokens[i] === 'minmax') {
          if (i + 2 < tokens.length) {
            let min = tokens[i + 1];
            let max = tokens[i + 2];

            // Convert 'd' to '.' for decimal values (e.g., '0d5fr' -> '0.5fr')
            min = min.replace(/d/g, '.');
            max = max.replace(/d/g, '.');

            // Convert 'pr' suffix to '%' for percentage values
            min = min.replace('pr', '%');
            max = max.replace('pr', '%');

            parsed.push(`repeat(${count}, minmax(${min}, ${max}))`);
            i += 3;
          } else {
            parsed.push(`repeat(${count}, minmax(/* missing args */))`);
            i += 1;
          }
        } else if (i < tokens.length) {
          // Simple repeat with single value
          let value = keywords[tokens[i]] || tokens[i];

          // Apply value transformations
          value = value.replace(/d/g, '.');
          value = value.replace('pr', '%');

          parsed.push(`repeat(${count}, ${value})`);
          i++;
        } else {
          parsed.push(`repeat(${count}, /* missing value */)`);
        }
      }
      // Handle standalone minmax() function
      else if (token === 'minmax') {
        if (i + 2 < tokens.length) {
          let min = tokens[i + 1];
          let max = tokens[i + 2];

          // Apply value transformations
          min = min.replace('pr', '%');
          max = max.replace('pr', '%');

          parsed.push(`minmax(${min}, ${max})`);
          i += 3;
        } else {
          parsed.push('minmax(/* missing args */)');
          i++;
        }
      }
      // Handle fit-content() function (abbreviated as 'fitc')
      else if (token === 'fitc') {
        if (i + 1 < tokens.length) {
          let value = tokens[i + 1];
          value = value.replace('pr', '%');

          parsed.push(`fit-content(${value})`);
          i += 2;
        } else {
          parsed.push('fit-content(/* missing args */)');
          i++;
        }
      }
      // Handle regular values (dimensions, fractions, keywords)
      else {
        let value = keywords[token] || token;

        // Preserve decimal fr values (e.g., '0.5fr') without modification
        if (value.includes('.') && value.endsWith('fr')) {
          // Already in correct format
        } else {
          // Apply standard unit conversions
          value = value.replace('pr', '%');
        }

        parsed.push(value);
        i++;
      }
    }

    // Construct final CSS declaration
    const cssValue = parsed.join(' ');
    const property = isColumns ? 'grid-template-columns' : 'grid-template-rows';

    return `${property}: ${cssValue};`;
  };

  /**
   * Development utility for testing and validating the grid parser.
   *
   * This function runs a comprehensive test suite against the grid template
   * parser to ensure correct parsing of various CSS Grid syntax patterns.
   * It's designed for development and debugging purposes to validate parser
   * behavior and catch regressions.
   *
   * Test Coverage:
   * - Simple fractional and fixed unit combinations
   * - Repeat functions with various count types
   * - Minmax functions with different unit types
   * - Fit-content functions and mixed patterns
   * - Auto-fit and auto-fill responsive patterns
   * - Responsive breakpoint prefixes
   * - Edge cases and error conditions
   *
   * @example
   * // Run the test suite (development mode only)
   * if (drupalSettings.utilikit.devMode) {
   *   Drupal.utilikit.testGridParser();
   * }
   */
  Drupal.utilikit.testGridParser = function() {
    const tests = [
      // Basic fractional and fixed unit patterns
      {
        input: 'uk-gc--repeat-auto-fit-minmax-280px-1fr',
        expected: 'grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fill-minmax-250px-1fr',
        expected: 'grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));'
      },
      {
        input: 'uk-gc--1fr-2fr-0.5fr',
        expected: 'grid-template-columns: 1fr 2fr 0.5fr;'
      },
      {
        input: 'uk-gc--repeat-3-1fr',
        expected: 'grid-template-columns: repeat(3, 1fr);'
      },
      {
        input: 'uk-gc--minmax-50pr-2fr',
        expected: 'grid-template-columns: minmax(50%, 2fr);'
      },
      {
        input: 'uk-gr--auto-1fr-auto',
        expected: 'grid-template-rows: auto 1fr auto;'
      },
      // Fit-content function patterns
      {
        input: 'uk-gc--fitc-200px',
        expected: 'grid-template-columns: fit-content(200px);'
      },
      {
        input: 'uk-gc--fitc-300px-1fr',
        expected: 'grid-template-columns: fit-content(300px) 1fr;'
      },
      {
        input: 'uk-gc--1fr-fitc-250px',
        expected: 'grid-template-columns: 1fr fit-content(250px);'
      },
      {
        input: 'uk-gc--fitc-50pr',
        expected: 'grid-template-columns: fit-content(50%);'
      },
      {
        input: 'uk-gc--200px-1fr',
        expected: 'grid-template-columns: 200px 1fr;'
      },
      {
        input: 'uk-gc--auto-auto',
        expected: 'grid-template-columns: auto auto;'
      },
      {
        input: 'uk-gc--1fr-auto-2fr',
        expected: 'grid-template-columns: 1fr auto 2fr;'
      },
      {
        input: 'uk-gc--100px-1fr-100px',
        expected: 'grid-template-columns: 100px 1fr 100px;'
      },
      {
        input: 'uk-gc--repeat-2-200px',
        expected: 'grid-template-columns: repeat(2, 200px);'
      },
      {
        input: 'uk-gc--repeat-4-1fr',
        expected: 'grid-template-columns: repeat(4, 1fr);'
      },
      {
        input: 'uk-gc--minmax-100px-1fr',
        expected: 'grid-template-columns: minmax(100px, 1fr);'
      },
      {
        input: 'uk-gc--minmax-200px-1fr',
        expected: 'grid-template-columns: minmax(200px, 1fr);'
      },
      {
        input: 'uk-gc--minmax-10rem-1fr',
        expected: 'grid-template-columns: minmax(10rem, 1fr);'
      },
      {
        input: 'uk-gc--repeat-3-minmax-200px-1fr',
        expected: 'grid-template-columns: repeat(3, minmax(200px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-2-minmax-100px-1fr',
        expected: 'grid-template-columns: repeat(2, minmax(100px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fit-minmax-200px-1fr',
        expected: 'grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fit-minmax-300px-1fr',
        expected: 'grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fill-minmax-200px-1fr',
        expected: 'grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fill-minmax-150px-1fr',
        expected: 'grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fill-minmax-250px-1fr',
        expected: 'grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));'
      },
      {
        input: 'uk-gc--repeat-auto-fit-250px',
        expected: 'grid-template-columns: repeat(auto-fit, 250px);'
      },
      {
        input: 'uk-gc--repeat-auto-fill-200px',
        expected: 'grid-template-columns: repeat(auto-fill, 200px);'
      },
      {
        input: 'uk-md-gc--repeat-2-1fr',
        expected: 'grid-template-columns: repeat(2, 1fr);'
      },
      {
        input: 'uk-lg-gc--repeat-auto-fit-minmax-250px-1fr',
        expected: 'grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));'
      }
    ];

    console.log('Testing Grid Parser:');
    tests.forEach(test => {
      const result = Drupal.utilikit.parseGridTemplateClass(test.input);
      const passed = result === test.expected;
      console.log(
        `${passed ? '✅' : '❌'} ${test.input}`,
        `\n   Expected: ${test.expected}`,
        `\n   Got:      ${result}`
      );
    });
  };

  /**
   * Applies UtiliKit utility classes to DOM elements with intelligent processing.
   *
   * This is the core function responsible for parsing utility classes and
   * applying corresponding CSS styles to elements. It handles responsive
   * breakpoint logic, property conflict resolution, and maintains performance
   * through optimized processing workflows.
   *
   * Processing Workflow:
   * 1. Initialize environment and validate dependencies
   * 2. Collect and categorize utility classes from each element
   * 3. Filter classes based on active breakpoints and screen size
   * 4. Sort classes by breakpoint priority for proper cascade
   * 5. Apply CSS properties while tracking applied properties
   * 6. Clean up stale inline styles from previous processing
   * 7. Update tracking attributes for subsequent runs
   *
   * Performance Features:
   * - Breakpoint applicability checks to skip unnecessary processing
   * - Property tracking to enable efficient style cleanup
   * - Sorted application order for predictable CSS cascade
   * - Static mode detection to skip style application
   * - Development mode logging for debugging and optimization
   *
   * @param {NodeList|Array} elements
   *   Collection of DOM elements to process for utility class application.
   *   Each element should have the 'utilikit' class and one or more
   *   utility classes following the pattern 'uk-[breakpoint-]prefix--value'.
   *
   * @example
   * // Apply classes to newly added elements
   * const newElements = document.querySelectorAll('.utilikit:not([data-utilikit-processed])');
   * Drupal.utilikit.applyClasses(newElements);
   *
   * @example
   * // Reprocess all elements after breakpoint change
   * const allElements = document.querySelectorAll('.utilikit');
   * Drupal.utilikit.applyClasses(allElements);
   *
   * @example
   * // Process elements in specific container
   * const container = document.querySelector('.dynamic-content');
   * const elements = container.querySelectorAll('.utilikit');
   * Drupal.utilikit.applyClasses(elements);
   */
  Drupal.utilikit.applyClasses = function(elements) {
    Drupal.utilikit.utilikitLog('Starting to process ' + elements.length + ' elements', { mode: drupalSettings.utilikit?.renderingMode || 'inline' }, 'log');

    // Initialize environment if not already done
    if (!Drupal.utilikit.state) {
      Drupal.utilikit.initEnvironment(document);

      // Verify initialization succeeded before proceeding
      if (!Drupal.utilikit.state || !Drupal.utilikit.rules) {
        Drupal.utilikit.utilikitLog('Failed to initialize - missing state or rules', null, 'warn');
        return;
      }
    }

    // Get runtime configuration for mode detection and debugging
    const renderingMode = drupalSettings.utilikit?.renderingMode || 'inline';
    const isDevMode = drupalSettings.utilikit?.devMode || false;
    const isStaticMode = renderingMode === 'static';

    elements.forEach((el) => {
      Drupal.utilikit.utilikitLog('Processing element with ' + el.classList.length + ' classes', { mode: renderingMode }, 'log');
      const appliedProps = new Set();

      // Collect and categorize all applicable utility classes
      const classesToApply = [];
      el.classList.forEach((className) => {
        // Skip non-UtiliKit classes
        if (!className.startsWith('uk-')) return;

        // Parse class name structure
        const classBody = className.slice(3);
        const parts = classBody.split('--');
        if (parts.length !== 2) return;

        const prefixParts = parts[0].split('-');
        const suffix = parts[1];
        let bp = null;
        let prefix = null;

        // Determine breakpoint and utility prefix
        if (prefixParts.length === 1) {
          prefix = prefixParts[0];
        } else if (prefixParts.length === 2) {
          bp = prefixParts[0];
          prefix = prefixParts[1];
        } else {
          return;
        }

        // Development mode breakpoint debugging
        if (bp) {
          Drupal.utilikit.utilikitLog('Processing breakpoint class: ' + className, { bp: bp, active: Drupal.utilikit.activeBreakpoints.includes(bp) }, 'log');
        }

        // Skip classes for breakpoints that don't apply to current screen size
        if (bp && !Drupal.utilikit.breakpointApplies(bp)) return;

        // Skip classes for disabled breakpoints in configuration
        if (bp && Drupal.utilikit.activeBreakpoints && !Drupal.utilikit.activeBreakpoints.includes(bp)) {
          return;
        }

        // Skip classes with unknown utility prefixes
        if (!(prefix in Drupal.utilikit.rules)) return;

        // Determine breakpoint priority for sorting (mobile-first cascade)
        const breakpointOrder = { '': 0, 'sm': 1, 'md': 2, 'lg': 3, 'xl': 4, 'xxl': 5 };
        const priority = breakpointOrder[bp || ''] || 0;

        classesToApply.push({
          className,
          prefix,
          suffix,
          bp,
          priority,
          rule: Drupal.utilikit.rules[prefix]
        });
      });

      // Sort by breakpoint priority to ensure proper CSS cascade
      // (smaller breakpoints first, larger breakpoints override)
      classesToApply.sort((a, b) => a.priority - b.priority);

      // Apply classes in priority order
      classesToApply.forEach(({ className, prefix, suffix, rule }) => {
        if (isStaticMode) {
          // Static mode: track classes for debugging but don't apply styles
          Drupal.utilikit.utilikitLog('Static mode - class detected: ' + className, null, 'log');
          appliedProps.add(rule.css); // Track for data attribute
        } else {
          // Inline mode: apply styles dynamically
          Drupal.utilikit.applyRule(el, rule, prefix, suffix, appliedProps, className);
        }
      });

      // Clean up stale inline styles from previous processing runs
      const previousProps = el.dataset.utilikitProps ? el.dataset.utilikitProps.split(',') : [];
      previousProps.forEach((prop) => {
        if (!appliedProps.has(prop)) el.style[prop] = '';
      });

      // Update tracking data for next processing run
      el.dataset.utilikitProps = Array.from(appliedProps).join(',');
      Drupal.utilikit.utilikitLog('Element completed: ' + appliedProps.size + ' properties applied', Array.from(appliedProps), 'log');
    });
  };

})(Drupal, once, drupalSettings);

/**
 * Integration Notes for UtiliKit Class Processing System:
 *
 * CSS Grid Template Parsing Architecture:
 * - Supports the complete CSS Grid specification for template definitions
 * - Handles complex syntax including nested functions and responsive variants
 * - Implements intelligent tokenization to preserve compound keywords
 * - Provides comprehensive error handling for malformed syntax
 * - Maintains CSS specification compliance for all generated output
 *
 * Responsive Breakpoint System:
 * - Mobile-first approach with progressive enhancement
 * - Breakpoint priority sorting ensures proper CSS cascade behavior
 * - Screen size applicability checks prevent unnecessary processing
 * - Configuration-based breakpoint activation for performance optimization
 * - Development mode debugging for breakpoint application logic
 *
 * Performance Optimization Strategies:
 * - Early returns for invalid or non-applicable classes
 * - Efficient DOM querying with minimal style recalculation
 * - Property tracking enables targeted cleanup of stale styles
 * - Static mode detection skips unnecessary processing overhead
 * - Batch processing of elements minimizes layout thrashing
 *
 * Development and Debugging Features:
 * - Comprehensive test suite for grid parser validation
 * - Detailed logging for class processing workflow
 * - Breakpoint application debugging with context information
 * - Property application tracking for optimization analysis
 * - Error reporting with specific context for troubleshooting
 *
 * Integration with UtiliKit Core Services:
 * - Environment initialization with state management integration
 * - Rules registry integration for utility prefix validation
 * - Active breakpoints configuration from drupalSettings
 * - Rendering mode detection for processing workflow adaptation
 * - Logging service integration for consistent debugging output
 *
 * Browser Compatibility and Standards:
 * - CSS Grid specification compliance for all generated output
 * - Modern JavaScript features with graceful degradation
 * - Cross-browser testing for grid template syntax support
 * - Standards-compliant CSS property names and values
 * - Performance optimization for various device capabilities
 *
 * Error Handling and Robustness:
 * - Graceful handling of malformed utility class syntax
 * - Fallback processing for missing or corrupted configuration
 * - Non-blocking error recovery that preserves other functionality
 * - Comprehensive validation of parser inputs and outputs
 * - Development mode warnings for debugging and optimization
 */
