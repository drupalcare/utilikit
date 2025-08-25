/**
 * @file
 * UtiliKit Admin Preview functionality for Drupal administration.
 *
 * Provides interactive preview capabilities in the admin interface,
 * including live rendering, mode switching, color picker integration,
 * clipboard operations, and comprehensive state management.
 *
 * Filename: utilikit.admin.preview.js
 * Website: https://www.flashwebcenter.com
 * Developer: Alaa Haddad https://www.alaahaddad.com.
 */

(function(Drupal, once, drupalSettings) {
  'use strict';

  /**
   * UtiliKit namespace initialization.
   *
   * Creates the global UtiliKit namespace if it doesn't exist to ensure
   * compatibility with other UtiliKit modules and features.
   *
   * @namespace
   */
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * UtiliKit Preview Drupal behavior.
   *
   * Initializes the admin preview interface with live rendering, mode
   * switching, color picker integration, copy-to-clipboard functionality,
   * and comprehensive reset capabilities. Always uses inline mode for
   * immediate visual feedback in admin contexts.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches preview functionality to admin interface elements.
   */
  Drupal.behaviors.utilikitPreview = {
    attach: function(context, settings) {

      // Always use inline mode regardless of global settings
      const elements = once('utilikit-playground', '.utilikit', context);
      if (elements.length > 0) {
        // Force apply classes using inline engine
        if (typeof Drupal.utilikit !== 'undefined' && typeof Drupal.utilikit.applyClasses === 'function') {
          Drupal.utilikit.applyClasses(elements);
        }
      }

      once('utilikitPlayground', '#utilikit-preview-form', context).forEach(() => {
        const form = document.getElementById('utilikit-preview-form');

        /**
         * Initialize color picker functionality.
         *
         * Sets up two-way binding between color picker input and hex value
         * field, with click-to-copy functionality for easy color usage.
         */
        const colorPicker = document.getElementById('utilikit-color-picker');
        const colorValue = document.getElementById('utilikit-color-value');

        if (colorPicker && colorValue) {
          colorPicker.addEventListener('input', () => {
            colorValue.value = colorPicker.value;
          });

          colorValue.addEventListener('click', () => {
            colorValue.select();
            try {
              document.execCommand('copy');
            } catch (err) {
              console.warn('Copy failed:', err);
            }
          });
        }

        /**
         * UI element references for preview interface.
         *
         * Centralizes access to all interactive elements in the preview
         * interface for consistent manipulation and state management.
         *
         * @type {Object.<string, Element>}
         */
        const ui = {
          mode: document.getElementById('utilikit-mode'),
          classInput: form.querySelector('#utilikit-class-input'),
          htmlInput: form.querySelector('#utilikit-html-input'),
          basicGroup: form.querySelector('#utilikit-basic-input-group'),
          advancedGroup: form.querySelector('#utilikit-advanced-input-group'),
          previewBox: document.getElementById('utilikit-preview-box'),
          outputCode: document.getElementById('utilikit-output-code'),
          renderedHtml: document.getElementById('utilikit-rendered-html'),
          copyBtn: document.getElementById('utilikit-copy-btn'),
          resetBtn: document.getElementById('utilikit-reset-btn'),
          hiddenCopy: document.getElementById('utilikit-hidden-copy'),
        };

        /**
         * Handle mode switching between basic and advanced editing.
         *
         * Toggles visibility of input groups based on selected mode,
         * allowing users to switch between simple class input and
         * full HTML editing capabilities.
         */
        ui.mode.addEventListener('change', () => {
          const isAdvanced = ui.mode.value === 'advanced';
          ui.basicGroup.style.display = isAdvanced ? 'none' : 'block';
          ui.advancedGroup.style.display = isAdvanced ? 'block' : 'none';
        });

        /**
         * Handle form submission and live preview rendering.
         *
         * Processes form input based on current mode (basic or advanced),
         * generates appropriate HTML, renders live preview, and updates
         * all output displays with proper UtiliKit engine integration.
         *
         * @param {Event} e - The form submission event.
         */
        form.addEventListener('submit', (e) => {
          e.preventDefault();

          let html = '';
          const isAdvanced = ui.mode.value === 'advanced';

          if (isAdvanced) {
            html = ui.htmlInput.value.trim();
          } else {
            const classString = ui.classInput.value.trim();
            html = `<div class="utilikit ${classString}">This is your styled preview.</div>`;
          }

          // Output raw HTML (copied or referenced)
          ui.outputCode.textContent = html;

          // Render live preview visually
          ui.previewBox.innerHTML = html;

          // Attach Drupal behaviors so the UtiliKit engine runs on new content
          Drupal.attachBehaviors(ui.previewBox);

          // After engine applies styles, show the fully rendered HTML
          requestAnimationFrame(() => {
            const renderedElement = ui.previewBox.querySelector('[class*="uk-"], .utilikit') || ui.previewBox.firstElementChild;

            requestAnimationFrame(() => {
              if (renderedElement && ui.renderedHtml) {
                ui.renderedHtml.textContent = renderedElement.outerHTML;
              }
            });
          });
        });

        /**
         * Handle copy-to-clipboard functionality.
         *
         * Implements robust clipboard copying with fallback for older
         * browsers, provides visual feedback, and handles both modern
         * Clipboard API and legacy execCommand methods.
         */
        ui.copyBtn.addEventListener('click', () => {
          const textToCopy = ui.outputCode.textContent;

          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy)
              .then(() => showCopyFeedback(true))
              .catch(() => fallbackCopy(textToCopy));
          } else {
            fallbackCopy(textToCopy);
          }

          /**
           * Fallback copy method for browsers without Clipboard API.
           *
           * Uses hidden textarea and execCommand for older browser
           * compatibility when modern clipboard methods are unavailable.
           *
           * @param {string} text - The text content to copy.
           */
          function fallbackCopy(text) {
            ui.hiddenCopy.value = text;
            ui.hiddenCopy.select();
            const success = document.execCommand('copy');
            showCopyFeedback(success);
          }

          /**
           * Displays visual feedback for copy operations.
           *
           * Temporarily updates button text and state to indicate
           * success or failure of clipboard operations.
           *
           * @param {boolean} success - Whether the copy operation succeeded.
           */
          function showCopyFeedback(success) {
            ui.copyBtn.textContent = success ? '✅ Copied!' : '⚠️ Copy failed';
            ui.copyBtn.disabled = true;
            setTimeout(() => {
              ui.copyBtn.textContent = '📋 Copy HTML';
              ui.copyBtn.disabled = false;
            }, 1500);
          }
        });

        /**
         * Handle comprehensive preview interface reset.
         *
         * Resets all interface elements to their default state, including
         * form inputs, displays, interactive components, and external
         * enhancements from other modules.
         */
        ui.resetBtn.addEventListener('click', () => {
          // 1. Reset form inputs
          ui.classInput.value = '';
          ui.htmlInput.value = '';

          // 2. Reset mode to basic
          ui.mode.value = 'basic';
          ui.basicGroup.style.display = 'block';
          ui.advancedGroup.style.display = 'none';

          // 3. Clear all outputs
          ui.outputCode.textContent = '';
          ui.renderedHtml.textContent = '';
          ui.previewBox.innerHTML = '';

          // 4. Reset color picker and value
          const colorPicker = document.getElementById('utilikit-color-picker');
          const colorValue = document.getElementById('utilikit-color-value');
          if (colorPicker) colorPicker.value = '#007bff';
          if (colorValue) colorValue.value = '#007bff';

          // 5. Clear selected color swatches
          document.querySelectorAll('.color-swatch.selected').forEach(swatch => {
            swatch.classList.remove('selected');
          });

          // 6. Reset viewport to desktop
          const viewportBtns = document.querySelectorAll('.viewport-btn');
          const previewFrame = document.getElementById('utilikit-preview-box');
          const sizeIndicator = document.querySelector('.viewport-size-indicator');

          viewportBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.viewport === 'desktop');
          });

          if (previewFrame) {
            previewFrame.className = 'preview-frame desktop utilikit-preview-output';
          }

          if (sizeIndicator) {
            sizeIndicator.textContent = '1200px';
          }

          // 7. Hide and reset visual builder
          const classBuilder = document.getElementById('class-builder');
          const builderBtn = document.querySelector('[data-action="builder"]');
          if (classBuilder) classBuilder.style.display = 'none';
          if (builderBtn) builderBtn.classList.remove('active');

          // Clear all active builder chips
          document.querySelectorAll('.builder-chip.active').forEach(chip => {
            chip.classList.remove('active');
          });

          // 8. Hide responsive helper
          const responsiveHelper = document.getElementById('responsive-helper');
          const responsiveBtn = document.querySelector('[data-action="responsive"]');
          if (responsiveHelper) responsiveHelper.style.display = 'none';
          if (responsiveBtn) responsiveBtn.classList.remove('active');

          // Clear responsive helper inputs
          const responsiveInput = document.getElementById('responsive-input');
          const responsiveOutput = document.getElementById('responsive-output');
          if (responsiveInput) responsiveInput.value = '';
          if (responsiveOutput) responsiveOutput.innerHTML = '<p style="color: #6c757d; font-size: 13px;">Enter a valid UtiliKit class (e.g., uk-pd--20)</p>';

          // 9. Hide autocomplete dropdown
          const autocomplete = document.getElementById('class-autocomplete');
          if (autocomplete) autocomplete.style.display = 'none';

          // 10. Reset style inspector
          const styleBreakdown = document.getElementById('style-breakdown');
          if (styleBreakdown) {
            styleBreakdown.innerHTML = '<p class="utilikit-description">Render content to see applied styles</p>';
          }

          // 11. Close any open overlays
          const tutorialOverlay = document.getElementById('tutorial-overlay');
          if (tutorialOverlay) tutorialOverlay.classList.remove('active');

          // 12. Reset button states
          document.querySelectorAll('.quick-action-btn.active').forEach(btn => {
            if (btn.dataset.action !== 'tutorial') {
              btn.classList.remove('active');
            }
          });

          // 13. Remove any success/error states
          document.querySelectorAll('.preset-btn.success-flash').forEach(btn => {
            btn.classList.remove('success-flash');
          });

          // 14. Reset copy button if needed
          const copyBtn = document.getElementById('utilikit-copy-btn');
          if (copyBtn && copyBtn.disabled) {
            copyBtn.textContent = '📋 Copy HTML';
            copyBtn.disabled = false;
          }

          // 15. Visual feedback for reset
          ui.resetBtn.textContent = '✅ Reset!';
          ui.resetBtn.style.background = '#28a745';
          ui.resetBtn.style.color = 'white';

          setTimeout(() => {
            ui.resetBtn.textContent = 'Reset';
            ui.resetBtn.style.background = '';
            ui.resetBtn.style.color = '';
          }, 1000);
        });

        /**
         * Additional reset helper for enhanced features.
         *
         * Provides deep reset functionality for advanced components
         * and data structures that may be added by enhancement modules.
         * This function should be coordinated with other UtiliKit modules.
         *
         * @private
         */
        function resetAllEnhancements() {
          // Reset any Set() or Map() data structures
          const builder = document.getElementById('class-builder');
          if (builder && typeof selectedClasses !== 'undefined') {
            selectedClasses.clear();
          }

          // Reset any custom data attributes
          document.querySelectorAll('[data-utilikitProps]').forEach(el => {
            delete el.dataset.utilikitProps;
          });

          // Clear any inline styles added by UtiliKit
          document.querySelectorAll('.utilikit[style]').forEach(el => {
            el.removeAttribute('style');
          });

          // Reset any animation states
          document.querySelectorAll('[style*="transform"]').forEach(el => {
            el.style.transform = '';
          });
        }

        /**
         * Global reset function for external module integration.
         *
         * Exposes the reset functionality globally so other modules
         * or external scripts can trigger a complete playground reset.
         *
         * @global
         * @function utilikitResetPlayground
         */
        window.utilikitResetPlayground = function() {
          const resetBtn = document.getElementById('utilikit-reset-btn');
          if (resetBtn) {
            resetBtn.click();
          }
        };
      });
    }
  };
})(Drupal, once, drupalSettings);
