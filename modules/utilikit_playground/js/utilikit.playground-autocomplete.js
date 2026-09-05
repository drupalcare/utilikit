/**
 * @file
 * Dynamic autocomplete functionality for UtiliKit Playground.
 *
 * Provides intelligent autocomplete suggestions for UtiliKit utility classes
 * based on rule types, CSS properties, and common values. Includes keyboard
 * navigation, visual selection, and real-time filtering.
 */

(function(Drupal, once) {
  'use strict';

  /**
   * UtiliKit rules definition matching PHP rules.
   *
   * This object defines all available UtiliKit utility prefixes and their
   * corresponding CSS properties, value types, and behavioral flags.
   * Should ideally be passed from Drupal settings for consistency.
   *
   * @type {Object.<string, Object>}
   *
   * @property {string} css - The CSS property name this rule affects
   * @property {string} group - The functional group for organization
   * @property {boolean} [sides] - Whether rule supports directional variants
   * @property {boolean} [isNumericFlexible] - Accepts numeric + unit values
   * @property {boolean} [allowAuto] - Accepts 'auto' as a value
   * @property {boolean} [isKeyword] - Uses predefined keyword values
   * @property {boolean} [isColor] - Accepts color hex values
   * @property {boolean} [isInteger] - Accepts integer values only
   * @property {boolean} [isOpacity] - Special handling for opacity values
   * @property {boolean} [isDecimalFixed] - Accepts decimal values
   * @property {string} [isTransform] - Transform type ('rotate', 'scale')
   * @property {boolean} [isGridTrackList] - CSS Grid track definitions
   * @property {boolean} [isRange] - Range values like grid spans or ratios
   */
  /**
   * The UtiliKit rules, read from the one place they are declared.
   *
   * This file used to carry its own copy of all fifty rules — the second of
   * three, and its own comment said what it should have been instead:
   * "Should ideally be passed from Drupal settings for consistency."
   *
   * It is a function rather than a const because it is read at call time. The
   * playground library depends on utilikit/utilikit.engine, so the registry is
   * populated before anything here runs, but a getter keeps that an ordering
   * detail rather than a load-order assumption baked into this file.
   *
   * @return {Object.<string, Object>}
   *   Prefix keyed rule definitions, or an empty object if none were
   *   delivered — utilikit.rules.js reports that case to the console.
   */
  function utilikitRules() {
    return (Drupal.utilikit && Drupal.utilikit.rules) || {};
  }

  /**
   * Keyword mappings for CSS properties that accept specific keywords.
   *
   * Maps CSS property names to arrays of valid keyword values that can
   * be used in autocomplete suggestions.
   *
   * @type {Object.<string, string[]>}
   */
  /**
   * Keyword suggestions, from the one declaration.
   *
   * This was a hand-kept copy and it had drifted both ways: it suggested
   * `uk-cu--progress`, which the engine rejected, and omitted four of the
   * fourteen `display` values. Suggesting a value the engine refuses is worse
   * than suggesting nothing.
   *
   * @return {Object.<string, string[]>}
   *   CSS property => accepted keywords.
   */
  function keywordMapFor() {
    return (drupalSettings && drupalSettings.utilikit
      && drupalSettings.utilikit.keywords) || {};
  }

  /**
   * Common color values for autocomplete suggestions.
   *
   * Provides a curated list of commonly used colors with friendly names
   * and hex values (without # prefix) for UtiliKit color utilities.
   *
   * @type {Array<{name: string, value: string}>}
   */
  const commonColors = [
    { name: 'Black', value: '000000' },
    { name: 'White', value: 'ffffff' },
    { name: 'Gray 100', value: 'f8f9fa' },
    { name: 'Gray 200', value: 'e9ecef' },
    { name: 'Gray 300', value: 'dee2e6' },
    { name: 'Gray 400', value: 'ced4da' },
    { name: 'Gray 500', value: 'adb5bd' },
    { name: 'Gray 600', value: '6c757d' },
    { name: 'Gray 700', value: '495057' },
    { name: 'Gray 800', value: '343a40' },
    { name: 'Gray 900', value: '212529' },
    { name: 'Blue', value: '007bff' },
    { name: 'Green', value: '28a745' },
    { name: 'Red', value: 'dc3545' },
    { name: 'Yellow', value: 'ffc107' },
    { name: 'Cyan', value: '17a2b8' },
    { name: 'Purple', value: '6f42c1' },
    { name: 'Orange', value: 'fd7e14' },
  ];

  /**
   * Generates autocomplete suggestions for a specific prefix and rule.
   *
   * Creates appropriate suggestions based on the rule type, including
   * numeric values, keywords, colors, directional variants, and more.
   *
   * @param {string} prefix
   *   The UtiliKit prefix (e.g., 'pd', 'mg', 'bg').
   * @param {Object} rule
   *   The rule configuration object from utilikitRules().
   *
   * @returns {string[]}
   *   Array of complete UtiliKit class suggestions.
   */
  function generateSuggestionsForPrefix(prefix, rule) {
    const suggestions = [];

    if (rule.isNumericFlexible) {
      // Common spacing values
      const values = [0, 2, 4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72, 80, 96, 128];
      values.forEach(v => suggestions.push(`uk-${prefix}--${v}`));

      // Percentage values
      ['10pr', '20pr', '25pr', '30pr', '33pr', '40pr', '50pr', '60pr', '66pr', '70pr', '75pr', '80pr', '90pr', '100pr'].forEach(v => {
        suggestions.push(`uk-${prefix}--${v}`);
      });

      // Viewport units for sizing properties
      if (['wd', 'ht', 'xw', 'xh', 'nw', 'nh', 'fs'].includes(prefix)) {
        ['25vh', '50vh', '75vh', '100vh', '25vw', '50vw', '75vw', '100vw'].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      }

      // Rem/em values
      ['0d25rem', '0d5rem', '0d75rem', '1rem', '1d25rem', '1d5rem', '2rem', '2d5rem', '3rem', '4rem', '5rem'].forEach(v => {
        suggestions.push(`uk-${prefix}--${v}`);
      });

      // If it supports sides, add directional examples
      if (rule.sides) {
        ['t', 'r', 'b', 'l'].forEach(dir => {
          [0, 8, 16, 24, 32].forEach(v => {
            suggestions.push(`uk-${prefix}--${dir}-${v}`);
          });
        });
        // Two-value shorthand
        suggestions.push(`uk-${prefix}--16-32`, `uk-${prefix}--20-40`, `uk-${prefix}--24-48`);
        // Four-value shorthand
        suggestions.push(`uk-${prefix}--16-24-32-40`);
      }

      // If it allows auto
      if (rule.allowAuto) {
        suggestions.push(`uk-${prefix}--auto`);
        if (rule.sides) {
          ['t', 'r', 'b', 'l'].forEach(dir => {
            suggestions.push(`uk-${prefix}--${dir}-auto`);
          });
          suggestions.push(`uk-${prefix}--0-auto`);
        }
      }
    }

    else if (rule.isKeyword) {
      const keywords = keywordMapFor()[rule.css] || [];
      keywords.forEach(keyword => {
        suggestions.push(`uk-${prefix}--${keyword}`);
      });

      // Side-specific keyword suggestions (e.g., uk-bs--b-dashed).
      if (rule.sides) {
        ['t', 'r', 'b', 'l'].forEach(dir => {
          keywords.forEach(keyword => {
            suggestions.push(`uk-${prefix}--${dir}-${keyword}`);
          });
        });
      }
    }

    else if (rule.isColor) {
      commonColors.forEach(color => {
        suggestions.push(`uk-${prefix}--${color.value}`);
        // Add common opacity variants
        ['25', '50', '75', '90'].forEach(opacity => {
          suggestions.push(`uk-${prefix}--${color.value}-${opacity}`);
        });
      });
      // Pseudo-state variants for bg and tc only (static/head mode).
      if (prefix === 'bg' || prefix === 'tc') {
        commonColors.slice(0, 6).forEach(color => {
          ['h', 'f', 'a'].forEach(state => {
            suggestions.push(`uk-${prefix}--${color.value}-${state}`);
          });
          // Alpha + pseudo-state combo
          suggestions.push(`uk-${prefix}--${color.value}-50-h`);
        });
      }
    }

    else if (rule.isInteger) {
      if (rule.isOpacity) {
        [0, 5, 10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90, 95, 100].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      } else if (prefix === 'fw') {
        [100, 200, 300, 400, 500, 600, 700, 800, 900].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      } else if (prefix === 'zi') {
        [-1, 0, 1, 10, 20, 30, 40, 50, 100, 999, 9999].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      } else if (prefix === 'or') {
        [-1, 0, 1, 2, 3, 4, 5].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      }
    }

    else if (rule.isDecimalFixed) {
      [0, '0d5', 1, '1d5', 2, '2d5', 3].forEach(v => {
        suggestions.push(`uk-${prefix}--${v}`);
      });
    }

    else if (rule.isTransform) {
      if (rule.isTransform === 'rotate') {
        [-180, -90, -45, 0, 45, 90, 180, 270, 360].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      } else if (rule.isTransform === 'scale') {
        [0, 50, 75, 90, 95, 100, 105, 110, 125, 150, 200].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      }
    }

    else if (rule.isGridTrackList) {
      const examples = [
        '1fr',
        '2fr',
        '1fr-1fr',
        '1fr-2fr',
        '2fr-1fr',
        '1fr-1fr-1fr',
        'repeat-2-1fr',
        'repeat-3-1fr',
        'repeat-4-1fr',
        'repeat-5-1fr',
        'repeat-6-1fr',
        'repeat-12-1fr',
        'repeat-auto-fit-minmax-200px-1fr',
        'repeat-auto-fit-minmax-250px-1fr',
        'repeat-auto-fit-minmax-300px-1fr',
        'repeat-auto-fill-minmax-200px-1fr',
        'repeat-auto-fill-minmax-250px-1fr',
        '200px-1fr',
        '250px-1fr',
        '300px-1fr',
        '1fr-200px',
        '1fr-300px',
        'minmax-200px-1fr',
        'minmax-0-1fr',
      ];
      examples.forEach(v => suggestions.push(`uk-${prefix}--${v}`));
    }

    else if (rule.isRange) {
      if (prefix === 'ar') {
        ['1-1', '3-2', '4-3', '5-4', '16-9', '21-9', '2-1', '3-1'].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      } else {
        // Grid lines
        ['1-2', '1-3', '1-4', '1-5', '2-3', '2-4', '2-5', '3-4', '3-5', 'span-1', 'span-2', 'span-3', 'span-4'].forEach(v => {
          suggestions.push(`uk-${prefix}--${v}`);
        });
      }
    }

    return suggestions;
  }

  /**
   * Converts a UtiliKit class name to a human-readable property name.
   *
   * Extracts the prefix from a class name and converts the corresponding
   * CSS property to a readable format for display in autocomplete.
   *
   * @param {string} className
   *   The UtiliKit class name to analyze (e.g., 'uk-pd--20').
   *
   * @returns {string}
   *   Human-readable property name (e.g., 'padding').
   */
  function getPropertyName(className) {
    const match = className.match(/^uk-([a-z]+)--/);
    if (!match) return '';

    const prefix = match[1];
    const rule = utilikitRules()[prefix];

    if (!rule) return prefix;

    // Convert camelCase to readable format
    const cssProperty = rule.css || prefix;
    return cssProperty
      .replace(/([A-Z])/g, ' $1')
      .toLowerCase()
      .trim();
  }

  /**
   * Updates visual selection highlighting in autocomplete dropdown.
   *
   * Manages the visual state of autocomplete items, highlighting the
   * currently selected item and ensuring it's visible in the scroll area.
   *
   * @param {NodeList} items
   *   The autocomplete item elements to update.
   * @param {number} selectedIndex
   *   The index of the currently selected item (-1 for no selection).
   */
  function updateAutocompleteSelection(items, selectedIndex) {
    items.forEach((item, index) => {
      item.classList.toggle('selected', index === selectedIndex);
      if (index === selectedIndex) {
        // Ensure selected item is visible
        item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    });
  }

  /**
   * Initializes autocomplete functionality for the class input field.
   *
   * Sets up input monitoring, suggestion generation, keyboard navigation,
   * and selection handling for UtiliKit class autocomplete.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing autocomplete controls.
   */
  function initAutocomplete(wrapper) {
    const classInput = document.getElementById('utilikit-class-input');
    const autocomplete = document.getElementById('class-autocomplete');

    if (!classInput || !autocomplete) return;

    let selectedIndex = -1;
    let currentSuggestions = [];

    // Input event handler
    classInput.addEventListener('input', function(e) {
      const value = e.target.value;
      const lastClass = value.split(' ').pop();

      // Only show suggestions if typing a utility class
      if (lastClass.startsWith('uk-') && lastClass.length >= 3) {
        let allSuggestions = [];

        // Extract the prefix being typed
        const match = lastClass.match(/^uk-([a-z]+)/);
        if (match) {
          const inputPrefix = match[1];

          // Find all matching rules
          Object.entries(utilikitRules()).forEach(([rulePrefix, rule]) => {
            if (rulePrefix.startsWith(inputPrefix)) {
              const suggestions = generateSuggestionsForPrefix(rulePrefix, rule);
              // Filter to only show suggestions that match what's typed
              const filtered = suggestions.filter(s => s.startsWith(lastClass));
              allSuggestions.push(...filtered);
            }
          });
        }

        // Remove duplicates and limit results
        currentSuggestions = [...new Set(allSuggestions)].slice(0, 20);

        if (currentSuggestions.length > 0) {
          selectedIndex = -1;

          // Build autocomplete using safe DOM methods (no innerHTML with dynamic data).
          autocomplete.textContent = '';
          const fragment = document.createDocumentFragment();
          currentSuggestions.forEach((sug, index) => {
            const propertyName = getPropertyName(sug);
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.dataset.index = index;
            item.dataset.value = sug;

            const classSpan = document.createElement('span');
            classSpan.className = 'autocomplete-class';
            classSpan.textContent = sug;

            const propSpan = document.createElement('span');
            propSpan.className = 'autocomplete-property';
            propSpan.textContent = propertyName;

            item.appendChild(classSpan);
            item.appendChild(propSpan);
            fragment.appendChild(item);
          });
          autocomplete.appendChild(fragment);

          autocomplete.style.display = 'block';

          // Attach click handlers to new items
          autocomplete.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('click', function() {
              const suggestion = this.dataset.value;
              const classes = classInput.value.split(' ');
              classes[classes.length - 1] = suggestion;
              classInput.value = classes.join(' ');
              autocomplete.style.display = 'none';
              selectedIndex = -1;
              classInput.focus();

              // Trigger form submit to update preview
              const form = document.getElementById('utilikit-preview-form');
              if (form) {
                form.dispatchEvent(new Event('submit'));
              }
            });
          });
        } else {
          autocomplete.style.display = 'none';
          currentSuggestions = [];
        }
      } else {
        autocomplete.style.display = 'none';
        currentSuggestions = [];
      }
    });

    // Keyboard navigation
    classInput.addEventListener('keydown', function(e) {
      const items = autocomplete.querySelectorAll('.autocomplete-item');
      const isVisible = autocomplete.style.display !== 'none';

      if (!isVisible || items.length === 0) return;

      switch(e.key) {
        case 'ArrowDown':
          e.preventDefault();
          selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
          updateAutocompleteSelection(items, selectedIndex);
          break;

        case 'ArrowUp':
          e.preventDefault();
          selectedIndex = Math.max(selectedIndex - 1, -1);
          updateAutocompleteSelection(items, selectedIndex);
          break;

        case 'Enter':
          if (selectedIndex >= 0 && items[selectedIndex]) {
            e.preventDefault();
            items[selectedIndex].click();
          }
          break;

        case 'Tab':
          if (selectedIndex >= 0 && items[selectedIndex]) {
            e.preventDefault();
            items[selectedIndex].click();
          } else if (currentSuggestions.length === 1) {
            e.preventDefault();
            items[0].click();
          }
          break;

        case 'Escape':
          e.preventDefault();
          autocomplete.style.display = 'none';
          selectedIndex = -1;
          break;
      }
    });

    // Hide autocomplete when clicking outside
    classInput.addEventListener('blur', function() {
      // Delay to allow click events on autocomplete items
      setTimeout(() => {
        autocomplete.style.display = 'none';
        selectedIndex = -1;
        currentSuggestions = [];
      }, 200);
    });

    // Focus management
    classInput.addEventListener('focus', function() {
      // Re-trigger suggestions if input has a partial class
      if (this.value) {
        this.dispatchEvent(new Event('input'));
      }
    });
  }

  /**
   * UtiliKit Playground Autocomplete Drupal behavior.
   *
   * Initializes intelligent autocomplete functionality for UtiliKit
   * utility class suggestions in the playground interface.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches autocomplete functionality to playground wrapper elements.
   */
  Drupal.behaviors.utilikitPlaygroundAutocomplete = {
    attach: function(context, settings) {
      once('utilikit-playground-autocomplete', '.utilikit-playground-wrapper', context).forEach(wrapper => {
        initAutocomplete(wrapper);
      });
    }
  };

})(Drupal, once);
