/**
 * @file
 * UtiliKit Security Framework and XSS Prevention System.
 *
 * This file provides comprehensive security utilities and sanitization
 * functions to protect against XSS attacks and ensure safe handling of
 * user-generated content within the UtiliKit system. It implements multiple
 * layers of protection including input validation, output sanitization,
 * and secure DOM manipulation.
 *
 * Security Features:
 * - Text content sanitization to prevent script injection
 * - HTML sanitization with allowlist-based tag filtering
 * - CSS class name validation and escaping
 * - Server response sanitization
 * - Safe DOM element creation utilities
 * - Security patches for existing UtiliKit functions
 *
 * The security system follows defense-in-depth principles, validating
 * inputs at multiple stages and providing secure alternatives to
 * potentially dangerous operations.
 *
 * @see utilikit.behavior.js for secure element processing
 * @see utilikit.reference.js for secure copy functionality
 */

(function(Drupal, once, drupalSettings) {
  'use strict';

  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * UtiliKit Security Utilities Namespace.
   *
   * Provides a collection of security-focused functions for safe handling
   * of user input, DOM manipulation, and content sanitization within the
   * UtiliKit ecosystem.
   *
   * @namespace
   */
  Drupal.utilikit.security = {

    /**
     * Sanitizes text content to prevent XSS attacks.
     *
     * Uses the browser's built-in HTML parsing to safely encode any
     * potentially dangerous characters. This function converts all HTML
     * entities and special characters to their safe encoded equivalents.
     *
     * @param {string} text
     *   The text content to sanitize.
     *
     * @returns {string}
     *   The sanitized text with HTML entities encoded.
     *
     * @example
     *   // Dangerous input
     *   const userInput = '<script>alert("xss")</script>';
     *   // Safe output
     *   const safe = Drupal.utilikit.security.sanitizeText(userInput);
     *   // Result: '&lt;script&gt;alert("xss")&lt;/script&gt;'
     */
    sanitizeText: function(text) {
      if (typeof text !== 'string') {
        return '';
      }

      // Create a temporary element to leverage browser's HTML parsing for safety
      const temp = document.createElement('div');
      temp.textContent = text;
      return temp.innerHTML;
    },

    /**
     * Sanitizes HTML content using allowlist-based tag filtering.
     *
     * Processes HTML content to remove any potentially dangerous elements
     * while preserving allowed tags. All attributes are stripped from
     * allowed elements to prevent attribute-based attacks. Non-allowed
     * elements are replaced with their text content.
     *
     * @param {string} html
     *   The HTML content to sanitize.
     * @param {Array<string>} allowedTags
     *   Array of allowed HTML tag names (lowercase). Defaults to empty array.
     *
     * @returns {string}
     *   The sanitized HTML with only allowed tags preserved.
     *
     * @example
     *   const dirtyHtml = '<p>Safe text</p><script>alert("bad")</script>';
     *   const cleanHtml = Drupal.utilikit.security.sanitizeHtml(
     *     dirtyHtml,
     *     ['p', 'strong', 'em']
     *   );
     *   // Result: '<p>Safe text</p>alert("bad")'
     */
    sanitizeHtml: function(html, allowedTags = []) {
      if (typeof html !== 'string') {
        return '';
      }

      // If no tags allowed, just return text content
      if (allowedTags.length === 0) {
        return this.sanitizeText(html);
      }

      // Use DOMParser to parse without executing scripts or event handlers (XSS-safe).
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const root = doc.body;

      // Remove all non-allowed elements (walk in reverse so removal doesn't break iteration)
      const allElements = Array.from(root.querySelectorAll('*'));
      allElements.forEach(element => {
        if (!allowedTags.includes(element.tagName.toLowerCase())) {
          const textNode = doc.createTextNode(element.textContent);
          element.parentNode.replaceChild(textNode, element);
        } else {
          while (element.attributes.length > 0) {
            element.removeAttribute(element.attributes[0].name);
          }
        }
      });

      return root.innerHTML;
    },

    /**
     * Validates and sanitizes CSS class names for security.
     *
     * Ensures class names contain only safe characters and are within
     * reasonable length limits. Prevents CSS injection attacks and
     * malformed class names that could break styling or cause errors.
     *
     * @param {string} className
     *   The CSS class name to validate.
     *
     * @returns {string|null}
     *   The validated class name if safe, null if invalid or dangerous.
     *
     * @example
     *   const safeName = Drupal.utilikit.security.validateClassName('my-class');
     *   // Result: 'my-class'
     *
     *   const dangerousName = Drupal.utilikit.security.validateClassName('class;color:red');
     *   // Result: null
     */
    validateClassName: function(className) {
      if (typeof className !== 'string') {
        return null;
      }

      // Only allow alphanumeric, hyphens, underscores
      const validPattern = /^[a-zA-Z0-9\-_]+$/;
      if (!validPattern.test(className)) {
        return null;
      }

      // Must be reasonable length
      if (className.length > 200) {
        return null;
      }

      return className;
    },

    /**
     * Escapes CSS selectors to prevent CSS injection attacks.
     *
     * Uses the browser's CSS.escape() method when available, with a
     * fallback implementation for older browsers. Properly escapes
     * special characters that could be used for CSS injection.
     *
     * @param {string} selector
     *   The CSS selector string to escape.
     *
     * @returns {string}
     *   The escaped CSS selector safe for use in stylesheets.
     *
     * @example
     *   const selector = Drupal.utilikit.security.escapeCssSelector('my:class');
     *   // Result: 'my\\:class'
     */
    escapeCssSelector: function(selector) {
      if (typeof selector !== 'string') {
        return '';
      }

      // CSS.escape() is the standard way, with fallback
      if (typeof CSS !== 'undefined' && CSS.escape) {
        return CSS.escape(selector);
      }

      // Fallback manual escaping for older browsers
      return selector.replace(/[!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~]/g, '\\$&');
    },

    /**
     * Validates UtiliKit utility class name format.
     *
     * Checks if a class name follows the correct UtiliKit naming convention
     * and contains only safe characters. Validates the prefix structure,
     * responsive breakpoints, and value patterns used by UtiliKit.
     *
     * @param {string} className
     *   The class name to validate against UtiliKit patterns.
     *
     * @returns {boolean}
     *   TRUE if the class follows valid UtiliKit format, FALSE otherwise.
     *
     * @example
     *   const isValid = Drupal.utilikit.security.isValidUtilityClass('uk-pd--16');
     *   // Result: true
     *
     *   const isInvalid = Drupal.utilikit.security.isValidUtilityClass('malicious-class');
     *   // Result: false
     */
    isValidUtilityClass: function(className) {
      if (typeof className !== 'string') {
        return false;
      }

      // Must start with uk-
      if (!className.startsWith('uk-')) {
        return false;
      }

      // Validate against the pattern
      const pattern = /^uk-(?:(?:sm|md|lg|xl|xxl)-)?[a-z]{2,4}--[a-zA-Z0-9\-_.%]+$/;
      return pattern.test(className);
    },

    /**
     * Sanitizes server response objects for safe consumption.
     *
     * Processes AJAX and API responses to ensure they contain only
     * safe, expected data types. Sanitizes text fields and validates
     * numeric values to prevent injection attacks through server responses.
     *
     * @param {*} response
     *   The server response object to sanitize.
     *
     * @returns {Object}
     *   A sanitized response object with validated fields.
     *
     * @example
     *   const serverResponse = {
     *     message: '<script>alert("xss")</script>',
     *     count: '42',
     *     malicious: 'ignored'
     *   };
     *   const safe = Drupal.utilikit.security.sanitizeServerResponse(serverResponse);
     *   // Result: { message: '&lt;script&gt;...', count: 42 }
     */
    sanitizeServerResponse: function(response) {
      const sanitized = {};

      if (response && typeof response === 'object') {
        // Sanitize common response fields
        if (response.message) {
          sanitized.message = this.sanitizeText(response.message);
        }
        if (response.status) {
          sanitized.status = this.sanitizeText(response.status);
        }
        if (response.count !== undefined) {
          sanitized.count = parseInt(response.count) || 0;
        }
        if (response.timestamp !== undefined) {
          sanitized.timestamp = parseInt(response.timestamp) || 0;
        }
      }

      return sanitized;
    },

    /**
     * Creates safe DOM elements with validated content and attributes.
     *
     * Provides a secure alternative to createElement by validating tag names,
     * sanitizing text content, and filtering attributes to prevent XSS
     * attacks. Only allows safe HTML tags and attributes.
     *
     * @param {string} tagName
     *   The HTML tag name for the element (must be in allowlist).
     * @param {string} textContent
     *   Text content for the element (will be sanitized).
     * @param {Object} attributes
     *   Object of attributes to set (will be filtered and validated).
     *
     * @returns {Element}
     *   A safe DOM element with sanitized content and attributes.
     *
     * @example
     *   const safeBtn = Drupal.utilikit.security.createSafeElement(
     *     'button',
     *     'Click me',
     *     { class: 'btn-primary', id: 'safe-button' }
     *   );
     */
    createSafeElement: function(tagName, textContent = '', attributes = {}) {
      // Validate tag name
      const allowedTags = ['div', 'span', 'p', 'button', 'strong', 'em', 'code', 'pre'];
      if (!allowedTags.includes(tagName.toLowerCase())) {
        tagName = 'div'; // Default to safe tag
      }

      const element = document.createElement(tagName);

      // Set text content safely
      if (textContent) {
        element.textContent = textContent;
      }

      // Set safe attributes (setAttribute escapes automatically; do not double-encode).
      const allowedAttributes = ['class', 'id', 'title', 'aria-label', 'aria-describedby', 'aria-live', 'aria-hidden', 'role', 'type'];
      Object.keys(attributes).forEach(attr => {
        if (allowedAttributes.includes(attr.toLowerCase())) {
          const value = attributes[attr];
          if (value !== undefined && value !== null && value !== '') {
            element.setAttribute(attr, String(value));
          }
        }
      });

      return element;
    }
  };

  /**
   * Safely sets HTML content using allowlist-based sanitization.
   *
   * Provides a secure alternative to innerHTML by sanitizing content
   * before insertion. Uses the allowlist approach to preserve only
   * explicitly permitted HTML tags while removing potentially dangerous
   * elements and attributes.
   *
   * @param {Element} element
   *   The DOM element to set content on.
   * @param {string} html
   *   The HTML content to set (will be sanitized).
   * @param {Array<string>} allowedTags
   *   Array of HTML tag names to preserve. Defaults to empty array.
   *
   * @example
   *   const container = document.getElementById('content');
   *   Drupal.utilikit.safeSetHtml(
   *     container,
   *     '<p>Safe content</p><script>dangerous()</script>',
   *     ['p', 'strong', 'em']
   *   );
   *   // Only the <p> tag will be preserved
   */
  Drupal.utilikit.safeSetHtml = function(element, html, allowedTags = []) {
    if (!element || !element.nodeType) {
      return;
    }

    const sanitized = Drupal.utilikit.security.sanitizeHtml(html, allowedTags);
    element.innerHTML = sanitized;
  };

  /**
   * Displays safe user messages with XSS protection.
   *
   * Creates and displays user notification messages using secure DOM
   * manipulation methods. Automatically removes messages after a timeout
   * and ensures all content is properly sanitized.
   *
   * @param {string} text
   *   The message text to display (will be sanitized).
   * @param {string} type
   *   The message type for styling ('info', 'success', 'warning', 'error').
   * @param {string} iconHtml
   *   Optional icon HTML content (will be sanitized).
   *
   * @example
   *   Drupal.utilikit.showSafeMessage(
   *     'Settings saved successfully!',
   *     'success',
   *     '<span class="icon-check"></span>'
   *   );
   */
  Drupal.utilikit.showSafeMessage = function(text, type = 'info', iconHtml = '') {
    const messageContainer = Drupal.utilikit.security.createSafeElement('div', '', {
      'class': `utilikit-update-message utilikit-update-message--${type}`,
      'role': 'alert',
      'aria-live': 'polite'
    });

    // Create icon element safely
    if (iconHtml) {
      const iconElement = Drupal.utilikit.security.createSafeElement('span', iconHtml, {
        'class': 'message-icon',
        'aria-hidden': 'true'
      });
      messageContainer.appendChild(iconElement);
    }

    // Create text element safely
    const textElement = Drupal.utilikit.security.createSafeElement('span', text, {
      'class': 'message-text'
    });
    messageContainer.appendChild(textElement);

    document.body.appendChild(messageContainer);

    // Auto-remove with cleanup
    setTimeout(() => {
      if (messageContainer.parentNode) {
        messageContainer.parentNode.removeChild(messageContainer);
      }
    }, 3000);
  };

})(Drupal, once, drupalSettings);

