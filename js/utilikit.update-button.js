/**
 * @file
 * UtiliKit Update Button System and CSS Regeneration Controller.
 *
 * This file implements the interactive update button that allows users to
 * manually trigger CSS regeneration when using UtiliKit in static mode.
 * The system automatically detects utility classes on the page, sends them
 * to the server for processing, and handles both inline and static CSS
 * mode updates with comprehensive error handling and user feedback.
 *
 * Key Features:
 * - Permission-based button rendering from server
 * - Automatic utility class detection and collection
 * - AJAX-based CSS update requests with CSRF protection
 * - Retry mechanism for system busy conditions
 * - Real-time user feedback with status messages
 * - Static CSS cache invalidation and refresh
 * - Inline CSS updates for immediate application
 * - Security validation and input sanitization
 *
 * The update process works differently based on rendering mode:
 * - Static Mode: Regenerates CSS file and refreshes browser cache
 * - Inline Mode: Re-applies classes dynamically to elements
 *
 * @see utilikit.behavior.js for class application logic
 * @see UtilikitAjaxController.php for server-side processing
 */

(function(Drupal, drupalSettings) {
  'use strict';

  /**
   * UtiliKit Update Button Behavior.
   *
   * Manages the lifecycle of the update button including permission-based
   * rendering, event attachment, and ensuring only one button exists per page.
   * The button is fetched from the server to include proper permission checks.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the update button behavior to the page when UtiliKit elements
   *   are present and the user has appropriate permissions.
   */
  Drupal.behaviors.utilikitUpdateButton = {
    /**
     * Attaches the update button behavior to the document.
     *
     * Checks for existing button to prevent duplicates, verifies the presence
     * of UtiliKit elements, and fetches the button HTML from the server with
     * proper permission validation.
     *
     * @param {Element} context
     *   The DOM element context for behavior attachment.
     */
    attach: function(context) {
      if (context !== document) return;

      // Check if button already exists
      const existingButton = document.getElementById('utilikit-update-button');
      if (existingButton) return;

      // Check if we have utilikit elements
      const hasUtilikit = document.querySelector('.utilikit');
      if (!hasUtilikit) return;

      // Fetch the button from server (includes permission check)
      fetch('/utilikit/render-button', {
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.text())
      .then(html => {
        if (html.trim()) {
          // Parse the HTML
          const wrapper = document.createElement('div');
          wrapper.innerHTML = html;
          const button = wrapper.firstElementChild;

          if (button) {
            document.body.appendChild(button);

            // Attach click handler
            button.addEventListener('click', function() {
              updateUtilikit();
            });
          }
        }
      })
      .catch(error => {
        console.error('Failed to load UtiliKit update button:', error);
      });
    }
  };

  /**
   * Initiates the UtiliKit CSS update process.
   *
   * Main controller function that handles the complete CSS update workflow:
   * 1. Updates button UI to show loading state
   * 2. Scans page for all utility classes
   * 3. Sends AJAX request to server for processing
   * 4. Handles server responses including retry logic
   * 5. Updates CSS and provides user feedback
   * 6. Resets button state on completion
   *
   * Supports both static and inline rendering modes with appropriate
   * post-update actions for each mode.
   */
  function updateUtilikit() {
    const button = document.getElementById('utilikit-update-button');
    const arrow = button.querySelector('.utilikit-update-arrow');
    const text = button.querySelector('.utilikit-update-text');

    // Store original content
    const originalArrowHtml = arrow.innerHTML;
    const originalText = text.textContent;

    // Show loading state
    arrow.innerHTML = '<span class="spinner"></span>';
    text.textContent = 'Updating...';
    button.disabled = true;
    button.classList.add('utilikit-updating');

    // Collect all utility classes from elements with 'utilikit' class
    const allElements = document.querySelectorAll('.utilikit');
    const classes = new Set();

    allElements.forEach(el => {
      el.classList.forEach(className => {
        // Collect ALL classes starting with 'uk-' without validation
        if (className.startsWith('uk-')) {
          classes.add(className);
        }
      });
    });

    // Get settings from drupalSettings
    const mode = drupalSettings.utilikit.renderingMode || 'inline';
    const csrfToken = drupalSettings.utilikit.csrfToken;

    // Track retry attempts
    let retryCount = 0;
    const maxRetries = 3;

    /**
     * Attempts to send the update request to the server.
     *
     * Internal function that handles the AJAX request and response processing
     * with built-in retry logic for system busy conditions. Manages all
     * possible response states including success, error, and retry scenarios.
     */
    function attemptUpdate() {
      fetch('/utilikit/update-css', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          classes: Array.from(classes),
          mode: mode
        })
      })
      .then(response => {
        if (!response.ok) {
          return response.text().then(text => {
            try {
              return JSON.parse(text);
            } catch (e) {
              console.error('Server error response:', text);
              throw new Error('Server error: ' + response.status);
            }
          });
        }
        return response.json();
      })
      .then(data => {
        const sanitizedData = sanitizeServerResponse(data);

        if (sanitizedData.status === 'locked' && retryCount < maxRetries) {
          retryCount++;

          showUpdateMessage(
            `System busy (attempt ${retryCount}/${maxRetries}). Retrying...`,
            'warning'
          );

          arrow.innerHTML = '<span class="spinner"></span>';
          text.textContent = `Waiting... (${retryCount}/${maxRetries})`;

          setTimeout(attemptUpdate, (sanitizedData.retry_after || 2) * 1000);

        } else if (sanitizedData.status === 'locked') {
          // Max retries reached
          arrow.innerHTML = originalArrowHtml;
          text.textContent = originalText;
          button.disabled = false;
          button.classList.remove('utilikit-updating');

          showUpdateMessage(
            'System is busy. Please try again in a few moments.',
            'error'
          );

        } else if (sanitizedData.status === 'success') {
          // Success!
          arrow.innerHTML = '✓';
          text.textContent = 'Updated!';
          button.classList.remove('utilikit-updating');
          button.classList.add('utilikit-success');

          const message = sanitizedData.message || `Updated ${sanitizedData.count || 0} classes successfully`;
          showUpdateMessage(message, 'success');

          if (mode === 'static') {
            refreshStaticCss(sanitizedData.timestamp);

            if (sanitizedData.css) {
              updateInlineStaticCss(sanitizedData.css);
            }
          } else if (mode === 'head') {
            // Head mode: update the style tag content directly
            if (sanitizedData.css) {
              const styleElement = document.getElementById('utilikit-head-mode');
              if (styleElement) {
                styleElement.textContent = sanitizedData.css;
              }
            }
          } else {
            if (Drupal.utilikit && Drupal.utilikit.applyClasses) {
              setTimeout(() => {
                const elements = document.querySelectorAll('.utilikit');
                Drupal.utilikit.applyClasses(elements);
              }, 100);
            }
          }

          // Reset button after delay
          setTimeout(() => {
            arrow.innerHTML = originalArrowHtml;
            text.textContent = originalText;
            button.disabled = false;
            button.classList.remove('utilikit-success');
          }, 4000);

        } else if (sanitizedData.status === 'error') {
          // Error occurred
          arrow.innerHTML = originalArrowHtml;
          text.textContent = originalText;
          button.disabled = false;
          button.classList.remove('utilikit-updating');

          showUpdateMessage(sanitizedData.message || 'Update failed', 'error');
        }
      })
      .catch(error => {
        arrow.innerHTML = originalArrowHtml;
        text.textContent = originalText;
        button.disabled = false;
        button.classList.remove('utilikit-updating');

        if (error.message.includes('Server error')) {
          showUpdateMessage('Server error occurred. Please check the logs.', 'error');
        } else {
          showUpdateMessage('Network error. Please check your connection.', 'error');
        }
        console.error('UtiliKit update failed:', error);
      });
    }

    attemptUpdate();
  }

  /**
   * Validates UtiliKit utility class name format and safety.
   *
   * Checks class names against the expected UtiliKit pattern to ensure
   * they are valid utility classes. Includes length validation to prevent
   * potential security issues with extremely long class names.
   *
   * @param {string} className
   *   The CSS class name to validate.
   *
   * @returns {boolean}
   *   TRUE if the class name follows valid UtiliKit format, FALSE otherwise.
   *
   * @example
   *   isValidUtilityClass('uk-pd--16');     // true
   *   isValidUtilityClass('uk-md-mg--auto'); // true
   *   isValidUtilityClass('invalid-class');  // false
   */
  function isValidUtilityClass(className) {
    const pattern = /^uk-(?:(?:sm|md|lg|xl|xxl)-)?[a-z]{2,4}--[a-zA-Z0-9\-_.%]+$/;
    return pattern.test(className) && className.length <= 200;
  }

  /**
   * Sanitizes server response data to prevent XSS attacks.
   *
   * Processes server response objects to ensure they contain only safe,
   * expected data types. Validates and sanitizes text fields while
   * converting numeric values to appropriate types.
   *
   * @param {Object} data
   *   The raw server response object to sanitize.
   *
   * @returns {Object}
   *   A sanitized response object with validated and cleaned fields.
   *
   * @example
   *   const serverData = { status: 'success', count: '42', message: 'Done!' };
   *   const clean = sanitizeServerResponse(serverData);
   *   // Returns: { status: 'success', count: 42, message: 'Done!' }
   */
  function sanitizeServerResponse(data) {
    const sanitized = {};

    if (data && typeof data === 'object') {
      if (typeof data.status === 'string') {
        sanitized.status = data.status.replace(/[<>&'"]/g, '');
      }
      if (typeof data.message === 'string') {
        sanitized.message = data.message.replace(/[<>&'"]/g, '');
      }
      if (typeof data.count === 'number' || typeof data.count === 'string') {
        sanitized.count = parseInt(data.count) || 0;
      }
      if (typeof data.timestamp === 'number' || typeof data.timestamp === 'string') {
        sanitized.timestamp = parseInt(data.timestamp) || 0;
      }
      if (typeof data.retry_after === 'number' || typeof data.retry_after === 'string') {
        sanitized.retry_after = Math.max(1, Math.min(10, parseInt(data.retry_after) || 2));
      }
      if (typeof data.css === 'string') {
        sanitized.css = data.css;
      }
    }

    return sanitized;
  }

  /**
   * Displays user feedback messages with automatic cleanup.
   *
   * Creates and displays status messages to inform users about update
   * progress, success, warnings, and errors. Messages automatically
   * disappear after a timeout period with smooth animations.
   *
   * @param {string} text
   *   The message text to display to the user.
   * @param {string} type
   *   The message type for styling ('info', 'success', 'warning', 'error').
   *
   * @example
   *   showUpdateMessage('CSS updated successfully!', 'success');
   *   showUpdateMessage('Connection failed', 'error');
   */
  function showUpdateMessage(text, type = 'info') {
    document.querySelectorAll('.utilikit-update-message').forEach(el => el.remove());

    const message = document.createElement('div');
    message.className = `utilikit-update-message utilikit-update-message--${type}`;

    const iconHtml = getMessageIcon(type);
    message.innerHTML = `<span class="message-icon">${iconHtml}</span><span class="message-text"></span>`;

    const textSpan = message.querySelector('.message-text');
    if (textSpan) {
      textSpan.textContent = text;
    }

    document.body.appendChild(message);

    setTimeout(() => {
      message.classList.add('utilikit-update-message-show');
    }, 10);

    const duration = type === 'error' ? 6000 : 4000;
    setTimeout(() => {
      message.classList.remove('utilikit-update-message-show');
      setTimeout(() => {
        if (message.parentNode) {
          message.remove();
        }
      }, 300);
    }, duration);
  }

  /**
   * Returns appropriate icon for message types.
   *
   * Provides unicode icons for different message types to enhance
   * visual feedback and improve user experience.
   *
   * @param {string} type
   *   The message type ('success', 'warning', 'error', 'info').
   *
   * @returns {string}
   *   Unicode icon character appropriate for the message type.
   */
  function getMessageIcon(type) {
    const icons = {
      success: '✓',
      warning: '⚠',
      error: '✕',
      info: 'ℹ'
    };
    return icons[type] || icons.info;
  }

  /**
   * Refreshes static CSS file link with cache invalidation.
   *
   * Updates the CSS file reference in the document head to force
   * browser reload of the updated static CSS file. Implements
   * progressive enhancement by replacing the old link only after
   * the new one loads successfully.
   *
   * @param {number} timestamp
   *   Server timestamp for cache-busting parameter.
   *
   * @example
   *   refreshStaticCss(1640995200); // Forces CSS reload with timestamp
   */
  function refreshStaticCss(timestamp) {
    const linkElement = document.getElementById('utilikit-static-css');
    if (linkElement) {
      const currentUrl = linkElement.href;
      const baseUrl = currentUrl.split('?')[0];

      const newUrl = baseUrl + '?v=' + Math.random().toString(36).substr(2, 9) + '&t=' + (timestamp || Date.now());

      const newLink = document.createElement('link');
      newLink.id = 'utilikit-static-css-new';
      newLink.rel = 'stylesheet';
      newLink.href = newUrl;
      newLink.media = 'all';

      newLink.onload = function() {
        if (linkElement.parentNode) {
          linkElement.parentNode.removeChild(linkElement);
        }
        newLink.id = 'utilikit-static-css';
      };

      linkElement.parentNode.insertBefore(newLink, linkElement.nextSibling);

      // Clear browser caches if Cache API is available
      if ('caches' in window) {
        caches.keys().then(names => {
          names.forEach(name => {
            caches.open(name).then(cache => {
              cache.delete(currentUrl);
              cache.delete(newUrl);
            });
          });
        });
      }
    }
  }

  /**
   * Updates inline static CSS content for immediate application.
   *
   * Creates or updates an inline style element with the latest CSS
   * content. This provides immediate visual feedback while the static
   * CSS file is being updated and cached.
   *
   * @param {string} css
   *   The CSS content to inject inline.
   *
   * @example
   *   updateInlineStaticCss('.uk-pd--16 { padding: 16px; }');
   */
  function updateInlineStaticCss(css) {
    let styleElement = document.getElementById('utilikit-static-inline');
    if (!styleElement) {
      styleElement = document.createElement('style');
      styleElement.id = 'utilikit-static-inline';
      document.head.appendChild(styleElement);
    }
    styleElement.textContent = css;
  }

})(Drupal, drupalSettings);
