<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

/**
 * Provides utility class rules and CSS property mappings.
 *
 * This service defines the core configuration for UtiliKit utility classes,
 * including the mapping between utility class prefixes and their corresponding
 * CSS properties, validation rules, value types, and categorization. It serves
 * as the central registry for all supported utility class patterns.
 */
class UtilikitRules {

  /**
   * Maps camelCase CSS property names to their kebab-case equivalents.
   *
   * This constant provides the translation between JavaScript-style camelCase
   * property names used internally and the standard kebab-case CSS property
   * names used in generated stylesheets.
   *
   * @var array<string, string>
   */
  const CSS_PROPERTY_MAP = [
    'borderWidth' => 'border-width',
    'borderRadius' => 'border-radius',
    'borderStyle' => 'border-style',
    'borderColor' => 'border-color',
    'backgroundColor' => 'background-color',
    'inlineSize' => 'inline-size',
    'blockSize' => 'block-size',
    'maxInlineSize' => 'max-inline-size',
    'minInlineSize' => 'min-inline-size',
    'maxBlockSize' => 'max-block-size',
    'minBlockSize' => 'min-block-size',
    'insetBlockStart' => 'inset-block-start',
    'insetBlockEnd' => 'inset-block-end',
    'insetInlineStart' => 'inset-inline-start',
    'insetInlineEnd' => 'inset-inline-end',
    'fontSize' => 'font-size',
    'lineHeight' => 'line-height',
    'letterSpacing' => 'letter-spacing',
    'fontWeight' => 'font-weight',
    'textAlign' => 'text-align',
    'zIndex' => 'z-index',
    'aspectRatio' => 'aspect-ratio',
    'backgroundSize' => 'background-size',
    'flexDirection' => 'flex-direction',
    'justifyContent' => 'justify-content',
    'alignItems' => 'align-items',
    'alignContent' => 'align-content',
    'flexWrap' => 'flex-wrap',
    'flexGrow' => 'flex-grow',
    'flexShrink' => 'flex-shrink',
    'flexBasis' => 'flex-basis',
    'gridTemplateColumns' => 'grid-template-columns',
    'gridTemplateRows' => 'grid-template-rows',
    'gridColumn' => 'grid-column',
    'gridRow' => 'grid-row',
    'userSelect' => 'user-select',
  ];

