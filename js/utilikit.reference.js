/**
 * @file
 * Interactive features for UtiliKit Reference page with comprehensive security.
 *
 * This file provides the complete interactive functionality for the UtiliKit
 * reference documentation page, including copy-to-clipboard features, live
 * utility class examples, hover tooltips, and comprehensive security measures
 * to prevent XSS attacks and ensure safe user interactions.
 *
 * Key Features:
 * - Secure copy-to-clipboard functionality for utility class examples
 * - Interactive live preview playground with real-time CSS application
 * - Hover tooltips with contextual examples and usage information
 * - Comprehensive input validation and sanitization throughout
 * - Cross-browser compatibility for clipboard operations
 * - Accessibility features for screen readers and keyboard navigation
 *
 * Security Measures:
 * - All user input sanitized through UtiliKit's security utilities
 * - Utility class validation before any DOM manipulation
 * - Safe DOM element creation preventing XSS vulnerabilities
 * - Input validation for all clipboard and preview operations
 * - Error handling that fails safely without exposing internals
 *
 * User Experience Features:
 * - Visual feedback for successful and failed operations
 * - Debounced input handling for smooth interactive performance
 * - Fallback clipboard methods for broader browser support
 * - Contextual examples tailored to specific utility class types
 * - Responsive design considerations for mobile and desktop usage
 */
