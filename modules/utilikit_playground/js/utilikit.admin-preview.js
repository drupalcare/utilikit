/**
 * @param Drupal
 * @param once
 * @param drupalSettings
 * @file
 * UtiliKit Admin Preview functionality for Drupal administration.
 *
 * Provides interactive preview capabilities in the admin interface,
 * including live rendering, tab-based mode switching, color picker
 * integration, clipboard operations, and comprehensive state management.
 *
 * Filename: utilikit.admin.preview.js
 * Website: https://www.flashwebcenter.com
 * Developer: Alaa Haddad https://www.alaahaddad.com.
 */

(function (Drupal, once, drupalSettings) {
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Sanitizes an HTML string by stripping dangerous elements and attributes.
   *
   * @param {string} rawHtml
   *   The raw HTML string to sanitize.
   * @return {Node[]}
   *   Array of safe DOM nodes.
   */
  function sanitizeHtml(rawHtml) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(rawHtml, 'text/html');

    // Remove all script, style, iframe, object, embed, link elements.
    const elList = doc.body.querySelectorAll(
      'script, style, iframe, object, embed, link, base, meta, form',
    );
    for (let i = 0; i < elList.length; i += 1) {
      const el = elList[i];
      el.remove();
    }

    // Strip event handler attributes from all elements.
    const elList2 = doc.body.querySelectorAll('*');
    for (let i = 0; i < elList2.length; i += 1) {
      const el = elList2[i];
      const attrList = Array.from(el.attributes);
      for (let j = 0; j < attrList.length; j += 1) {
        const attr = attrList[j];
        const name = attr.name.toLowerCase();
        if (
          name.startsWith('on') ||
          // The sanitiser that strips javascript: URLs — the literal below is
          // what it detects, not something this code builds.
          // eslint-disable-next-line no-script-url
          attr.value.trim().toLowerCase().startsWith('javascript:')
        ) {
          el.removeAttribute(attr.name);
        }
      }
      // Also strip href/src with javascript: protocol.
      const aList = ['href', 'src', 'action', 'formaction', 'xlink:href'];
      for (let k = 0; k < aList.length; k += 1) {
        const a = aList[k];
        const val = el.getAttribute(a);
        // eslint-disable-next-line no-script-url -- detection, not construction.
        if (val?.trim().toLowerCase().startsWith('javascript:')) {
          el.removeAttribute(a);
        }
      }
    }

    return Array.from(doc.body.childNodes).map((n) => n.cloneNode(true));
  }

  Drupal.behaviors.utilikitPreview = {
    attach(context) {
      // Always use inline mode regardless of global settings
      const elements = once('utilikit-playground', '.utilikit', context);
      if (elements.length > 0 && Drupal.utilikit?.applyClasses) {
        Drupal.utilikit.applyClasses(elements);
      }

      once(
        'utilikit-playground-form',
        '#utilikit-preview-form',
        context,
      ).forEach(() => {
        const form = document.getElementById('utilikit-preview-form');

        // Color picker
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

        // UI references
        const ui = {
          classInput: form.querySelector('#utilikit-class-input'),
          htmlInput: form.querySelector('#utilikit-html-input'),
          tabClasses: document.getElementById('tab-classes'),
          tabHtml: document.getElementById('tab-html'),
          previewBox: document.getElementById('utilikit-preview-box'),
          outputCode: document.getElementById('utilikit-output-code'),
          renderedHtml: document.getElementById('utilikit-rendered-html'),
          copyBtn: document.getElementById('utilikit-copy-btn'),
          resetBtn: document.getElementById('utilikit-reset-btn'),
          hiddenCopy: document.getElementById('utilikit-hidden-copy'),
        };

        // Tab switching
        const tabs = form.querySelectorAll('.playground-tab[data-tab]');
        const tabContents = form.querySelectorAll('.playground-tab-content');

        tabs.forEach((tab) => {
          tab.addEventListener('click', function () {
            const targetTab = this.dataset.tab;

            // Update tab active states
            tabs.forEach((t) => t.classList.remove('active'));
            this.classList.add('active');

            // Show/hide tab content
            tabContents.forEach((content) => {
              const isTarget = content.dataset.tabContent === targetTab;
              content.style.display = isTarget ? 'block' : 'none';
              content.classList.toggle('active', isTarget);
            });

            // Clear preview on tab switch
            ui.previewBox.innerHTML = '';
            ui.outputCode.textContent = '';
            ui.renderedHtml.textContent = '';

            const styleBreakdown = document.getElementById('style-breakdown');
            if (styleBreakdown) {
              styleBreakdown.innerHTML =
                '<p class="utilikit-description">Render content to see applied styles</p>';
            }
          });
        });

        /**
         * Check which tab is active.
         *
         * @return {boolean} True if HTML tab is active.
         */
        function isHtmlMode() {
          const htmlTab = form.querySelector(
            '.playground-tab[data-tab="html"]',
          );
          return htmlTab && htmlTab.classList.contains('active');
        }

        // Form submit
        form.addEventListener('submit', (e) => {
          e.preventDefault();

          ui.previewBox.textContent = '';

          if (isHtmlMode()) {
            const rawHtml = ui.htmlInput.value.trim();
            ui.outputCode.textContent = rawHtml;
            // Sanitize: parse into DOM, strip scripts and event handlers.
            const safeNodes = sanitizeHtml(rawHtml);
            safeNodes.forEach((node) => ui.previewBox.appendChild(node));
          } else {
            const classString = ui.classInput.value.trim();
            // Validate: only allow valid utility class characters.
            const safeClasses = classString
              .split(/\s+/)
              .filter((cls) => /^[a-zA-Z0-9_-]+$/.test(cls))
              .join(' ');
            const div = document.createElement('div');
            div.className = `utilikit ${safeClasses}`;
            div.textContent = 'This is your styled preview.';
            ui.outputCode.textContent = div.outerHTML;
            ui.previewBox.appendChild(div);
          }

          Drupal.attachBehaviors(ui.previewBox);

          requestAnimationFrame(() => {
            const renderedElement =
              ui.previewBox.querySelector('[class*="uk-"], .utilikit') ||
              ui.previewBox.firstElementChild;

            requestAnimationFrame(() => {
              if (renderedElement && ui.renderedHtml) {
                ui.renderedHtml.textContent = renderedElement.outerHTML;
              }
            });
          });
        });

        // Copy to clipboard
        ui.copyBtn.addEventListener('click', () => {
          const textToCopy = ui.outputCode.textContent;

          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard
              .writeText(textToCopy)
              .then(() => showCopyFeedback(true))
              .catch(() => fallbackCopy(textToCopy));
          } else {
            fallbackCopy(textToCopy);
          }

          function fallbackCopy(text) {
            ui.hiddenCopy.value = text;
            ui.hiddenCopy.select();
            const success = document.execCommand('copy');
            showCopyFeedback(success);
          }

          function showCopyFeedback(success) {
            ui.copyBtn.textContent = success ? 'Copied!' : 'Copy failed';
            ui.copyBtn.disabled = true;
            setTimeout(() => {
              ui.copyBtn.textContent = 'Copy HTML';
              ui.copyBtn.disabled = false;
            }, 1500);
          }
        });

        // Reset — clears everything on the page back to default state
        ui.resetBtn.addEventListener('click', () => {
          // Native form reset first (clears all native inputs/selects/textareas)
          form.reset();

          // Explicitly clear every input by ID (belt-and-suspenders)
          const classInput = document.getElementById('utilikit-class-input');
          const htmlInput = document.getElementById('utilikit-html-input');
          const responsiveInput = document.getElementById('responsive-input');
          const responsiveOutput = document.getElementById('responsive-output');
          const colorPickerEl = document.getElementById(
            'utilikit-color-picker',
          );
          const colorValueEl = document.getElementById('utilikit-color-value');
          const autocomplete = document.getElementById('class-autocomplete');
          const styleBreakdown = document.getElementById('style-breakdown');
          const tutorialOverlay = document.getElementById('tutorial-overlay');
          const previewFrame = document.getElementById('utilikit-preview-box');
          const sizeIndicator = document.querySelector(
            '.viewport-size-indicator',
          );
          const copyBtn = document.getElementById('utilikit-copy-btn');

          // 1. Clear all text inputs and textareas
          if (classInput) classInput.value = '';
          if (htmlInput) htmlInput.value = '';
          if (responsiveInput) responsiveInput.value = '';
          if (responsiveOutput) responsiveOutput.innerHTML = '';

          // 2. Reset color picker to default
          if (colorPickerEl) colorPickerEl.value = '#007bff';
          if (colorValueEl) colorValueEl.value = '#007bff';

          // 3. Switch to Classes tab
          tabs.forEach((t) =>
            t.classList.toggle('active', t.dataset.tab === 'classes'),
          );
          tabContents.forEach((c) => {
            const isClasses = c.dataset.tabContent === 'classes';
            c.style.display = isClasses ? 'block' : 'none';
            c.classList.toggle('active', isClasses);
          });

          // 4. Clear all output areas
          ui.outputCode.textContent = '';
          ui.renderedHtml.textContent = '';
          ui.previewBox.innerHTML = '';

          // 5. Reset style inspector
          if (styleBreakdown) {
            styleBreakdown.innerHTML =
              '<p class="utilikit-description">Render content to see applied styles</p>';
          }

          // 6. Reset viewport to desktop
          document.querySelectorAll('.viewport-btn').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.viewport === 'desktop');
          });
          if (previewFrame) {
            previewFrame.className =
              'preview-frame desktop utilikit-preview-output';
          }
          if (sizeIndicator) sizeIndicator.textContent = '1200px';

          // 7. Close all <details> panels
          document
            .querySelectorAll('.playground-details[open]')
            .forEach((d) => {
              d.removeAttribute('open');
            });

          // 8. Clear all interactive element states
          document
            .querySelectorAll('.builder-chip.active')
            .forEach((el) => el.classList.remove('active'));
          document
            .querySelectorAll('.preset-btn')
            .forEach((el) => el.classList.remove('active', 'success-flash'));
          document
            .querySelectorAll('.color-swatch.selected')
            .forEach((el) => el.classList.remove('selected'));

          // 9. Clear builder state
          if (window.utilikitPlaygroundState) {
            window.utilikitPlaygroundState.selectedClasses.clear();
            window.utilikitPlaygroundState.autocompleteIndex = -1;
          }

          // 10. Hide autocomplete dropdown
          if (autocomplete) autocomplete.style.display = 'none';

          // 11. Close tutorial overlay
          if (tutorialOverlay) tutorialOverlay.classList.remove('active');

          // 12. Reset copy button
          if (copyBtn && copyBtn.disabled) {
            copyBtn.textContent = 'Copy HTML';
            copyBtn.disabled = false;
          }

          // 13. Visual feedback
          ui.resetBtn.textContent = 'Done!';
          setTimeout(() => {
            ui.resetBtn.textContent = 'Reset All';
          }, 1000);
        });

        // Global reset function
        window.utilikitResetPlayground = function () {
          const resetBtn = document.getElementById('utilikit-reset-btn');
          if (resetBtn) resetBtn.click();
        };
      });
    },
  };
})(Drupal, once, drupalSettings);
