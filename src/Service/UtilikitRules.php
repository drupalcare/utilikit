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
   * @var array
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
   * @return array
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

      'wd' => ['css' => 'inlineSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],
      'ht' => ['css' => 'blockSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],
      'xw' => ['css' => 'maxInlineSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],
      'nw' => ['css' => 'minInlineSize', 'isNumericFlexible' => TRUE, 'group' => 'Sizing'],
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