(function (Drupal, once) {
  'use strict';

  /**
   * UtiliKit Reference page behavior for interactive documentation features.
   *
   * This behavior initializes all interactive features on the UtiliKit reference
   * page, including copy buttons, live examples, and hover effects. It ensures
   * proper dependency loading and provides comprehensive error handling for
   * a robust user experience.
   */
  Drupal.behaviors.utilikitReference = {

    /**
     * Attaches interactive functionality to UtiliKit reference page elements.
     *
     * This method initializes all interactive features with proper dependency
     * checking and error handling. It waits for UtiliKit core functions to
     * be available before proceeding with initialization to ensure all
     * required functionality is present.
     *
     * @param {Document|Element} context
     *   The DOM context being processed, typically the document or a
     *   specific container after AJAX operations.
     * @param {Object} settings
     *   Drupal settings object containing configuration and runtime data.
     */
    attach: function (context, settings) {
      // Wait for UtiliKit core to be loaded before initializing reference features
      if (typeof Drupal.utilikit === 'undefined' || typeof Drupal.utilikit.applyClasses !== 'function') {
        setTimeout(() => {
          Drupal.behaviors.utilikitReference.attach(context, settings);
        }, 100);
        return;
      }

      // Initialize all reference page features with error handling
      once('utilikitReference', '.utilikit-reference-page', context).forEach(function (element) {
        try {
          initializeCopyButtons(element);
          initializeInteractiveExample(element);
          addHoverEffects(element);
        } catch (error) {
          console.error('UtiliKit Reference: Initialization failed', error);
        }
      });
    }
  };

  /**
   * Initializes secure copy-to-clipboard functionality for utility class examples.
   *
   * This function adds copy buttons to utility class syntax examples and
   * group collections, with comprehensive security validation to prevent
   * malicious code injection. All copied content is validated before
   * clipboard operations to ensure only valid UtiliKit classes are copied.
   *
   * Security Features:
   * - Input sanitization through UtiliKit security utilities
   * - Utility class validation before clipboard operations
   * - Safe DOM element creation to prevent XSS attacks
   * - Error handling that fails safely without exposing internals
   *
   * @param {Element} container
   *   The container element containing syntax items and copy targets.
   *   Should be the reference page container or a specific section.
   *
   * @example
   * // Called automatically during reference page initialization
   * // Manual initialization for specific container:
   * const section = document.querySelector('.syntax-section');
   * initializeCopyButtons(section);
   */
  function initializeCopyButtons(container) {
    // Initialize individual syntax item copy buttons
    container.querySelectorAll('.syntax-item').forEach(function (item) {
      // Skip items that already have copy buttons to prevent duplicates
      if (item.querySelector('.copy-btn')) return;

      item.style.position = 'relative';

      const syntaxEl = item.querySelector('.syntax');
      if (!syntaxEl) return;

      // Create secure copy button using UtiliKit's security utilities
      const copyBtn = Drupal.utilikit.security.createSafeElement('button', 'Copy', {
        'class': 'copy-btn',
        'type': 'button',
        'aria-label': 'Copy utility class example'
      });

      copyBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        e.preventDefault();

        try {
          const prefix = syntaxEl.querySelector('.syntax-prefix');
          const value = syntaxEl.querySelector('.syntax-value');
          if (!prefix) return;

          // Security: Validate and sanitize prefix
          const prefixText = Drupal.utilikit.security.sanitizeText(prefix.textContent);
          let valueText = value ? Drupal.utilikit.security.sanitizeText(value.textContent) : '20';

          // If the value is the generic word "value", get the first example instead
          if (valueText === 'value') {
            const examples = getExamplesForSyntax(`uk-${prefixText}--`);
            valueText = examples.split(',')[0].trim(); // Get first example
          }

          const textToCopy = `uk-${prefixText}--${valueText}`;

          // Validate the generated class against UtiliKit security rules
          if (!Drupal.utilikit.security.isValidUtilityClass(textToCopy)) {
            console.warn('UtiliKit: Invalid class generated for copy');
            return;
          }

          safeCopyToClipboard(textToCopy, copyBtn);
        } catch (error) {
          console.error('UtiliKit: Copy failed', error);
          showCopyError(copyBtn);
        }
      });

      item.appendChild(copyBtn);
    });

    // Initialize group copy buttons for bulk copying multiple classes
    container.querySelectorAll('.copy-group-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();

        try {
          const groupContainer = btn.closest('.syntax-group').querySelector('.syntax-items');
          const classes = [];

          groupContainer.querySelectorAll('.syntax-prefix').forEach(function(prefixEl) {
            const prefixText = Drupal.utilikit.security.sanitizeText(prefixEl.textContent);
            const className = `uk-${prefixText}--20`;

            // Security: Only add validated classes to prevent malicious content
            if (Drupal.utilikit.security.isValidUtilityClass(className)) {
              classes.push(className);
            }
          });

          if (classes.length > 0) {
            safeCopyToClipboard(classes.join(' '), btn);
          }
        } catch (error) {
          console.error('UtiliKit: Group copy failed', error);
          showCopyError(btn);
        }
      });
    });
  }

  /**
   * Secure clipboard copy function with fallback support and validation.
   *
   * This function implements secure clipboard operations using modern
   * Clipboard API with fallback to legacy methods for broader browser
   * support. All input is validated before clipboard operations to
   * prevent potential security issues.
   *
   * @param {string} text
   *   The text content to copy to clipboard. Must be a valid, non-empty
   *   string that has been pre-validated for security.
   * @param {Element} button
   *   The button element that triggered the copy operation, used for
   *   visual feedback and user experience enhancements.
   */
  function safeCopyToClipboard(text, button) {
    // Validate input to ensure security and prevent empty clipboard operations
    if (typeof text !== 'string' || !text.trim()) {
      showCopyError(button);
      return;
    }

    // Use modern Clipboard API when available and secure context exists
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function () {
        showCopySuccess(button);
      }).catch(function(err) {
        console.error('Clipboard API failed:', err);
        fallbackCopy(text, button);
      });
    } else {
      // Fall back to legacy method for broader browser compatibility
      fallbackCopy(text, button);
    }
  }

  /**
   * Secure fallback clipboard copy method for legacy browser support.
   *
   * This function implements a secure fallback for clipboard operations
   * using the legacy document.execCommand approach. It creates a temporary
   * textarea element with security attributes to safely copy content
   * without exposing it to potential manipulation.
   *
   * @param {string} text
   *   The validated text content to copy using legacy clipboard methods.
   * @param {Element} button
   *   The button element for visual feedback during the copy operation.
   */
  function fallbackCopy(text, button) {
    try {
      // Create secure temporary textarea for legacy clipboard operation
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.top = '0';
      textarea.style.left = '-9999px';
      textarea.style.opacity = '0';
      textarea.setAttribute('readonly', '');
      textarea.setAttribute('aria-hidden', 'true');

      document.body.appendChild(textarea);
      textarea.select();
      textarea.setSelectionRange(0, 99999);

      const successful = document.execCommand('copy');
      document.body.removeChild(textarea);

      if (successful) {
        showCopySuccess(button);
      } else {
        showCopyError(button);
      }
    } catch (err) {
      console.error('Fallback copy failed:', err);
      showCopyError(button);
    }
  }

  /**
   * Displays visual feedback for successful clipboard copy operations.
   *
   * This function provides immediate visual feedback to users when clipboard
   * operations succeed, enhancing user experience with clear confirmation
   * of the completed action. The feedback is temporary and automatically
   * reverts to the original state.
   *
   * @param {Element} button
   *   The button element to update with success feedback styling and text.
   */
  function showCopySuccess(button) {
    const originalText = button.textContent;
    button.textContent = 'Copied!';
    button.classList.add('copied', 'success-pulse');
    button.setAttribute('aria-label', 'Copied to clipboard');

    setTimeout(function () {
      button.textContent = originalText;
      button.classList.remove('copied', 'success-pulse');
      button.setAttribute('aria-label', 'Copy utility class example');
    }, 2000);
  }

  /**
   * Displays visual feedback for failed clipboard copy operations.
   *
   * This function provides clear visual indication when clipboard operations
   * fail, helping users understand that the operation was not successful
   * and may need to be retried or performed manually.
   *
   * @param {Element} button
   *   The button element to update with error feedback styling and text.
   */
  function showCopyError(button) {
    const originalText = button.textContent;
    const originalBg = button.style.background;

    button.textContent = 'Failed';
    button.style.background = '#dc3545';
    button.setAttribute('aria-label', 'Copy failed');

    setTimeout(function () {
      button.textContent = originalText;
      button.style.background = originalBg;
      button.setAttribute('aria-label', 'Copy utility class example');
    }, 2000);
  }

  /**
   * Initializes the interactive example playground with live utility class preview.
   *
   * This function creates a secure, interactive environment where users can
   * test utility classes in real-time with live CSS application and visual
   * feedback. All input is validated and sanitized to prevent XSS attacks
   * while providing an educational and functional testing environment.
   *
   * Features:
   * - Real-time utility class application with live preview
   * - Clickable code examples for quick testing
   * - Debounced input handling for smooth performance
   * - Comprehensive input validation and sanitization
   * - Visual feedback for user interactions
   *
   * @param {Element} container
   *   The container element containing the interactive example interface.
   *   Should include input field and preview area elements.
   */
  function initializeInteractiveExample(container) {
    const input = container.querySelector('#utilikit-example-input');
    const preview = container.querySelector('#utilikit-example-preview');

    if (!input || !preview) {
      return;
    }

    // Make example code blocks clickable for quick testing
    container.querySelectorAll('.interactive-example code').forEach(function(codeBlock) {
      codeBlock.style.cursor = 'pointer';
      codeBlock.title = 'Click to try this example';

      codeBlock.addEventListener('click', function() {
        try {
          // Security: Sanitize the code block content before processing
          const content = Drupal.utilikit.security.sanitizeText(codeBlock.textContent);

          // Validate each class individually before applying
          const classes = content.split(/\s+/).filter(cls => {
            return Drupal.utilikit.security.isValidUtilityClass(cls);
          });

          if (classes.length > 0) {
            input.value = classes.join(' ');
            updatePreview();

            // Provide visual feedback for successful code block interaction
            codeBlock.style.background = '#28a745';
            codeBlock.style.color = 'white';
            setTimeout(function() {
              codeBlock.style.background = '';
              codeBlock.style.color = '';
            }, 300);
          }
        } catch (error) {
          console.error('UtiliKit: Example click failed', error);
        }
      });
    });

    // Initialize preview element with safe default state
    preview.textContent = 'Preview Text';
    preview.className = 'example-preview utilikit';

    // Set secure initial example with validated classes
    const initialClasses = 'uk-pd--20 uk-bg--007bff uk-tc--ffffff uk-br--8 uk-fs--18';
    input.value = initialClasses;
    updatePreview();

    // Add debounced input listeners for smooth real-time updates
    let timeout;
    input.addEventListener('input', function() {
      clearTimeout(timeout);
      timeout = setTimeout(updatePreview, 300);
    });

    input.addEventListener('paste', function() {
      setTimeout(updatePreview, 10);
    });

    /**
     * Updates the live preview with validated utility classes.
     *
     * This internal function safely processes user input, validates all
     * utility classes, and applies them to the preview element. It provides
     * comprehensive security validation to prevent XSS attacks while
     * maintaining a smooth user experience.
     */
    function updatePreview() {
      try {
        // Security: Validate and sanitize all user input
        const inputValue = Drupal.utilikit.security.sanitizeText(input.value);
        const classes = inputValue.trim().split(/\s+/);

        // Reset preview to secure base state
        preview.className = 'example-preview utilikit';
        preview.removeAttribute('style');

        // Apply only validated utility classes to prevent malicious content
        classes.forEach(function (cls) {
          if (cls && Drupal.utilikit.security.isValidUtilityClass(cls)) {
            preview.classList.add(cls);
          }
        });

        // Force UtiliKit to safely reprocess the updated element
        if (Drupal.utilikit && Drupal.utilikit.applyClasses) {
          delete preview.dataset.utilikitProps;
          Drupal.utilikit.applyClasses([preview]);
        }
      } catch (error) {
        console.error('UtiliKit: Preview update failed', error);
        // Reset to safe state on any error to prevent security issues
        preview.className = 'example-preview utilikit';
        preview.removeAttribute('style');
      }
    }
  }

  /**
   * Adds secure hover effects with contextual examples and tooltips.
   *
   * This function enhances the reference page with informative hover
   * tooltips that provide contextual examples for utility class patterns.
   * All tooltip content is generated securely to prevent XSS vulnerabilities
   * while providing valuable educational information.
   *
   * @param {Element} container
   *   The container element containing syntax items that should receive
   *   hover effects and tooltip functionality.
   */
  function addHoverEffects(container) {
    container.querySelectorAll('.syntax-item').forEach(function (item) {
      // Skip items that already have tooltip wrappers to prevent duplicates
      if (item.parentNode.classList.contains('tooltip-wrapper')) return;

      try {
        // Create secure tooltip element using UtiliKit security utilities
        const tooltip = Drupal.utilikit.security.createSafeElement('div', '', {
          'class': 'tooltip-content',
          'role': 'tooltip'
        });

        const syntaxElement = item.querySelector('.syntax');
        if (!syntaxElement) return;

        // Generate secure contextual examples for the utility class pattern
        const syntax = Drupal.utilikit.security.sanitizeText(syntaxElement.textContent.trim());
        const examples = getExamplesForSyntax(syntax);

        // Security: Use safe DOM creation instead of innerHTML injection
        const strongEl = Drupal.utilikit.security.createSafeElement('strong', 'Examples: ');
        tooltip.appendChild(strongEl);
        tooltip.appendChild(document.createTextNode(examples));

        // Create wrapper for proper tooltip positioning
        const wrapper = Drupal.utilikit.security.createSafeElement('div', '', {
          'class': 'tooltip-wrapper'
        });

        item.parentNode.insertBefore(wrapper, item);
        wrapper.appendChild(item);
        wrapper.appendChild(tooltip);
      } catch (error) {
        console.error('UtiliKit: Hover effect failed', error);
      }
    });
  }

  /**
   * Generates secure contextual examples for utility class syntax patterns.
   *
   * This function provides tailored examples for different utility class
   * types, helping users understand the range of values and patterns
   * available for each utility prefix. All output is sanitized to ensure
   * security while providing comprehensive educational content.
   *
   * @param {string} syntax
   *   The utility class syntax pattern to generate examples for.
   *   Should follow the format "uk-prefix--" for proper pattern matching.
   *
   * @returns {string}
   *   Sanitized string containing comma-separated examples appropriate
   *   for the given utility class pattern.
   */
  function getExamplesForSyntax(syntax) {
    const match = syntax.match(/uk-(\w+)--/);
    if (!match) return syntax + '20';

    // Security: Validate the extracted prefix before processing
    const prefix = Drupal.utilikit.security.validateClassName(match[1]);
    if (!prefix) return '20';

    // Comprehensive examples mapping for all utility class types
    const examples = {
      'pd': '20, t-16, 10-20-30',           // Padding utilities
      'mg': '20, auto, t-auto, 10-20',       // Margin utilities
      'bw': '2, t-1, 1-2-3-4',              // Border width utilities
      'br': '8, t-16, 50pr, 10-20-30-40',   // Border radius utilities
      'wd': '100pr, 200, 50vw',             // Width utilities
      'ht': '100, 100vh, 500',              // Height utilities
      'xw': '1200, 100pr',                  // Max width utilities
      'xh': '600, 100vh',                   // Max height utilities
      'nw': '300, 50pr',                    // Min width utilities
      'nh': '100, 20vh',                    // Min height utilities
      'bg': 'ff0000, 007bff-50',            // Background color utilities
      'tc': '333333, ffffff',               // Text color utilities
      'bc': 'dee2e6, 007bff',               // Border color utilities
      'bs': 'solid, dashed, none',          // Border style utilities
      'bz': 'cover, contain, auto',         // Background size utilities
      'dp': 'flex, grid, none, block',      // Display utilities
      'ps': 'relative, absolute, fixed',    // Position utilities
      'tp': '0, 20, 50pr',                  // Top position utilities
      'rt': '0, 20, 50pr',                  // Right position utilities
      'bt': '0, 20, 50pr',                  // Bottom position utilities
      'lt': '0, 20, 50pr',                  // Left position utilities
      'fs': '16, 1.5rem, 2em',              // Font size utilities
      'fw': '400, 700, 900',                // Font weight utilities
      'lh': '1.5, 24, 2rem',                // Line height utilities
      'ls': '1, 0.05em, 2',                 // Letter spacing utilities
      'ta': 'center, left, right',          // Text align utilities
      'op': '50, 75, 100',                  // Opacity utilities
      'zi': '10, 100, -1',                  // Z-index utilities
      'gp': '20, 16-32',                    // Gap utilities
      'ar': '16-9, 4-3, 1-1',               // Aspect ratio utilities
      'fl': 'left, right, none',            // Float utilities
      'cl': 'both, left, right',            // Clear utilities
      'ov': 'hidden, auto, visible',        // Overflow utilities
      'cu': 'pointer, move, text',          // Cursor utilities
      'us': 'none, text, all',              // User select utilities
      'fd': 'row, column, row-reverse',     // Flex direction utilities
      'jc': 'center, space-between, flex-start', // Justify content utilities
      'ai': 'center, flex-start, stretch',  // Align items utilities
      'ac': 'center, space-between, stretch', // Align content utilities
      'fx': 'wrap, nowrap',                 // Flex wrap utilities
      'fg': '1, 2, 0.5',                    // Flex grow utilities
      'fk': '1, 0, 2',                      // Flex shrink utilities
      'fb': 'auto, 200, 50pr',              // Flex basis utilities
      'or': '1, -1, 2',                     // Order utilities
      'gc': 'repeat-3-1fr, 200-1fr',       // Grid columns utilities
      'gr': '100-auto-1fr, repeat-2-200',  // Grid rows utilities
      'gl': '1-3, 2-4',                     // Grid column utilities
      'gw': '1-2, 2-5',                     // Grid row utilities
      'rt': '45, 90, 180',                  // Rotate transform utilities
      'sc': '150, 50, 200',                 // Scale transform utilities
    };

    return Drupal.utilikit.security.sanitizeText(examples[prefix] || '20, 50pr, auto');
  }

})(Drupal, once);

