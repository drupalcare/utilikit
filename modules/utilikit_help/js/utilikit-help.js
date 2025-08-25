/**
 * @file
 * UtiliKit Help Page JavaScript - Interactive documentation functionality.
 *
 * Provides comprehensive interactive features for the UtiliKit help system
 * including responsive tab navigation, code copying, expandable sections,
 * performance monitoring, and educational enhancements. Creates an engaging
 * documentation experience with accessibility support and mobile optimization.
 */

(function(Drupal, once, drupalSettings) {
  'use strict';

  /**
   * UtiliKit Help Engine Behavior - Forces inline rendering for examples.
   *
   * Ensures all UtiliKit examples in the help documentation render using
   * inline mode regardless of global settings, providing immediate visual
   * feedback for educational purposes.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches inline rendering to UtiliKit elements in help context.
   */
  Drupal.behaviors.utilikitHelp = {
    attach: function(context, settings) {
      // Always use inline mode regardless of global settings
      const elements = once('utilikit-examples', '.utilikit', context);
      if (elements.length > 0) {
        // Force apply classes using inline engine
        if (typeof Drupal.utilikit !== 'undefined' && typeof Drupal.utilikit.applyClasses === 'function') {
          Drupal.utilikit.applyClasses(elements);
        }
      }
    }
  };
})(Drupal, once, drupalSettings);

