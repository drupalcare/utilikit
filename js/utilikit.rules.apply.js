/**
 * @file
 * UtiliKit CSS Rule Application Engine.
 *
 * This file contains the core rule application logic for the UtiliKit
 * utility-first CSS system. It provides specialized functions for applying
 * different types of CSS rules including spacing, transforms, colors, grids,
 * and responsive utilities to DOM elements dynamically.
 *
 * The rule application system supports:
 * - Directional spacing (padding, margin, border-radius, border-width)
 * - CSS shorthand notation for box model properties
 * - Keyword-based rules (display, position, etc.)
 * - Transform functions (rotate, scale, translate)
 * - Color values with alpha transparency
 * - CSS Grid track lists and fractional units
 * - Numeric values with flexible units
 * - Range-based grid positioning
 *
 * @see utilikit.rules.js for rule definitions
 * @see utilikit.behavior.js for element processing workflow
 */
(function(Drupal, once, drupalSettings) {
  'use strict';
  Drupal.utilikit = Drupal.utilikit || {};

  /**
   * Applies directional side-based CSS rules to elements.
   *
   * Handles utility classes that target specific sides of the box model,
   * such as padding-top, margin-left, border-radius-top-right, etc.
   * Supports all standard CSS units and the special 'auto' value where
   * applicable.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply styles to.
   * @param {object} rule
   *   The rule definition object containing CSS property and metadata.
   * @param {string} prefix
   *   The UtiliKit prefix identifier (e.g., "pd", "mg", "br", "bw").
   * @param {string} suffix
   *   The value suffix from the class (e.g., "t-24", "r-auto").
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS property names for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the rule was successfully applied, FALSE otherwise.
   */
  Drupal.utilikit.applySidesRule = function(el, rule, prefix, suffix, utiliKitProperties, className) {
    if (!rule.sides) return false;

    // Handle keyword sides (e.g., uk-bs--b-dashed → border-block-end-style: dashed).
    if (rule.isKeyword) {
      const keywordMatch = suffix.match(/^(t|r|b|l)-([\w-]+)$/);
      if (keywordMatch) {
        const side = keywordMatch[1];
        const keyword = keywordMatch[2];
        const sideMap = { t: 'BlockStart', r: 'InlineEnd', b: 'BlockEnd', l: 'InlineStart' };
        // borderStyle + BlockEnd → borderBlockEndStyle (side goes before property suffix).
        const baseProp = rule.css;
        const prop = baseProp.replace(/^(border)(.*)$/, `$1${sideMap[side]}$2`);
        el.style[prop] = keyword;
        utiliKitProperties.add(prop);
        return true;
      }
      // No side prefix — fall through to applyKeywordRule for all-sides handling.
      return false;
    }

    // Convert decimal notation: digit-d-digit becomes digit.digit (e.g., 1d5 -> 1.5).
    const normalizedSuffix = suffix.replace(/(\d)d(\d)/g, '$1.$2');

    const match = normalizedSuffix.match(/^(t|r|b|l)-(auto|\d+(?:\.\d+)?(?:px|pr|em|rem|vh|vw)?)$/);
    if (!match) return false;

    const side = match[1];
    let value = match[2];

    if (value !== 'auto') {
      // Parse the value with units
      const unitMatch = value.match(/^(\d+(?:\.\d+)?)(px|pr|em|rem|vh|vw)?$/);
      if (unitMatch) {
        const num = unitMatch[1];
        let unit = unitMatch[2] || 'px';
        if (unit === 'pr') unit = '%';
        value = num + unit;
      } else {
        value = value + 'px'; // Fallback
      }
    }

    if (prefix === 'br') {
      if (value === 'auto') return false; // border-radius does not support auto
      const radiusMap = {
        t: ['borderStartStartRadius', 'borderStartEndRadius'],
        r: ['borderStartEndRadius', 'borderEndEndRadius'],
        b: ['borderEndEndRadius', 'borderEndStartRadius'],
        l: ['borderStartStartRadius', 'borderEndStartRadius'],
      };
      radiusMap[side].forEach((prop) => {
        el.style[prop] = value;
        utiliKitProperties.add(prop);
      });
    } else if (prefix === 'bw') {
      const sideMap = { t: 'borderBlockStartWidth', r: 'borderInlineEndWidth', b: 'borderBlockEndWidth', l: 'borderInlineStartWidth' };
      const prop = sideMap[side];
      el.style[prop] = value;
      utiliKitProperties.add(prop);
    } else {
      const logicalSideMap = { t: 'BlockStart', r: 'InlineEnd', b: 'BlockEnd', l: 'InlineStart' };
      const prop = `${rule.css}${logicalSideMap[side]}`;
      el.style[prop] = value;
      utiliKitProperties.add(prop);
    }
    return true;
  };

  /**
   * Applies CSS shorthand notation for box model properties.
   *
   * Processes UtiliKit classes that use CSS shorthand syntax for padding,
   * margin, border-width, and border-radius. Supports 1-4 value notation
   * following standard CSS shorthand rules.
   *
   * Examples:
   * - uk-pd--12 → padding: 12px (all sides)
   * - uk-mg--12-24 → margin: 12px 24px (vertical horizontal)
   * - uk-pd--8-12-16-20 → padding: 8px 12px 16px 20px (TRBL)
   *
   * @param {HTMLElement} el
   *   The DOM element to apply styles to.
   * @param {object} rule
   *   The rule definition object from UtiliKit rules.
   * @param {string} prefix
   *   The UtiliKit prefix ("pd", "mg", "bw", "br").
   * @param {string} suffix
   *   The hyphen-separated values (e.g., "12-24", "8-16-24-32").
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the shorthand rule was applied, FALSE otherwise.
   */
  Drupal.utilikit.applySpacingShorthandRule = function(el, rule, prefix, suffix, utiliKitProperties, className) {
    if (!['pd', 'mg', 'bw', 'br'].includes(prefix)) return false;
    if (suffix.match(/^(t|r|b|l)-/)) return false;

    // Convert decimal notation: digit-d-digit becomes digit.digit (e.g., 1d5 -> 1.5).
    const normalizedSuffix = suffix.replace(/(\d)d(\d)/g, '$1.$2');

    // Split by dashes to handle each value individually
    const parts = normalizedSuffix.split('-');
    const values = [];

    // Parse each part
    for (let part of parts) {
      if (part === 'auto') {
        if (rule.allowAuto && ['mg'].includes(prefix)) {
          values.push('auto');
        } else {
          return false;
        }
      } else {
        const numMatch = part.match(/^(\d+(?:\.\d+)?)(px|pr|em|rem)?$/);
        if (!numMatch) return false;

        let unit = 'px';
        if (numMatch[2] === 'pr') unit = '%';
        else if (numMatch[2]) unit = numMatch[2];

        values.push(numMatch[1] + unit);
      }
    }
    // Validate we got 1-4 values
    if (values.length === 0 || values.length > 4) return false;

    // Continue with the rest of the function...
    // Apply CSS shorthand logic using logical properties.
    // blockStart = top, inlineEnd = right, blockEnd = bottom,
    // inlineStart = left (in LTR writing mode).
    let blockStart, inlineEnd, blockEnd, inlineStart;

    switch (values.length) {
      case 1:
        // uk-pd--20 → padding: 20px (all sides)
        blockStart = inlineEnd = blockEnd = inlineStart = values[0];
        break;
      case 2:
        // uk-pd--12-24 → padding: 12px 24px (block inline)
        blockStart = blockEnd = values[0];
        inlineEnd = inlineStart = values[1];
        break;
      case 3:
        // uk-pd--12-24-36 → padding: 12px 24px 36px (block-start inline block-end)
        blockStart = values[0];
        inlineEnd = inlineStart = values[1];
        blockEnd = values[2];
        break;
      case 4:
        // uk-pd--12-24-36-48 → (block-start inline-end block-end inline-start)
        blockStart = values[0];
        inlineEnd = values[1];
        blockEnd = values[2];
        inlineStart = values[3];
        break;
      default:
        return false;
    }

    // Apply to element based on prefix using logical properties.
    if (prefix === 'pd') {
      el.style.paddingBlockStart = blockStart;
      el.style.paddingInlineEnd = inlineEnd;
      el.style.paddingBlockEnd = blockEnd;
      el.style.paddingInlineStart = inlineStart;
      utiliKitProperties.add('paddingBlockStart');
      utiliKitProperties.add('paddingInlineEnd');
      utiliKitProperties.add('paddingBlockEnd');
      utiliKitProperties.add('paddingInlineStart');
    } else if (prefix === 'mg') {
      el.style.marginBlockStart = blockStart;
      el.style.marginInlineEnd = inlineEnd;
      el.style.marginBlockEnd = blockEnd;
      el.style.marginInlineStart = inlineStart;
      utiliKitProperties.add('marginBlockStart');
      utiliKitProperties.add('marginInlineEnd');
      utiliKitProperties.add('marginBlockEnd');
      utiliKitProperties.add('marginInlineStart');
    } else if (prefix === 'bw') {
      el.style.borderBlockStartWidth = blockStart;
      el.style.borderInlineEndWidth = inlineEnd;
      el.style.borderBlockEndWidth = blockEnd;
      el.style.borderInlineStartWidth = inlineStart;
      utiliKitProperties.add('borderBlockStartWidth');
      utiliKitProperties.add('borderInlineEndWidth');
      utiliKitProperties.add('borderBlockEndWidth');
      utiliKitProperties.add('borderInlineStartWidth');
    } else if (prefix === 'br') {
      el.style.borderStartStartRadius = blockStart;
      el.style.borderStartEndRadius = inlineEnd;
      el.style.borderEndEndRadius = blockEnd;
      el.style.borderEndStartRadius = inlineStart;
      utiliKitProperties.add('borderStartStartRadius');
      utiliKitProperties.add('borderStartEndRadius');
      utiliKitProperties.add('borderEndEndRadius');
      utiliKitProperties.add('borderEndStartRadius');
    }

    return true;
  };

  /**
   * Applies keyword-based CSS property values.
   *
   * Handles UtiliKit classes that set CSS properties to predefined keyword
   * values such as display: flex, position: absolute, text-align: center, etc.
   * Validates against a whitelist of allowed keyword values.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the style to.
   * @param {object} rule
   *   The rule definition containing the CSS property name.
   * @param {string} suffix
   *   The keyword value to apply (e.g., "flex", "absolute", "center").
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the keyword rule was applied, FALSE otherwise.
   */
  /**
   * Allowed keyword values per CSS property.
   *
   * Restricts keyword rules to valid CSS values only, preventing
   * arbitrary strings from being applied as CSS property values.
   */
  /**
   * The keyword values each CSS property accepts, from the one declaration.
   *
   * This list used to be written out here. It was the only copy — PHP had
   * none, and its generator emitted whatever word it was handed, so
   * `uk-dp--banana` became `display: banana;` in static and head mode while
   * this file rejected the identical class in inline mode. It now lives in
   * UtilikitRules::getAllowedKeywords() and arrives with the rules.
   *
   * @type {Object.<string, string[]>}
   */
  const allowedKeywords = (drupalSettings && drupalSettings.utilikit
    && drupalSettings.utilikit.keywords) || {};

  Drupal.utilikit.applyKeywordRule = function(el, rule, suffix, utiliKitProperties, className) {
    if (!rule.isKeyword) return false;
    const match = suffix.match(/^([\w-]+)$/);
    if (!match) return false;

    const keyword = match[1];
    const cssProp = rule.css;

    // Validate against allowed keywords for this CSS property.
    if (allowedKeywords[cssProp]) {
      // Map shorthand values (e.g., 'between' -> 'space-between').
      let value = keyword;
      if (cssProp === 'justifyContent' || cssProp === 'alignContent') {
        const shorthandMap = {
          'between': 'space-between',
          'around': 'space-around',
          'evenly': 'space-evenly',
        };
        value = shorthandMap[keyword] || keyword;
      }
      if (!allowedKeywords[cssProp].includes(value)) {
        return false;
      }
      el.style[cssProp] = value;
    }
    else {
      // No allowlist defined for this property — reject unknown properties.
      return false;
    }

    utiliKitProperties.add(cssProp);
    return true;
  };

  /**
   * Applies CSS transform functions to elements.
   *
   * Processes UtiliKit transform utilities such as rotate, scale, translate.
   * Supports decimal notation using 'd' character (e.g., 45d5 = 45.5).
   * Integrates with the existing transform parsing system to handle multiple
   * transforms on the same element.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the transform to.
   * @param {object} rule
   *   The rule definition containing transform metadata.
   * @param {string} suffix
   *   The numeric value for the transform (e.g., "90", "45d5").
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the transform was applied, FALSE otherwise.
   */
  Drupal.utilikit.applyTransformRule = function(el, rule, suffix, utiliKitProperties, className) {
    if (!rule.isTransform) return false;

    // Updated regex to support decimals with 'd' notation
    // Matches: 90, 45d5, 150, 120d75, etc.
    const match = suffix.match(/^(\d+(?:d\d+)?)$/);
    if (!match) return false;

    // Convert 'd' notation to decimal point
    const valueStr = match[1].replace('d', '.');
    const value = parseFloat(valueStr);

    // Validate the numeric value
    if (isNaN(value)) return false;

    // Apply the transform using the existing helper
    Drupal.utilikit.parseTransform(el, rule.isTransform, value);
    utiliKitProperties.add('transform');
    return true;
  };

  /**
   * Applies CSS Grid gap property with flexible syntax.
   *
   * Processes UtiliKit gap utilities that set row-gap and column-gap
   * properties. Supports single values (applied to both axes) and dual
   * values (row-gap column-gap). Handles multiple CSS units including
   * viewport units and percentages.
   *
   * Examples:
   * - uk-gp--12 → gap: 12px 12px
   * - uk-gp--12-24 → gap: 12px 24px
   * - uk-gp--1rem-2rem → gap: 1rem 2rem
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the gap rule to.
   * @param {object} rule
   *   The rule definition object for gap property.
   * @param {string} prefix
   *   The UtiliKit prefix (should be 'gp' for gap).
   * @param {string} suffix
   *   The gap values from the class name.
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the gap was applied successfully, FALSE otherwise.
   */
Drupal.utilikit.applyGapRule = function(el, rule, prefix, suffix, utiliKitProperties, className) {
    if (prefix !== 'gp') return false;

    // Convert decimal notation: digit-d-digit becomes digit.digit (e.g., 1d5 -> 1.5).
    const normalizedSuffix = suffix.replace(/(\d)d(\d)/g, '$1.$2');

    const match = normalizedSuffix.match(/^(\d+(?:\.\d+)?(?:px|pr|em|rem|vh|vw)?)(?:-(\d+(?:\.\d+)?(?:px|pr|em|rem|vh|vw)?))?$/);
    if (!match) return false;

    // Process first value
    let rowGap = match[1];
    const rowMatch = rowGap.match(/^(\d+(?:\.\d+)?)(px|pr|em|rem|vh|vw)?$/);
    if (rowMatch) {
      const num = rowMatch[1];
      let unit = rowMatch[2] || 'px';
      if (unit === 'pr') unit = '%';
      rowGap = num + unit;
    }

    // Process second value if exists
    let colGap = rowGap; // Default to same as row
    if (match[2]) {
      const colMatch = match[2].match(/^(\d+(?:\.\d+)?)(px|pr|em|rem|vh|vw)?$/);
      if (colMatch) {
        const num = colMatch[1];
        let unit = colMatch[2] || 'px';
        if (unit === 'pr') unit = '%';
        colGap = num + unit;
      }
    }

    el.style.gap = `${rowGap} ${colGap}`;
    utiliKitProperties.add('gap');
    return true;
  };

  /**
   * Applies numeric-based utility rules with flexible unit support.
   *
   * Handles various numeric rule types including opacity conversion,
   * decimal values, integers, and flexible numeric values with units.
   * Supports special handling for line-height (unitless multipliers)
   * and viewport unit restrictions for appropriate properties.
   *
   * Rule types supported:
   * - Opacity: 0-100 integer → 0-1 decimal
   * - Decimal fixed: precise decimal values
   * - Integer: whole number values
   * - Numeric flexible: values with optional units
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the style to.
   * @param {object} rule
   *   The rule definition containing property and type metadata.
   * @param {string} prefix
   *   The UtiliKit prefix for the current property.
   * @param {string} suffix
   *   The value portion of the utility class.
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the style was applied, FALSE if invalid format.
   */
  Drupal.utilikit.applyNumericRule = function(el, rule, prefix, suffix, utiliKitProperties, className) {
    // Handle opacity: integer 0–100 → decimal 0–1
    if (rule.isOpacity) {
      const match = suffix.match(/^(\d{1,3})$/);
      if (!match) return false;
      const value = parseInt(match[1], 10);
      if (value < 0 || value > 100) return false;
      el.style[rule.css] = (value / 100).toString();
      utiliKitProperties.add(rule.css);
      return true;
    }

    // Handle fixed decimal: integer or exactly two decimal places
    if (rule.isDecimalFixed) {
      // Updated regex to handle 'd' as decimal point
      const match = suffix.match(/^(?:\d+|\d+d\d{1,2}|0d\d{1,2})$/);
      if (!match) return false;

      // Convert 'd' to '.' for CSS value
      const value = match[0].replace('d', '.');
      el.style[rule.css] = value;
      utiliKitProperties.add(rule.css);
      return true;
    }

    if (rule.isInteger) {
      const match = suffix.match(/^(-?\d+)$/);
      if (!match) return false;
      el.style[rule.css] = match[1];
      utiliKitProperties.add(rule.css);
      return true;
    }

        // Handle numeric + unit: px, %, em, rem – and allow "auto" if flagged
    if (rule.isNumericFlexible) {
      if (rule.allowAuto && suffix === 'auto') {
        el.style[rule.css] = 'auto';
        utiliKitProperties.add(rule.css);
        return true;
      }

      // Accept integers or decimals written with 'd' (e.g., 12d5 → 12.5)
      const match = suffix.match(/^(\d+(?:d\d+)?)(px|pr|em|rem|vh|vw)?$/);
      if (!match) return false;

      // Keep the viewport-unit whitelist as in your original logic.
      const viewportAllowedRules = ['wd', 'ht', 'xw', 'xh', 'nw', 'nh', 'fs'];
      const unitRaw = match[2] || '';
      if ((unitRaw === 'vh' || unitRaw === 'vw') && !viewportAllowedRules.includes(prefix)) {
        return false;
      }

      // Convert 'd' to '.' and validate numeric.
      const valueStr = match[1].replace('d', '.');
      const numeric = Number(valueStr);
      if (Number.isNaN(numeric)) return false;

      // Map units: default px; 'pr' → '%'; otherwise passthrough.
      let unit = 'px';
      if (unitRaw === 'pr') unit = '%';
      else if (unitRaw) unit = unitRaw;

      // Special handling for unitless line-height (allowed 0–10).
      if (prefix === 'lh' && !unitRaw && numeric >= 0 && numeric <= 10) {
        el.style[rule.css] = valueStr; // unitless multiplier
        utiliKitProperties.add(rule.css);
        return true;
      }

      el.style[rule.css] = `${valueStr}${unit}`;
      utiliKitProperties.add(rule.css);
      return true;
    }

    return false;
  };

  /**
   * Applies color values with optional alpha transparency.
   *
   * Processes UtiliKit color utilities that support both solid colors
   * and colors with alpha transparency. Delegates to the color parsing
   * system for validation and format conversion.
   *
   * Supported formats:
   * - Named colors: red, blue, primary, secondary
   * - Hex colors: ff0000, 00ff00-50 (with alpha)
   * - RGB/HSL colors with alpha notation
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the color to.
   * @param {object} rule
   *   The rule definition containing the CSS property name.
   * @param {string} suffix
   *   The color value portion of the utility class.
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the color was applied, FALSE if invalid format.
   */
  Drupal.utilikit.applyColorRule = function(el, rule, suffix, utiliKitProperties, className) {
    if (!rule.isColor) return false;
    const color = Drupal.utilikit.parseColorWithAlpha(suffix);
    if (color === null) {
      return false;
    }
    el.style[rule.css] = color;
    utiliKitProperties.add(rule.css);
    return true;
  };

  /**
   * Applies CSS Grid fractional unit values.
   *
   * Processes UtiliKit classes that use CSS Grid's fractional unit (fr)
   * for flexible grid sizing. Validates the numeric portion and formats
   * the final CSS value with the 'fr' unit.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the fractional rule to.
   * @param {object} rule
   *   The rule definition containing the CSS property name.
   * @param {string} suffix
   *   The numeric value with 'fr' suffix.
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the fractional value was applied, FALSE otherwise.
   */
  Drupal.utilikit.applyFractionalRule = function(el, rule, suffix, utiliKitProperties, className) {
    if (!rule.isFractional) return false;
    const match = suffix.match(/^(\d+)fr$/);
    if (!match) return false;
    el.style[rule.css] = `${match[1]}fr`;
    utiliKitProperties.add(rule.css);
    return true;
  };

  /**
   * Applies CSS Grid range positioning values.
   *
   * Processes UtiliKit classes for CSS Grid line-based positioning
   * using the format "start-end" which becomes "start / end" in CSS.
   * Used for grid-column and grid-row span definitions.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply grid positioning to.
   * @param {object} rule
   *   The rule definition containing the CSS property name.
   * @param {string} suffix
   *   The range value in "start-end" format.
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   * @param {string} className
   *   The complete utility class name for error reporting.
   *
   * @returns {boolean}
   *   TRUE if the range was applied, FALSE otherwise.
   */
  Drupal.utilikit.applyRangeRule = function(el, rule, suffix, utiliKitProperties, className) {
    if (!rule.isRange) return false;
    const match = suffix.match(/^(\d+)-(\d+)$/);
    if (!match) return false;
    el.style[rule.css] = `${match[1]} / ${match[2]}`;
    utiliKitProperties.add(rule.css);
    return true;
  };

   /**
   * Applies CSS Grid track list definitions.
   *
   * Processes complex grid template utilities using functions like repeat(),
   * minmax(), and fr units. Delegates to the grid template parsing system
   * to handle the complex syntax and generate appropriate CSS values.
   *
   * Supported formats:
   * - uk-gc--repeat-2-1fr → grid-template-columns: repeat(2, 1fr)
   * - uk-gr--minmax-100px-1fr → grid-template-rows: minmax(100px, 1fr)
   *
   * @param {HTMLElement} el
   *   The DOM element to apply grid templates to.
   * @param {object} rule
   *   The rule definition for grid track lists.
   * @param {string} className
   *   The complete utility class name for parsing.
   * @param {Set} utiliKitProperties
   *   Set tracking modified CSS properties for cleanup.
   *
   * @returns {boolean}
   *   TRUE if the grid template was applied, FALSE otherwise.
   */
  Drupal.utilikit.applyGridTrackListRule = function(el, rule, className, utiliKitProperties) {
    if (!rule.isGridTrackList) return false;

    const css = Drupal.utilikit.parseGridTemplateClass(className);
    if (!css) return false;

    const match = css.match(/^(grid-template-(columns|rows)):\s*(.+);$/);
    if (!match) return false;

    const prop = match[1];
    const value = match[3];

    // Apply the style
    if (prop === 'grid-template-columns') {
      el.style.gridTemplateColumns = value;
      utiliKitProperties.add('gridTemplateColumns');
    } else if (prop === 'grid-template-rows') {
      el.style.gridTemplateRows = value;
      utiliKitProperties.add('gridTemplateRows');
    }

    return true;
  };

   /**
   * Main rule application dispatcher function.
   *
   * Coordinates the application of UtiliKit rules by attempting all
   * applicable rule handlers in sequence. Ensures the environment is
   * initialized before processing and provides centralized error logging
   * for unsupported rule formats.
   *
   * This is the primary entry point for converting UtiliKit utility
   * classes into applied CSS styles on DOM elements.
   *
   * @param {HTMLElement} el
   *   The DOM element to apply the rule to.
   * @param {object} rule
   *   The rule definition from the UtiliKit rules registry.
   * @param {string} prefix
   *   The UtiliKit prefix identifying the property type.
   * @param {string} suffix
   *   The value portion of the utility class to be processed.
   * @param {Set} utiliKitProperties
   *   Set tracking applied CSS properties for element cleanup.
   * @param {string} className
   *   The complete utility class name for debugging and logging.
   *
   * @returns {boolean}
   *   TRUE if any rule handler successfully applied the style, FALSE if
   *   no handler could process the given suffix format.
   */
  Drupal.utilikit.applyRule = function(el, rule, prefix, suffix, utiliKitProperties, className) {
    if (!Drupal.utilikit.state) {
      Drupal.utilikit.initEnvironment(document);
    }

    // Special handling for grid templates - they need the full className
    if (rule.isGridTrackList) {
      return Drupal.utilikit.applyGridTrackListRule(el, rule, className, utiliKitProperties);
    }

    const success =
      Drupal.utilikit.applySidesRule(el, rule, prefix, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applySpacingShorthandRule(el, rule, prefix, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyKeywordRule(el, rule, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyTransformRule(el, rule, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyGapRule(el, rule, prefix, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyNumericRule(el, rule, prefix, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyColorRule(el, rule, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyFractionalRule(el, rule, suffix, utiliKitProperties, className) ||
      Drupal.utilikit.applyRangeRule(el, rule, suffix, utiliKitProperties, className);

    if (!success) {
      Drupal.utilikit.logInvalid('Invalid value', suffix, rule, className);
    }
    return success;
  };

  //console.log('Current breakpoint:', Drupal.utilikit.getBreakpoint());
})(Drupal, once, drupalSettings);