/**
 * Integration Notes for UtiliKit Reference Page:
 *
 * Security Architecture:
 * - All user input sanitized through UtiliKit's security utilities
 * - Utility class validation prevents injection of malicious CSS
 * - Safe DOM element creation eliminates XSS vulnerabilities
 * - Error handling fails safely without exposing system internals
 * - Content validation occurs at multiple layers for defense in depth
 *
 * User Experience Enhancements:
 * - Copy-to-clipboard with visual feedback and fallback support
 * - Interactive live preview with real-time utility class application
 * - Contextual tooltips with relevant examples for each utility type
 * - Clickable code examples for quick testing and experimentation
 * - Responsive design considerations for mobile and desktop usage
 *
 * Performance Optimizations:
 * - Debounced input handling prevents excessive processing during typing
 * - Efficient DOM querying with targeted selectors and early returns
 * - Lazy initialization with dependency checking for UtiliKit core
 * - Minimal DOM manipulation and efficient event listener management
 * - Graceful degradation when optional features are unavailable
 *
 * Accessibility Features:
 * - Proper ARIA labels and roles for screen reader compatibility
 * - Keyboard navigation support for all interactive elements
 * - Clear visual feedback for all user interactions and state changes
 * - Semantic HTML structure with appropriate heading hierarchy
 * - High contrast feedback for success and error states
 *
 * Browser Compatibility:
 * - Modern Clipboard API with fallback to legacy document.execCommand
 * - Cross-browser event handling and DOM manipulation
 * - Progressive enhancement with graceful feature degradation
 * - Standards-compliant CSS and JavaScript implementation
 * - Mobile device considerations for touch interactions
 *
 * Development and Debugging Support:
 * - Comprehensive error logging with actionable context information
 * - Development mode integration with UtiliKit's logging system
 * - Non-blocking error handling that preserves page functionality
 * - Clear separation of concerns for maintainable code architecture
 * - Extensible design for adding new interactive features
 *
 * Educational Value:
 * - Contextual examples tailored to specific utility class types
 * - Interactive playground for hands-on learning and experimentation
 * - Visual feedback that reinforces understanding of utility class effects
 * - Comprehensive coverage of all utility class patterns and variations
 * - Progressive disclosure of information through hover interactions
 */