/**
 * @file
 * Security Patches for Existing UtiliKit Functions.
 *
 * This section applies security patches to existing UtiliKit behaviors
 * and functions to retrofit them with XSS protection and input validation.
 * These patches wrap existing functionality with security checks without
 * breaking the original API.
 */
(function(Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Security patch for UtiliKit Reference page behavior.
   *
   * Wraps the reference page attach function to add security validation
   * for any unsafe HTML content. Processes elements marked with
   * data-unsafe-html attributes and sanitizes them using allowlisted tags.
   */
  if (Drupal.behaviors.utilikitReference) {
    const originalAttach = Drupal.behaviors.utilikitReference.attach;
    Drupal.behaviors.utilikitReference.attach = function(context, settings) {
      // Call original but with security patches
      originalAttach.call(this, context, settings);

      // Patch any unsafe innerHTML usage
      context.querySelectorAll('.utilikit-reference-page [data-unsafe-html]').forEach(element => {
        const unsafeHtml = element.getAttribute('data-unsafe-html');
        element.removeAttribute('data-unsafe-html');
        Drupal.utilikit.safeSetHtml(element, unsafeHtml, ['strong', 'em', 'code']);
      });
    };
  }

  /**
   * Security patch for UtiliKit class application system.
   *
   * Defers until applyClasses is defined (classes.js loads after security.js),
   * then wraps it to validate elements and CSS classes before processing.
   */
  (function tryPatchApplyClasses(attempts) {
    if (Drupal.utilikit && Drupal.utilikit.applyClasses && !Drupal.utilikit.applyClasses._securityWrapped) {
      const originalApplyClasses = Drupal.utilikit.applyClasses;
      Drupal.utilikit.applyClasses = function(elements) {
        try {
          const safeElements = Array.from(elements).filter(el => {
            if (!el || !el.classList) return false;
            const classes = Array.from(el.classList);
            return classes.every(className => {
              return Drupal.utilikit.security.isValidUtilityClass(className) ||
                     className === 'utilikit' ||
                     Drupal.utilikit.security.validateClassName(className);
            });
          });
          if (safeElements.length > 0) {
            originalApplyClasses.call(this, safeElements);
          }
          if (safeElements.length < elements.length) {
            console.warn('UtiliKit: Some elements filtered out for security reasons');
          }
        } catch (error) {
          console.error('UtiliKit: Security error in applyClasses:', error);
        }
      };
      Drupal.utilikit.applyClasses._securityWrapped = true;
      return;
    }
    if (attempts < 50) {
      setTimeout(function() { tryPatchApplyClasses(attempts + 1); }, 20);
    }
  })(0);

  /**
   * Security notification for update button functionality.
   *
   * Logs a warning if the updateUtilikit function exists but hasn't been
   * properly patched with security measures. This serves as a reminder
   * to apply security patches to the update button implementation.
   */
  if (typeof updateUtilikit === 'function') {
    // This would need to be applied to the update button code
    console.warn('UtiliKit: Update button needs security patching');
  }

})(Drupal, once, drupalSettings);
