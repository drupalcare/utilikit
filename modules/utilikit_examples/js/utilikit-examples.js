/**
 * @file
 * UtiliKit Examples JavaScript behavior.
 *
 * Handles dynamic class application for UtiliKit examples in documentation,
 * playground, and test environments. Always forces inline mode regardless
 * of global settings to ensure examples display correctly.
 */

(function(Drupal, once, drupalSettings) {
  'use strict';

  /**
   * UtiliKit Examples behavior.
   *
   * Ensures UtiliKit examples always use inline mode for consistent
   * rendering across documentation, playground, and testing contexts.
   * This behavior overrides global rendering mode settings to guarantee
   * that examples work correctly regardless of site configuration.
   *
   * @namespace
   */
  Drupal.behaviors.utilikitExamples = {

    /**
     * Attaches UtiliKit inline processing to example elements.
     *
     * Scans for elements with 'utilikit' class and forces inline mode
     * processing to ensure examples render consistently. This is
     * particularly important in documentation and testing contexts
     * where static mode might interfere with dynamic examples.
     *
     * @param {HTMLElement} context
     *   The DOM element context to search within for UtiliKit elements.
     * @param {object} settings
     *   Drupal settings object containing configuration data.
     *
     * @returns {void}
     */
    attach: function(context, settings) {
      // Always use inline mode regardless of global settings
      const elements = once('utilikit-examples', '.utilikit', context);

      if (elements.length > 0) {
        // Force apply classes using inline engine to ensure examples
        // display correctly in documentation and testing environments
        if (typeof Drupal.utilikit !== 'undefined' &&
            typeof Drupal.utilikit.applyClasses === 'function') {
          Drupal.utilikit.applyClasses(elements);
        }
      }
    }
  };

})(Drupal, once, drupalSettings);