  /**
   * Gets the complete set of utility class rules.
   *
   * Returns the comprehensive configuration array that defines all supported
   * UtiliKit utility classes, their CSS properties, value types, validation
   * rules, and categorization. Each rule defines how a utility class prefix
   * should be processed and what CSS it should generate.
   *
   * Rule structure includes:
   * - css: The CSS property name (camelCase)
   * - sides: Whether the property supports directional values (t/r/b/l)
   * - isNumericFlexible: Accepts numeric values with various units
   * - isKeyword: Accepts CSS keyword values
   * - isColor: Accepts hex color values with optional alpha
   * - isInteger: Accepts integer values only
   * - isDecimalFixed: Accepts decimal values with 'd' notation
   * - isOpacity: Special handling for opacity (0-100 to 0-1)
   * - isTransform: Transform function type (rotate/scale)
   * - isGridTrackList: Complex grid template syntax
   * - isRange: Range values for grid positioning
   * - allowAuto: Allows 'auto' keyword for numeric properties
   * - group: Category for organizational purposes
   *
   * @return array<string, mixed>
   *   Array of utility class rules keyed by prefix abbreviation.
   */
  public static function getRules(): array {
    return [
      'pd' => ['css' => 'padding', 'sides' => TRUE, 'isNumericFlexible' => TRUE, 'group' => 'Box Model'],
      'mg' => [
        'css' => 'margin',
        'sides' => TRUE,
        'isNumericFlexible' => TRUE,
        'allowAuto' => TRUE,
        'group' => 'Box Model',
      ],
      'bw' => ['css' => 'borderWidth', 'sides' => TRUE, 'isNumericFlexible' => TRUE, 'group' => 'Box Model'],
      'br' => ['css' => 'borderRadius', 'sides' => TRUE, 'isNumericFlexible' => TRUE, 'group' => 'Box Model'],

      'bs' => ['css' => 'borderStyle', 'isKeyword' => TRUE, 'sides' => TRUE, 'group' => 'Box Model'],
      'bc' => ['css' => 'borderColor', 'isColor' => TRUE, 'group' => 'Colors'],

      // `allowAuto` on wd/ht/nw and NOT on xw/xh is decided by CSS, not by
      // preference — verified against the browser's own CSS.supports() on
      // 2026-09-05: `inline-size`, `block-size` and `min-inline-size` accept
      // `auto`; `max-inline-size` and `max-block-size` do not (they take
      // `none`). The JS copy of these rules had allowAuto on wd/ht/xw/nw and
      // PHP had it on none of them, so `uk-wd--auto` rendered in inline mode
      // and silently produced NO CSS in static and head mode, while
      // `uk-xw--auto` produced invalid CSS the browser discarded. Measured,
      // not reasoned about.
      //
      // `nh` (min-block-size) does accept `auto` and neither side allows it.
      // That is a missing feature rather than drift, and adding it silently
      // here would be a change nobody asked for.
      'wd' => ['css' => 'inlineSize', 'isNumericFlexible' => TRUE, 'allowAuto' => TRUE, 'group' => 'Sizing'],
      'ht' => ['css' => 'blockSize', 'isNumericFlexible' => TRUE, 'allowAuto' => TRUE, 'group' => 'Sizing'],
      'xw' => ['css' => 'maxInlineSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],
      'nw' => ['css' => 'minInlineSize', 'isNumericFlexible' => TRUE, 'allowAuto' => TRUE, 'group' => 'Sizing'],
      'xh' => ['css' => 'maxBlockSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],
      'nh' => ['css' => 'minBlockSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],

      'tp' => ['css' => 'insetBlockStart', 'isNumericFlexible' => TRUE, 'group' => 'Positioning'],
      'lt' => ['css' => 'insetInlineStart', 'isNumericFlexible' => TRUE, 'group' => 'Positioning'],
      'ri' => ['css' => 'insetInlineEnd', 'isNumericFlexible' => TRUE, 'group' => 'Positioning'],
      'bt' => ['css' => 'insetBlockEnd', 'isNumericFlexible' => TRUE, 'group' => 'Positioning'],

      'fs' => ['css' => 'fontSize', 'isNumericFlexible' => TRUE, 'group' => 'Typography'],
      'lh' => ['css' => 'lineHeight', 'isNumericFlexible' => TRUE, 'group' => 'Typography'],
      'fw' => ['css' => 'fontWeight', 'isInteger' => TRUE, 'group' => 'Typography'],
      'ls' => ['css' => 'letterSpacing', 'isNumericFlexible' => TRUE, 'group' => 'Typography'],
      'op' => ['css' => 'opacity', 'isInteger' => TRUE, 'isOpacity' => TRUE, 'group' => 'Effects'],
      'zi' => ['css' => 'zIndex', 'isInteger' => TRUE, 'group' => 'Effects'],

      'gp' => ['css' => 'gap', 'isNumericFlexible' => TRUE, 'group' => 'Spacing'],
      'ar' => ['css' => 'aspectRatio', 'isRange' => TRUE, 'group' => 'Layout'],

      'bg' => ['css' => 'backgroundColor', 'isColor' => TRUE, 'group' => 'Colors'],
      'tc' => ['css' => 'color', 'isColor' => TRUE, 'group' => 'Colors'],

      'fg' => ['css' => 'flexGrow', 'isDecimalFixed' => TRUE, 'group' => 'Flexbox'],
      'fk' => ['css' => 'flexShrink', 'isDecimalFixed' => TRUE, 'group' => 'Flexbox'],
      'fb' => ['css' => 'flexBasis', 'isNumericFlexible' => TRUE, 'group' => 'Flexbox'],
      'or' => ['css' => 'order', 'isInteger' => TRUE, 'group' => 'Flexbox'],

      'gc' => ['css' => 'gridTemplateColumns', 'isGridTrackList' => TRUE, 'group' => 'Grid'],
      'gr' => ['css' => 'gridTemplateRows', 'isGridTrackList' => TRUE, 'group' => 'Grid'],
      'gl' => ['css' => 'gridColumn', 'isRange' => TRUE, 'group' => 'Grid'],
      'gw' => ['css' => 'gridRow', 'isRange' => TRUE, 'group' => 'Grid'],

      'rt' => ['css' => 'rotate', 'isTransform' => 'rotate', 'group' => 'Transform'],
      'sc' => ['css' => 'scale', 'isTransform' => 'scale', 'group' => 'Transform'],

      'ta' => ['css' => 'textAlign', 'isKeyword' => TRUE, 'group' => 'Typography'],
      'dp' => ['css' => 'display', 'isKeyword' => TRUE, 'group' => 'Layout'],
      'ps' => ['css' => 'position', 'isKeyword' => TRUE, 'group' => 'Positioning'],
      'bz' => ['css' => 'backgroundSize', 'isKeyword' => TRUE, 'group' => 'Background'],
      'fd' => ['css' => 'flexDirection', 'isKeyword' => TRUE, 'group' => 'Flexbox'],
      'jc' => ['css' => 'justifyContent', 'isKeyword' => TRUE, 'group' => 'Flexbox'],
      'ai' => ['css' => 'alignItems', 'isKeyword' => TRUE, 'group' => 'Flexbox'],
      'ac' => ['css' => 'alignContent', 'isKeyword' => TRUE, 'group' => 'Flexbox'],
      'fx' => ['css' => 'flexWrap', 'isKeyword' => TRUE, 'group' => 'Flexbox'],
      'ov' => ['css' => 'overflow', 'isKeyword' => TRUE, 'group' => 'Layout'],
      'cu' => ['css' => 'cursor', 'isKeyword' => TRUE, 'group' => 'Layout'],
      'fl' => ['css' => 'float', 'isKeyword' => TRUE, 'group' => 'Layout'],
      'cl' => ['css' => 'clear', 'isKeyword' => TRUE, 'group' => 'Layout'],
      'us' => ['css' => 'userSelect', 'isKeyword' => TRUE, 'group' => 'Layout'],
    ];
  }

  /**
   * Shorthand keyword values, and what they mean.
   *
   * `uk-jc--between` is friendlier than `uk-jc--space-between`, and the
   * JavaScript engine has always accepted it — it mapped the shorthand before
   * validating. PHP did not, and emitted `justify-content: between;`, which is
   * not valid CSS and which the browser discards.
   *
   * MEASURED 2026-09-05: `uk-jc--between` renders in inline mode and silently
   * does nothing in static and head mode. Confirmed invalid by the browser's
   * own CSS.supports() — `around`, `between` and `evenly` are not real values
   * for either property; only the `space-` forms are.
   *
   * This is the third rule in this module that was written twice, after
   * `allowAuto` and the keyword lists. All three had the same shape: one
   * implementation in each language, disagreeing, with the answer depending on
   * a rendering-mode setting nobody associates with validity.
   *
   * @return array<string, array<string, string>>
   *   CSS property => [shorthand => real CSS value].
   */
  public static function getKeywordShorthand(): array {
    return [
      'justifyContent' => [
        'between' => 'space-between',
        'around' => 'space-around',
        'evenly' => 'space-evenly',
      ],
      'alignContent' => [
        'between' => 'space-between',
        'around' => 'space-around',
        'evenly' => 'space-evenly',
      ],
    ];
  }

  /**
   * The keyword values each CSS property will accept.
   *
   * WHY THIS IS HERE AND NOT ONLY IN JAVASCRIPT. It lived in
   * js/utilikit.rules.apply.js alone, and PHP validated nothing: its
   * applyKeywordRule() accepted any `[\w-]+` and emitted it verbatim.
   * MEASURED 2026-09-05 — `uk-dp--banana` produced `display: banana;` in the
   * generated stylesheet under static and head mode, while the JavaScript
   * engine rejected the same class in inline mode. The same class, two answers,
   * decided by a setting nobody associates with validity. That is the second
   * instance of one rule written twice in this module; the first was
   * `allowAuto`.
   *
   * Ported verbatim from the JavaScript so nothing changes shape in transit.
   * Eighteen of these thirty-three properties currently match no rule — they
   * are kept rather than trimmed, because dropping them would silently remove
   * validation from any rule added for them later.
   *
   * @return array<string, list<string>>
   *   CSS property name (camelCase, as `css` in getRules()) => allowed values.
   */
  public static function getAllowedKeywords(): array {
    return [
      'display' => [
        'none', 'block', 'inline', 'inline-block', 'flex', 'inline-flex',
        'grid', 'inline-grid', 'table', 'table-row', 'table-cell', 'contents',
        'flow-root', 'list-item',
      ],
      'position' => ['static', 'relative', 'absolute', 'fixed', 'sticky'],
      'overflow' => ['visible', 'hidden', 'scroll', 'auto', 'clip'],
      'overflowX' => ['visible', 'hidden', 'scroll', 'auto', 'clip'],
      'overflowY' => ['visible', 'hidden', 'scroll', 'auto', 'clip'],
      'textAlign' => ['left', 'right', 'center', 'justify', 'start', 'end'],
      'float' => ['left', 'right', 'none', 'inline-start', 'inline-end'],
      'clear' => [
        'left', 'right', 'both', 'none', 'inline-start', 'inline-end',
      ],
      'borderStyle' => [
        'none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge',
        'inset', 'outset', 'hidden',
      ],
      'boxSizing' => ['content-box', 'border-box'],
      'cursor' => [
        'auto', 'default', 'none', 'pointer', 'grab', 'grabbing', 'text',
        'move', 'not-allowed', 'crosshair', 'wait', 'help', 'progress', 'zoom-in',
        'zoom-out', 'col-resize', 'row-resize', 'n-resize', 's-resize',
        'e-resize', 'w-resize',
      ],
      // 'contain' is gone: it was in the CSS spec and no browser shipped it.
      // The browser's own CSS.supports('user-select', 'contain') answers false,
      // measured 2026-09-05, so listing it only ever wrote a declaration that
      // was discarded. Both copies of this list agreed on it and both were
      // wrong — agreement between two copies is not evidence.
      'userSelect' => ['none', 'auto', 'text', 'all'],
      'flexDirection' => ['row', 'row-reverse', 'column', 'column-reverse'],
      'flexWrap' => ['nowrap', 'wrap', 'wrap-reverse'],
      'justifyContent' => [
        'start', 'end', 'center', 'between', 'around', 'evenly', 'flex-start',
        'flex-end', 'space-between', 'space-around', 'space-evenly',
        'stretch', 'normal',
      ],
      'alignItems' => [
        'start', 'end', 'center', 'stretch', 'baseline', 'flex-start',
        'flex-end', 'self-start', 'self-end', 'normal',
      ],
      'alignContent' => [
        'start', 'end', 'center', 'stretch', 'between', 'around', 'evenly',
        'flex-start', 'flex-end', 'space-between', 'space-around',
        'space-evenly', 'normal',
      ],
      'order' => ['0', '1', '2', '3', '4', '5', '-1'],
      'visibility' => ['visible', 'hidden', 'collapse'],
      'whiteSpace' => [
        'normal', 'nowrap', 'pre', 'pre-wrap', 'pre-line', 'break-spaces',
      ],
      'wordBreak' => ['normal', 'break-all', 'keep-all', 'break-word'],
      'textDecoration' => ['none', 'underline', 'overline', 'line-through'],
      'textTransform' => ['none', 'uppercase', 'lowercase', 'capitalize'],
      'fontStyle' => ['normal', 'italic', 'oblique'],
      'listStyleType' => ['none', 'disc', 'circle', 'square', 'decimal'],
      'objectFit' => ['contain', 'cover', 'fill', 'none', 'scale-down'],
      'objectPosition' => ['top', 'bottom', 'left', 'right', 'center'],
      'pointerEvents' => ['auto', 'none'],
      'resize' => ['none', 'both', 'horizontal', 'vertical'],
      'tableLayout' => ['auto', 'fixed'],
      'borderCollapse' => ['collapse', 'separate'],
      'verticalAlign' => [
        'baseline', 'top', 'middle', 'bottom', 'sub', 'super', 'text-top',
        'text-bottom',
      ],
      'backgroundSize' => ['auto', 'cover', 'contain'],
    ];
  }

  /**
   * Converts a camelCase CSS property name to kebab-case.
   *
   * Looks up the property name in the CSS_PROPERTY_MAP constant to convert
   * from JavaScript-style camelCase to standard CSS kebab-case format.
   * Returns the property unchanged if no mapping exists.
   *
   * @param string $property
   *   The camelCase CSS property name to convert.
   *
   * @return string
   *   The kebab-case CSS property name, or the original property if no
   *   mapping exists.
   */
  public static function getCssPropertyName(string $property): string {
    return self::CSS_PROPERTY_MAP[$property] ?? $property;
  }

}
