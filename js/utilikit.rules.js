/**
 * @file
 * UtiliKit CSS Rules Definition Registry.
 *
 * This file contains the complete mapping of UtiliKit utility prefixes to
 * their corresponding CSS properties and rule application behaviors. Each
 * rule defines how a utility class prefix should be processed, including
 * value types, special behaviors, and CSS property targets.
 *
 * The rules system supports multiple value types:
 * - Numeric flexible: Values with units (px, %, em, rem, vh, vw)
 * - Keywords: Predefined CSS keyword values
 * - Colors: Named colors, hex values, and alpha transparency
 * - Transforms: Rotation, scaling with decimal support
 * - Grid track lists: Complex grid template definitions
 * - Sides: Directional properties (top, right, bottom, left)
 * - Ranges: Grid line-based positioning
 * - Decimals: Fixed-precision decimal values
 * - Integers: Whole number values
 * - Opacity: 0-100 integer values converted to 0-1 decimals
 *
 * @see utilikit.rules.apply.js for rule application logic
 * @see utilikit.behavior.js for element processing workflow
 */
(function(Drupal, once, drupalSettings) {
  'use strict';
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * UtiliKit CSS Rules Registry.
   *
   * THIS FILE NO LONGER DECLARES THE RULES. They are declared once, in
   * UtilikitRules::getRules(), and published to JavaScript by
   * utilikit_js_settings_alter(). Every rule object still has the same shape:
   *
   * - css: The target CSS property name (camelCase)
   * - group: The functional group, for grouped listings
   * - sides: Supports directional values (t, r, b, l)
   * - isNumericFlexible: Accepts numeric values with optional units
   * - isKeyword: Accepts predefined keyword values
   * - isColor: Accepts color values with optional alpha
   * - isTransform: Transform function name for CSS transforms
   * - isGridTrackList: Complex grid template syntax support
   * - isRange: Grid line positioning (start-end format)
   * - isDecimalFixed: Fixed-precision decimal values
   * - isInteger: Whole number values only
   * - isOpacity: 0-100 integers converted to 0-1 decimals
   * - allowAuto: Permits the 'auto' keyword
   *
   * WHY, MEASURED 2026-09-05. The same fifty rules were written out three
   * times — here, in the playground's autocomplete, and in PHP — and had
   * already drifted. `wd`, `ht`, `xw` and `nw` carried allowAuto in JavaScript
   * and not in PHP, so `uk-wd--auto` rendered in inline mode and produced NO
   * CSS in static and head mode. Nobody could have seen that by reading either
   * file on its own, which is the whole argument for one source.
   *
   * IT FAILS LOUDLY. An empty registry would not throw — it would quietly make
   * every utility class on the page do nothing, which is the exact defect this
   * project keeps meeting. So an absent payload is reported once, by name, to
   * the console.
   *
   * @type {Object.<string, Object>}
   */
  var published = (drupalSettings && drupalSettings.utilikit && drupalSettings.utilikit.rules) || null;

  if (!published) {
    // Not a warning. With no rules, every uk- class silently stops working,
    // and a person would be left inspecting CSS that was never generated.
    console.error(
      'UtiliKit: no rule definitions were delivered to the page. ' +
      'drupalSettings.utilikit.rules is missing, so no utility class can be ' +
      'processed. This usually means the utilikit/utilikit.engine library was ' +
      'attached without the module settings that accompany it — see ' +
      'utilikit_js_settings_alter() in utilikit.module.'
    );
  }

  Drupal.utilikit.rules = published || {};

  /**
   * The keyword values a prefix accepts, from the one declaration.
   *
   * Consumers used to keep their own shortened copies — the reference page and
   * the playground autocomplete both did, and both had drifted. The
   * autocomplete suggested `uk-cu--progress` while the engine rejected it, and
   * offered ten of the fourteen `display` values.
   *
   * @param {string} prefix
   *   A two-to-four letter utility prefix, such as `dp`.
   *
   * @return {string[]}
   *   The accepted keywords, or an empty array when the prefix takes none.
   */
  Drupal.utilikit.keywordsFor = function (prefix) {
    const rule = Drupal.utilikit.rules[prefix];

    if (!rule || !rule.isKeyword) {
      return [];
    }

    const keywords = (drupalSettings && drupalSettings.utilikit
      && drupalSettings.utilikit.keywords) || {};

    return keywords[rule.css] || [];
  };

})(Drupal, once, drupalSettings);