(function(Drupal, once) {
  'use strict';

  /**
   * UtiliKit Help Engine Behavior - Secondary inline rendering handler.
   *
   * Provides redundant inline rendering functionality to ensure all
   * UtiliKit examples display correctly in the help documentation.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches additional inline rendering support.
   */
  Drupal.behaviors.utilikitHelpEngine = {
    attach: function(context, settings) {
      // Always use inline mode regardless of global settings
      const elements = once('utilikit-examples', '.utilikit', context);
      if (elements.length > 0) {
        // Force apply classes using inline engine
        if (typeof Drupal.utilikit !== 'undefined' && typeof Drupal.utilikit.applyClasses === 'function') {
          Drupal.utilikit.applyClasses(elements);
        }
      }
    }
  };

  /**
   * UtiliKit Help Page Main Behavior.
   *
   * Initializes the comprehensive help page functionality including
   * responsive tabs, interactive elements, performance monitoring,
   * and accessibility features.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches all help page interactive functionality.
   */
  Drupal.behaviors.utilikitHelp = {
    attach: function(context, settings) {
      once('utilikit-help', '.utilikit-help-page', context).forEach(function(helpPage) {
        initializeHelpPage(helpPage);
      });
    }
  };

  /**
   * Initializes the complete help page functionality.
   *
   * Orchestrates the setup of all interactive features including responsive
   * tab navigation, accessibility support, performance monitoring, and
   * educational enhancements.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeHelpPage(helpPage) {
    const tabsWrapper = helpPage.querySelector('.vertical-tabs-wrapper');
    if (!tabsWrapper) return;

    // Initialize responsive tabs
    initializeResponsiveTabs(tabsWrapper);

    // Initialize tab switching
    initializeTabSwitching(tabsWrapper);

    // Initialize responsive features
    initializeResponsiveFeatures(helpPage);

    // Initialize interactive elements
    initializeInteractiveElements(helpPage);

    // Initialize performance monitoring
    initializePerformanceMonitoring(helpPage);
  }

  /**
   * Initializes responsive tab behavior for different screen sizes.
   *
   * Handles the transformation between vertical tabs (desktop) and
   * horizontal navigation (mobile) with smooth transitions and
   * proper event handling.
   *
   * @param {Element} tabsWrapper
   *   The tabs wrapper container element.
   */
  function initializeResponsiveTabs(tabsWrapper) {
    const breakpoint = parseInt(tabsWrapper.dataset.responsiveTabs) || 992;

    /**
     * Handles window resize events and applies appropriate tab layout.
     */
    function handleResize() {
      const windowWidth = window.innerWidth;

      if (windowWidth <= breakpoint) {
        // Mobile mode: add mobile classes and behavior
        tabsWrapper.classList.add('mobile-tabs');
        setupMobileNavigation(tabsWrapper);
      } else {
        // Desktop mode: remove mobile classes
        tabsWrapper.classList.remove('mobile-tabs', 'nav-open');
        removeMobileNavigation(tabsWrapper);
      }
    }

    // Initial setup
    handleResize();

    // Listen for resize events with debouncing
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(handleResize, 150);
    });
  }

  /**
   * Sets up mobile navigation toggle functionality.
   *
   * Creates touch-friendly navigation for mobile devices with smooth
   * animations and proper event handling for tab menu opening/closing.
   *
   * @param {Element} tabsWrapper
   *   The tabs wrapper container element.
   */
  function setupMobileNavigation(tabsWrapper) {
    // Remove existing click handler
    tabsWrapper.removeEventListener('click', tabsWrapper._mobileToggleHandler);

    // Create new handler
    tabsWrapper._mobileToggleHandler = function(event) {
      // Check if click is on the pseudo-element area (navigation toggle)
      if (event.target === tabsWrapper && event.offsetY <= 60) {
        tabsWrapper.classList.toggle('nav-open');

        // Smooth scroll animation for nav opening
        if (tabsWrapper.classList.contains('nav-open')) {
          const nav = tabsWrapper.querySelector('.vertical-tabs-nav');
          if (nav) {
            nav.style.maxHeight = nav.scrollHeight + 'px';
            setTimeout(() => {
              nav.style.maxHeight = '';
            }, 300);
          }
        }
      }
    };

    // Add click handler for mobile toggle
    tabsWrapper.addEventListener('click', tabsWrapper._mobileToggleHandler);
  }

  /**
   * Removes mobile navigation setup and event handlers.
   *
   * Cleans up mobile-specific event listeners and functionality
   * when switching back to desktop layout.
   *
   * @param {Element} tabsWrapper
   *   The tabs wrapper container element.
   */
  function removeMobileNavigation(tabsWrapper) {
    if (tabsWrapper._mobileToggleHandler) {
      tabsWrapper.removeEventListener('click', tabsWrapper._mobileToggleHandler);
      delete tabsWrapper._mobileToggleHandler;
    }
  }

  /**
   * Initializes tab switching functionality with accessibility support.
   *
   * Sets up click and keyboard navigation for tabs, URL hash support
   * for direct linking, and proper ARIA attributes for screen readers.
   *
   * @param {Element} tabsWrapper
   *   The tabs wrapper container element.
   */
  function initializeTabSwitching(tabsWrapper) {
    const tabButtons = tabsWrapper.querySelectorAll('.tab-button');
    const tabContents = tabsWrapper.querySelectorAll('.tab-content');

    tabButtons.forEach(function(button) {
      button.addEventListener('click', function() {
        const targetTab = this.dataset.tab;
        switchToTab(targetTab, tabButtons, tabContents, tabsWrapper);
      });

      // Keyboard navigation
      button.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          this.click();
        }
      });
    });

    // URL hash support for direct linking
    initializeHashNavigation(tabButtons, tabContents, tabsWrapper);
  }

  /**
   * Switches to a specific tab with proper state management.
   *
   * Updates button states, content visibility, URL hash, and handles
   * mobile navigation closing with smooth scrolling animations.
   *
   * @param {string} targetTab
   *   The ID of the tab to switch to.
   * @param {NodeList} tabButtons
   *   Collection of all tab button elements.
   * @param {NodeList} tabContents
   *   Collection of all tab content elements.
   * @param {Element} tabsWrapper
   *   The tabs wrapper container element.
   */
  function switchToTab(targetTab, tabButtons, tabContents, tabsWrapper) {
    // Update button states
    tabButtons.forEach(function(btn) {
      btn.classList.remove('active');
      btn.setAttribute('aria-selected', 'false');
    });

    // Update content states
    tabContents.forEach(function(content) {
      content.classList.remove('active');
    });

    // Activate target tab
    const activeButton = tabsWrapper.querySelector(`[data-tab="${targetTab}"]`);
    const activeContent = tabsWrapper.querySelector(`#${targetTab}`);

    if (activeButton && activeContent) {
      activeButton.classList.add('active');
      activeButton.setAttribute('aria-selected', 'true');
      activeContent.classList.add('active');

      // Update URL hash without scrolling
      updateUrlHash(targetTab);

      // Close mobile navigation after selection
      if (tabsWrapper.classList.contains('mobile-tabs')) {
        tabsWrapper.classList.remove('nav-open');
      }

      // Smooth scroll to content on mobile
      if (window.innerWidth <= 992) {
        setTimeout(function() {
          activeContent.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }, 100);
      }

      // Trigger custom event for analytics or other listeners
      dispatchTabChangeEvent(targetTab, activeContent);
    }
  }

  /**
   * Initializes URL hash navigation for deep linking support.
   *
   * Handles initial page load with hash fragments and browser
   * back/forward navigation to maintain proper tab state.
   *
   * @param {NodeList} tabButtons
   *   Collection of all tab button elements.
   * @param {NodeList} tabContents
   *   Collection of all tab content elements.
   * @param {Element} tabsWrapper
   *   The tabs wrapper container element.
   */
  function initializeHashNavigation(tabButtons, tabContents, tabsWrapper) {
    // Handle initial hash
    const initialHash = window.location.hash.substring(1);
    if (initialHash && document.getElementById(initialHash)) {
      switchToTab(initialHash, tabButtons, tabContents, tabsWrapper);
    }

    // Handle hash changes (back/forward navigation)
    window.addEventListener('hashchange', function() {
      const hash = window.location.hash.substring(1);
      if (hash && document.getElementById(hash)) {
        switchToTab(hash, tabButtons, tabContents, tabsWrapper);
      }
    });
  }

  /**
   * Updates URL hash without triggering page scroll.
   *
   * Uses modern History API when available, falls back to direct
   * hash modification for older browsers.
   *
   * @param {string} tabId
   *   The tab ID to set in the URL hash.
   */
  function updateUrlHash(tabId) {
    if (history.replaceState) {
      history.replaceState(null, null, '#' + tabId);
    } else {
      window.location.hash = tabId;
    }
  }

  /**
   * Dispatches custom tab change event for analytics and integrations.
   *
   * Creates a custom event with tab details that can be consumed
   * by analytics systems or other JavaScript components.
   *
   * @param {string} tabId
   *   The ID of the activated tab.
   * @param {Element} tabContent
   *   The content element for the activated tab.
   */
  function dispatchTabChangeEvent(tabId, tabContent) {
    const event = new CustomEvent('utilikitTabChange', {
      detail: {
        tabId: tabId,
        tabContent: tabContent,
        timestamp: new Date().toISOString()
      }
    });
    document.dispatchEvent(event);
  }

  /**
   * Initializes responsive features for enhanced user experience.
   *
   * Sets up screen size indicators, smooth scrolling for internal
   * links, and responsive behavior monitoring.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeResponsiveFeatures(helpPage) {
    // Current screen size indicator for responsive demo
    const currentSizeSpan = helpPage.querySelector('#current-size');
    if (currentSizeSpan) {
      updateCurrentSize(currentSizeSpan);

      let sizeTimeout;
      window.addEventListener('resize', function() {
        clearTimeout(sizeTimeout);
        sizeTimeout = setTimeout(function() {
          updateCurrentSize(currentSizeSpan);
        }, 100);
      });
    }

    // Smooth scroll for internal links
    helpPage.addEventListener('click', function(event) {
      const link = event.target.closest('a[href^="#"]');
      if (link) {
        event.preventDefault();
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      }
    });
  }

  /**
   * Updates the current screen size display with animation.
   *
   * Shows the current breakpoint category and animates the change
   * to provide visual feedback during responsive testing.
   *
   * @param {Element} element
   *   The element to update with screen size information.
   */
  function updateCurrentSize(element) {
    const width = window.innerWidth;
    let size = 'Mobile';

    if (width >= 1400) size = 'XXL (1400px+)';
    else if (width >= 1200) size = 'XL (1200px+)';
    else if (width >= 992) size = 'Large (992px+)';
    else if (width >= 768) size = 'Medium (768px+)';
    else if (width >= 576) size = 'Small (576px+)';
    else size = 'Mobile (0px+)';

    element.textContent = size;

    // Add a subtle animation
    element.style.transform = 'scale(1.1)';
    element.style.color = '#007bff';
    setTimeout(function() {
      element.style.transform = '';
      element.style.color = '';
    }, 200);
  }

  /**
   * Initializes all interactive elements in the help page.
   *
   * Orchestrates the setup of code copying, expandable sections,
   * interactive examples, and tooltip functionality.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeInteractiveElements(helpPage) {
    // Code copy functionality
    initializeCodeCopy(helpPage);

    // Expandable code sections
    initializeExpandableCode(helpPage);

    // Interactive examples
    initializeInteractiveExamples(helpPage);

    // Tooltip functionality for technical terms
    initializeTooltips(helpPage);
  }

  /**
   * Initializes copy-to-clipboard functionality for code blocks.
   *
   * Adds hover-revealed copy buttons to all code blocks with
   * modern Clipboard API support and legacy fallbacks.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeCodeCopy(helpPage) {
    const codeBlocks = helpPage.querySelectorAll('pre code');

    codeBlocks.forEach(function(codeBlock) {
      const pre = codeBlock.parentElement;

      // Create copy button
      const copyButton = document.createElement('button');
      copyButton.className = 'code-copy-btn';
      copyButton.innerHTML = 'Copy';
      copyButton.setAttribute('aria-label', 'Copy code to clipboard');

      // Position button
      pre.style.position = 'relative';
      copyButton.style.position = 'absolute';
      copyButton.style.bottom = '0';
      copyButton.style.right = '10px';
      copyButton.style.padding = '5px 10px';
      copyButton.style.background = '#007bff';
      copyButton.style.color = 'white';
      copyButton.style.border = 'none';
      copyButton.style.borderRadius = '4px';
      copyButton.style.cursor = 'pointer';
      copyButton.style.fontSize = '0.8rem';
      copyButton.style.opacity = '0';
      copyButton.style.transition = 'opacity 0.3s ease';

      pre.appendChild(copyButton);

      // Show button on hover
      pre.addEventListener('mouseenter', function() {
        copyButton.style.opacity = '1';
      });

      pre.addEventListener('mouseleave', function() {
        copyButton.style.opacity = '0';
      });

      // Copy functionality
      copyButton.addEventListener('click', function(event) {
        event.preventDefault();

        const textToCopy = codeBlock.textContent;

        if (navigator.clipboard) {
          navigator.clipboard.writeText(textToCopy).then(function() {
            showCopySuccess(copyButton);
          }).catch(function() {
            fallbackCopyTextToClipboard(textToCopy, copyButton);
          });
        } else {
          fallbackCopyTextToClipboard(textToCopy, copyButton);
        }
      });
    });
  }

  /**
   * Shows visual feedback for successful copy operations.
   *
   * Temporarily changes button appearance to indicate successful
   * clipboard operation with automatic restoration.
   *
   * @param {Element} button
   *   The copy button element to update.
   */
  function showCopySuccess(button) {
    const originalText = button.innerHTML;
    button.innerHTML = 'Copied!';
    button.style.background = '#28a745';

    setTimeout(function() {
      button.innerHTML = originalText;
      button.style.background = '#007bff';
    }, 2000);
  }

  /**
   * Fallback copy method for browsers without Clipboard API.
   *
   * Uses the legacy execCommand method with temporary textarea
   * for copying text to clipboard.
   *
   * @param {string} text
   *   The text content to copy to clipboard.
   * @param {Element} button
   *   The copy button element for feedback display.
   */
  function fallbackCopyTextToClipboard(text, button) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      const successful = document.execCommand('copy');
      if (successful) {
        showCopySuccess(button);
      } else {
        showCopyError(button);
      }
    } catch (err) {
      showCopyError(button);
    }

    document.body.removeChild(textArea);
  }

  /**
   * Shows visual feedback for failed copy operations.
   *
   * Displays error state on copy button with automatic restoration
   * to original state after timeout.
   *
   * @param {Element} button
   *   The copy button element to update.
   */
  function showCopyError(button) {
    const originalText = button.innerHTML;
    button.innerHTML = 'Failed';
    button.style.background = '#dc3545';

    setTimeout(function() {
      button.innerHTML = originalText;
      button.style.background = '#007bff';
    }, 2000);
  }

  /**
   * Initializes expandable code sections with smooth animations.
   *
   * Adds smooth expand/collapse animations to HTML details elements
   * containing code examples for better user experience.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeExpandableCode(helpPage) {
    const details = helpPage.querySelectorAll('details.code-details');

    details.forEach(function(detail) {
      const summary = detail.querySelector('summary');

      summary.addEventListener('click', function() {
        // Add smooth animation
        if (detail.open) {
          // Closing
          setTimeout(function() {
            detail.style.maxHeight = detail.scrollHeight + 'px';
            requestAnimationFrame(function() {
              detail.style.maxHeight = summary.scrollHeight + 'px';
            });
          }, 0);
        } else {
          // Opening
          setTimeout(function() {
            detail.style.maxHeight = detail.scrollHeight + 'px';
            setTimeout(function() {
              detail.style.maxHeight = '';
            }, 300);
          }, 0);
        }
      });
    });
  }

  /**
   * Initializes interactive examples with hover effects and click actions.
   *
   * Adds visual feedback for example cards and click-to-copy
   * functionality for inline code elements.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeInteractiveExamples(helpPage) {
    // Add hover effects to example cards
    const exampleCards = helpPage.querySelectorAll('.example-card');

    exampleCards.forEach(function(card) {
      card.addEventListener('mouseenter', function() {
        const demo = this.querySelector('.example-demo');
        if (demo) {
          demo.style.transform = 'scale(1.02)';
          demo.style.transition = 'transform 0.3s ease';
        }
      });

      card.addEventListener('mouseleave', function() {
        const demo = this.querySelector('.example-demo');
        if (demo) {
          demo.style.transform = '';
        }
      });
    });

    // Add click-to-highlight functionality for code examples
    const inlineCode = helpPage.querySelectorAll('code:not(pre code)');
    inlineCode.forEach(function(code) {
      code.addEventListener('click', function() {
        // Highlight effect
        this.style.background = '#ffeb3b';
        this.style.transition = 'background 0.3s ease';

        setTimeout(() => {
          this.style.background = '';
        }, 1000);

        // Copy to clipboard
        if (navigator.clipboard) {
          navigator.clipboard.writeText(this.textContent);
        }
      });

      // Add cursor pointer to indicate it's clickable
      code.style.cursor = 'pointer';
      code.title = 'Click to copy';
    });
  }

  /**
   * Initializes tooltips for technical terms throughout the help page.
   *
   * Automatically identifies technical terms and adds hover tooltips
   * with definitions to improve educational value.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializeTooltips(helpPage) {
    const technicalTerms = {
      'breakpoint': 'A specific screen width where the layout changes to accommodate different device sizes.',
      'utility class': 'A CSS class that applies a single, specific style property.',
      'responsive design': 'Design approach that makes web pages render well on different devices and screen sizes.',
      'CSS optimization': 'The process of reducing CSS file size and improving performance.',
      'cache busting': 'Technique to force browsers to download new versions of files instead of using cached versions.'
    };

    Object.keys(technicalTerms).forEach(function(term) {
      const regex = new RegExp(`\\b${term}\\b`, 'gi');
      const walker = document.createTreeWalker(
        helpPage,
        NodeFilter.SHOW_TEXT,
        null,
        false
      );

      const textNodes = [];
      let node;
      while (node = walker.nextNode()) {
        if (node.parentElement && !node.parentElement.closest('code, pre')) {
          textNodes.push(node);
        }
      }

      textNodes.forEach(function(textNode) {
        if (regex.test(textNode.textContent)) {
          const span = document.createElement('span');
          span.innerHTML = textNode.textContent.replace(regex, function(match) {
            return `<abbr class="help-tooltip" title="${technicalTerms[term.toLowerCase()]}">${match}</abbr>`;
          });
          textNode.parentNode.replaceChild(span, textNode);
        }
      });
    });

    // Style tooltips
    const style = document.createElement('style');
    style.textContent = `
      .help-tooltip {
        border-bottom: 1px dotted #007bff;
        cursor: help;
        text-decoration: none;
      }
      .help-tooltip:hover {
        color: #007bff;
      }
    `;
    document.head.appendChild(style);
  }

  /**
   * Initializes performance monitoring and analytics tracking.
   *
   * Sets up tab interaction tracking, scroll depth monitoring,
   * and page load performance measurement for optimization insights.
   *
   * @param {Element} helpPage
   *   The main help page container element.
   */
  function initializePerformanceMonitoring(helpPage) {
    // Track tab interactions for analytics
    document.addEventListener('utilikitTabChange', function(event) {
      // This could be connected to analytics systems
      if (window.gtag) {
        gtag('event', 'tab_change', {
          'tab_id': event.detail.tabId,
          'timestamp': event.detail.timestamp
        });
      }
    });

    // Monitor scroll depth for engagement tracking
    let maxScrollDepth = 0;
    const trackScrollDepth = throttle(function() {
      const scrollPercent = Math.round(
        (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100
      );

      if (scrollPercent > maxScrollDepth) {
        maxScrollDepth = scrollPercent;

        // Track milestones
        if ([25, 50, 75, 100].includes(maxScrollDepth)) {
          if (window.gtag) {
            gtag('event', 'scroll_depth', {
              'percent': maxScrollDepth,
              'page': 'utilikit_help'
            });
          }
        }
      }
    }, 1000);

    window.addEventListener('scroll', trackScrollDepth);

    // Performance timing
    if (window.performance && window.performance.timing) {
      window.addEventListener('load', function() {
        setTimeout(function() {
          const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
          console.log('UtiliKit Help page load time:', loadTime + 'ms');
        }, 0);
      });
    }
  }

  /**
   * Throttles function execution for performance optimization.
   *
   * Limits the rate at which a function can fire to improve
   * performance during high-frequency events like scrolling.
   *
   * @param {Function} func
   *   The function to throttle.
   * @param {number} limit
   *   The minimum time between function executions in milliseconds.
   *
   * @returns {Function}
   *   The throttled function.
   */
  function throttle(func, limit) {
    let inThrottle;
    return function() {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }

  /**
   * Developer console utilities and easter eggs.
   *
   * Provides helpful console messages and utility functions for
   * developers exploring the help system implementation.
   */
  if (window.console && console.log) {
    const styles = [
      'color: #007bff',
      'font-size: 16px',
      'font-weight: bold'
    ].join(';');

    console.log('%cWelcome to UtiliKit Help!', styles);
    console.log('%cTip: Try typing utilikitHelp.getTips() in the console!', 'color: #28a745');

    // Add some console utilities for fun
    window.utilikitHelp = {
      /**
       * Returns helpful tips for using UtiliKit.
       *
       * @returns {string[]} Array of helpful tips.
       */
      getTips: function() {
        return [
          'Use Developer Mode to see what UtiliKit is doing behind the scenes',
          'The Playground is perfect for testing ideas before implementing',
          'Always test responsive designs on real devices',
          'Static mode is great for production performance',
          'Check the browser console for helpful debug information'
        ];
      },

      /**
       * Returns version information.
       *
       * @returns {string} Version string with humorous message.
       */
      getVersion: function() {
        return 'UtiliKit Help v1.0 - Making documentation fun since 2025!';
      },

      /**
       * Triggers a fun visual effect for developers.
       *
       * @returns {string} Success message for the easter egg.
       */
      surprise: function() {
        document.body.style.animation = 'rainbow 2s ease-in-out';
        const style = document.createElement('style');
        style.textContent = `
          @keyframes rainbow {
            0% { filter: hue-rotate(0deg); }
            50% { filter: hue-rotate(180deg); }
            100% { filter: hue-rotate(360deg); }
          }
        `;
        document.head.appendChild(style);

        setTimeout(function() {
          document.body.style.animation = '';
          document.head.removeChild(style);
        }, 2000);

        return 'Surprise! You found the dev mode!';
      }
    };
  }

})(Drupal, once, drupalSettings);
