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
   * Maps utility class prefixes to their CSS properties and processing rules.
   * Each rule object defines how utility classes with that prefix should be
   * parsed and applied to DOM elements.
   *
   * Rule object properties:
   * - css: The target CSS property name (camelCase)
   * - sides: Boolean indicating support for directional values (t,r,b,l)
   * - isNumericFlexible: Accepts numeric values with optional units
   * - isKeyword: Accepts predefined keyword values
   * - isColor: Accepts color values with optional alpha
   * - isTransform: Transform function name for CSS transforms
   * - isGridTrackList: Complex grid template syntax support
   * - isRange: Grid line positioning (start-end format)
   * - isDecimalFixed: Fixed-precision decimal values
   * - isInteger: Whole number values only
   * - isOpacity: 0-100 integers converted to 0-1 decimals
   * - isFractional: CSS Grid fractional units (fr)
   * - allowAuto: Permits 'auto' keyword value
   *
   * @type {Object.<string, Object>}
   */
  Drupal.utilikit.rules = {
    /**
     * Box Model Properties (with directional support).
     *
     * These rules support both shorthand notation and directional targeting:
     * - uk-pd--16 → padding: 16px (all sides)
     * - uk-pd--t-16 → padding-top: 16px (specific side)
     * - uk-mg--12-24 → margin: 12px 24px (shorthand)
     */

    /** Padding utilities (uk-pd--*). */
    pd: { css: 'padding', sides: true, isNumericFlexible: true },

    /** Margin utilities with auto support (uk-mg--*). */
    mg: { css: 'margin', sides: true, isNumericFlexible: true, allowAuto: true },

    /** Border width utilities (uk-bw--*). */
    bw: { css: 'borderWidth', sides: true, isNumericFlexible: true },

    /** Border radius utilities (uk-br--*). */
    br: { css: 'borderRadius', sides: true, isNumericFlexible: true },

    /**
     * Border Style and Color Properties.
     */

    /** Border style keywords (uk-bs--solid, uk-bs--b-dashed). */
    bs: { css: 'borderStyle', isKeyword: true, sides: true },

    /** Border color with alpha support (uk-bc--red-50). */
    bc: { css: 'borderColor', isColor: true },

    /**
     * Dimensional Properties.
     *
     * Control element sizing with support for various units and auto values.
     */

    /** Inline size (width) with auto support (uk-wd--200, uk-wd--auto). */
    wd: { css: 'inlineSize', isNumericFlexible: true, allowAuto: true },

    /** Block size (height) with auto support (uk-ht--100vh, uk-ht--auto). */
    ht: { css: 'blockSize', isNumericFlexible: true, allowAuto: true },

    /** Max inline size (max-width) with auto support (uk-xw--1200). */
    xw: { css: 'maxInlineSize', isNumericFlexible: true, allowAuto: true },

    /** Min inline size (min-width) with auto support (uk-nw--300). */
    nw: { css: 'minInlineSize', isNumericFlexible: true, allowAuto: true },

    /** Max block size (max-height) (uk-xh--500). */
    xh: { css: 'maxBlockSize', isNumericFlexible: true },

    /** Min block size (min-height) (uk-nh--200). */
    nh: { css: 'minBlockSize', isNumericFlexible: true },

    /**
     * Positioning Properties.
     *
     * CSS positioning values for absolute, relative, and fixed elements.
     */

    /** Inset block start (top) position (uk-tp--10). */
    tp: { css: 'insetBlockStart', isNumericFlexible: true },

    /** Inset inline start (left) position (uk-lt--20). */
    lt: { css: 'insetInlineStart', isNumericFlexible: true },

    /** Inset inline end (right) position (uk-ri--30). */
    ri: { css: 'insetInlineEnd', isNumericFlexible: true },

    /** Inset block end (bottom) position (uk-bt--40). */
    bt: { css: 'insetBlockEnd', isNumericFlexible: true },

    /**
     * Typography Properties.
     *
     * Font sizing, spacing, and appearance controls.
     */

    /** Font size with viewport units allowed (uk-fs--16, uk-fs--2rem). */
    fs: { css: 'fontSize', isNumericFlexible: true },

    /** Line height with unitless support (uk-lh--1d5, uk-lh--24px). */
    lh: { css: 'lineHeight', isNumericFlexible: true },

    /** Font weight integers (uk-fw--400, uk-fw--700). */
    fw: { css: 'fontWeight', isInteger: true },

    /** Letter spacing (uk-ls--1px, uk-ls--0d1em). */
    ls: { css: 'letterSpacing', isNumericFlexible: true },

    /** Opacity 0-100 to 0-1 conversion (uk-op--50 → opacity: 0.5). */
    op: { css: 'opacity', isInteger: true, isOpacity: true },

    /** Z-index integer values (uk-zi--10, uk-zi--999). */
    zi: { css: 'zIndex', isInteger: true },

    /**
     * Layout Properties.
     *
     * Grid gap and aspect ratio controls.
     */

    /** CSS Grid and Flexbox gap (uk-gp--16, uk-gp--1rem-2rem). */
    gp: { css: 'gap', isNumericFlexible: true },

    /** Aspect ratio using range format (uk-ar--16-9 → 16/9). */
    ar: { css: 'aspectRatio', isRange: true },

    /**
     * Color Properties.
     *
     * Background and text colors with alpha transparency support.
     */

    /** Background color with alpha (uk-bg--primary, uk-bg--ff0000-80). */
    bg: { css: 'backgroundColor', isColor: true },

    /** Text color with alpha (uk-tc--white, uk-tc--333333-90). */
    tc: { css: 'color', isColor: true },

    /**
     * Flexbox Properties.
     *
     * CSS Flexbox layout controls with decimal precision support.
     */

    /** Flex grow with decimal precision (uk-fg--1, uk-fg--0d5). */
    fg: { css: 'flexGrow', isDecimalFixed: true },

    /** Flex shrink with decimal precision (uk-fk--1, uk-fk--0d75). */
    fk: { css: 'flexShrink', isDecimalFixed: true },

    /** Flex basis with units (uk-fb--200px, uk-fb--33pr). */
    fb: { css: 'flexBasis', isNumericFlexible: true },

    /** Flex order integer values (uk-or--1, uk-or---1). */
    or: { css: 'order', isInteger: true },

    /**
     * CSS Grid Properties.
     *
     * Advanced grid layout with track lists and positioning.
     */

    /** Grid template columns (uk-gc--repeat-3-1fr). */
    gc: { css: 'gridTemplateColumns', isGridTrackList: true },

    /** Grid template rows (uk-gr--minmax-100px-1fr). */
    gr: { css: 'gridTemplateRows', isGridTrackList: true },

    /** Grid column positioning (uk-gl--1-3 → grid-column: 1 / 3). */
    gl: { css: 'gridColumn', isRange: true },

    /** Grid row positioning (uk-gw--2-4 → grid-row: 2 / 4). */
    gw: { css: 'gridRow', isRange: true },

    /**
     * Transform Properties.
     *
     * CSS transforms with decimal degree support using 'd' notation.
     */

    /** Rotation transform (uk-rt--90, uk-rt--45d5 → 45.5deg). */
    rt: { css: 'rotate', isTransform: 'rotate' },

    /** Scale transform (uk-sc--150 → scale(1.5), supports both percentage and decimal notation). */
    sc: { css: 'scale', isTransform: 'scale' },

    /**
     * Keyword-Based Properties.
     *
     * CSS properties that accept predefined keyword values.
     */

    /** Text alignment (uk-ta--center, uk-ta--left). */
    ta: { css: 'textAlign', isKeyword: true },

    /** Display type (uk-dp--flex, uk-dp--grid, uk-dp--none). */
    dp: { css: 'display', isKeyword: true },

    /** Position type (uk-ps--absolute, uk-ps--relative). */
    ps: { css: 'position', isKeyword: true },

    /** Background size (uk-bz--cover, uk-bz--contain). */
    bz: { css: 'backgroundSize', isKeyword: true },

    /** Flex direction (uk-fd--row, uk-fd--column). */
    fd: { css: 'flexDirection', isKeyword: true },

    /** Justify content (uk-jc--center, uk-jc--space-between). */
    jc: { css: 'justifyContent', isKeyword: true },

    /** Align items (uk-ai--center, uk-ai--flex-start). */
    ai: { css: 'alignItems', isKeyword: true },

    /** Align content (uk-ac--center, uk-ac--space-around). */
    ac: { css: 'alignContent', isKeyword: true },

    /** Flex wrap (uk-fx--wrap, uk-fx--nowrap). */
    fx: { css: 'flexWrap', isKeyword: true },

    /** Overflow behavior (uk-ov--hidden, uk-ov--scroll). */
    ov: { css: 'overflow', isKeyword: true },

    /** Cursor style (uk-cu--pointer, uk-cu--default). */
    cu: { css: 'cursor', isKeyword: true },

    /** Float property (uk-fl--left, uk-fl--right). */
    fl: { css: 'float', isKeyword: true },

    /** Clear property (uk-cl--both, uk-cl--left). */
    cl: { css: 'clear', isKeyword: true },

    /** User select (uk-us--none, uk-us--text). */
    us: { css: 'userSelect', isKeyword: true }
  };

})(Drupal, once, drupalSettings);
