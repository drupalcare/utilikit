<?php

declare(strict_types=1);

namespace Drupal\utilikit\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates CSS from UtiliKit utility classes.
 *
 * This service converts UtiliKit utility classes into their corresponding CSS
 * rules. It supports responsive breakpoints, various value types (colors,
 * transforms, grid templates, spacing), caching for performance, and handles
 * complex parsing for features like decimal notation and grid track lists.
 */
class UtilikitCssGenerator {

  /**
   * Constructs a new UtilikitCssGenerator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend service.
   * @param \Drupal\utilikit\Service\UtilikitContentScanner $contentScanner
   *   The content scanner service for validation.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service for recording CSS generation operations.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly CacheBackendInterface $cache,
    protected readonly UtilikitContentScanner $contentScanner,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Generates CSS from an array of utility classes.
   *
   * Processes utility classes to generate corresponding CSS rules, organized
   * by breakpoints and cached for performance. Handles validation, filtering,
   * and responsive media query generation.
   *
   * @param list<string> $classes
   *   Array of utility class names to process.
   *
   * @return string
   *   Generated CSS string with media queries for responsive classes.
   */
  public function generateCssFromClasses(array $classes): string {
    // Filter out null, empty, and non-string values FIRST.
    $classes = array_filter($classes, static fn ($class) => is_string($class) && $class !== '');

    // Remove duplicates.
    $classes = array_unique($classes);

    if (count($classes) > UtilikitConstants::MAX_CLASSES_WARNING_THRESHOLD) {
      $this->logger->error('Class count exceeds hard limit: @count > @limit', [
        '@count' => count($classes),
        '@limit' => UtilikitConstants::MAX_CLASSES_WARNING_THRESHOLD,
      ]);
      throw new \RuntimeException('Too many utility classes to process. Please clean up unused classes.');
    }

    sort($classes);

    // Fetch config once before the loop to avoid repeated calls.
    $config = $this->configFactory->get('utilikit.settings');
    $activeBreakpoints = array_keys(array_filter($config->get('active_breakpoints') ?? []));
    if (empty($activeBreakpoints)) {
      $activeBreakpoints = array_keys(UtilikitConstants::DEFAULT_BREAKPOINTS);
    }
    $useImportant = (bool) ($config->get('use_important') ?? TRUE);
    $renderingMode = $config->get('rendering_mode') ?? 'inline';

    // Include settings that affect CSS output in the cache key so changes
    // to use_important, rendering_mode, or breakpoints produce fresh CSS.
    $configHash = md5(json_encode([
      $classes,
      $useImportant,
      $renderingMode,
      $activeBreakpoints,
    ]));
    $cid = 'utilikit:css:' . $configHash;

    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $rules = UtilikitRules::getRules();
    $cssRules = [
      'base' => [],
      'sm' => [],
      'md' => [],
      'lg' => [],
      'xl' => [],
      'xxl' => [],
    ];

    $processedClasses = [
      'base' => [],
      'sm' => [],
      'md' => [],
      'lg' => [],
      'xl' => [],
      'xxl' => [],
    ];

    foreach ($classes as $class) {
      // Double-check even after filtering (defensive programming).
      if (empty($class) || !is_string($class)) {
        continue;
      }
      if (!$this->contentScanner->isValidUtilityClass($class)) {
        continue;
      }

      $pattern = '/^uk-(?:(sm|md|lg|xl|xxl)-)?(\w+)--(.+)$/';
      if (!preg_match($pattern, $class, $matches)) {
        continue;
      }

      $breakpoint = $matches[1] ?: NULL;
      $prefix = $matches[2];
      $suffix = $matches[3];

      $group = $breakpoint ?: 'base';

      if (in_array($class, $processedClasses[$group], TRUE)) {
        continue;
      }

      if ($breakpoint && !in_array($breakpoint, $activeBreakpoints, TRUE)) {
        continue;
      }

      if (!isset($rules[$prefix])) {
        continue;
      }

      $rule = $rules[$prefix];

      if (!empty($rule['isGridTrackList'])) {
        $rules['gc']['isGridTrackList'] = TRUE;
        $rules['gr']['isGridTrackList'] = TRUE;
        $declaration = $this->parseGridTrackList($class, $rule);
      }
      else {
        $declaration = $this->applyRule($rule, $prefix, $suffix, $class);
      }

      if (!$declaration) {
        continue;
      }

      // Handle structured return from color rules (with pseudo-state).
      $pseudoState = NULL;
      if (is_array($declaration)) {
        $pseudoState = $declaration['pseudoState'] ?? NULL;
        $declaration = $declaration['declaration'];
      }

      $selector = '.' . $this->escapeCssSelector($class);

      // Append pseudo-state selector for static/head modes.
      if ($pseudoState && isset(UtilikitConstants::PSEUDO_STATE_MAP[$pseudoState])) {
        $selector .= ':' . UtilikitConstants::PSEUDO_STATE_MAP[$pseudoState];
      }

      if ($useImportant && in_array($renderingMode, ['static', 'head'], TRUE)) {
        $declaration = str_replace(';', ' !important;', $declaration);
      }

      $cssRule = "  $selector { $declaration }";

      $cssRules[$group][] = $cssRule;
      $processedClasses[$group][] = $class;
    }

    foreach ($cssRules as $group => &$rules) {
      sort($rules);
    }

    $css = '';

    if (!empty($cssRules['base'])) {
      $css .= implode("\n", $cssRules['base']) . "\n";
    }

    foreach (UtilikitConstants::BREAKPOINT_VALUES as $bp => $width) {
      if (!empty($cssRules[$bp])) {
        $css .= "@media (min-width: {$width}px) {\n";
        $css .= implode("\n", $cssRules[$bp]);
        $css .= "\n}\n";
      }
    }

    $this->cache->set($cid, $css, CacheBackendInterface::CACHE_PERMANENT, [UtilikitConstants::CACHE_TAG_CSS]);

    return $css;
  }

  /**
   * Escapes special characters in CSS selectors.
   *
   * Handles CSS selector escaping while preserving decimal notation with
   * special handling for dots in decimal numbers (e.g., "1.5" becomes "1\\.5").
   *
   * @param string $selector
   *   The CSS selector to escape.
   *
   * @return string
   *   Escaped CSS selector safe for use in stylesheets.
   */
  private function escapeCssSelector(string $selector): string {
    // Escape each character individually: walk the string and escape any
    // CSS special character with a backslash. This avoids fragile
    // placeholder-based approaches and handles all edge cases.
    $special = [
      '!' => TRUE,
      '"' => TRUE,
      '#' => TRUE,
      '$' => TRUE,
      '%' => TRUE,
      '&' => TRUE,
      "'" => TRUE,
      '(' => TRUE,
      ')' => TRUE,
      '*' => TRUE,
      '+' => TRUE,
      ',' => TRUE,
      '.' => TRUE,
      '/' => TRUE,
      ':' => TRUE,
      ';' => TRUE,
      '<' => TRUE,
      '=' => TRUE,
      '>' => TRUE,
      '?' => TRUE,
      '@' => TRUE,
      '[' => TRUE,
      '\\' => TRUE,
      ']' => TRUE,
      '^' => TRUE,
      '`' => TRUE,
      '{' => TRUE,
      '|' => TRUE,
      '}' => TRUE,
      '~' => TRUE,
    ];

    $escaped = '';
    $length = strlen($selector);
    for ($i = 0; $i < $length; $i++) {
      $char = $selector[$i];
      if (isset($special[$char])) {
        $escaped .= '\\' . $char;
      }
      else {
        $escaped .= $char;
      }
    }

    return $escaped;
  }

  /**
   * Applies a utility rule to generate CSS declaration.
   *
   * Routes rule processing to appropriate handlers based on rule type
   * (sides, keywords, transforms, colors, etc.) and generates the
   * corresponding CSS declaration.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition.
   * @param string $prefix
   *   The utility class prefix.
   * @param string $suffix
   *   The utility class value suffix.
   * @param string $className
   *   The complete utility class name.
   *
   * @return string|array|null
   *   Generated CSS declaration string, structured array (for color rules
   *   with pseudo-state info), or NULL if rule cannot be applied.
   *
   * @phpstan-return string|array<string, mixed>|null
   */
  private function applyRule(array $rule, string $prefix, string $suffix, string $className): string|array|null {
    if (!empty($rule['sides'])) {
      $result = $this->applySidesRule($rule, $prefix, $suffix);
      if ($result) {
        return $result;
      }
    }

    $result = $this->applySpacingShorthandRule($rule, $prefix, $suffix);
    if ($result) {
      return $result;
    }

    if (!empty($rule['isKeyword'])) {
      return $this->applyKeywordRule($rule, $suffix);
    }

    if (!empty($rule['isTransform'])) {
      return $this->applyTransformRule($rule, $suffix);
    }

    if ($prefix === 'gp') {
      $result = $this->applyGapRule($rule, $prefix, $suffix);
      if ($result) {
        return $result;
      }
    }

    if (!empty($rule['isOpacity']) || !empty($rule['isDecimalFixed']) || !empty($rule['isInteger']) || !empty($rule['isNumericFlexible'])) {
      return $this->applyNumericRule($rule, $prefix, $suffix);
    }

    if (!empty($rule['isColor'])) {
      $colorResult = $this->applyColorRule($rule, $suffix);
      if ($colorResult === NULL) {
        return NULL;
      }
      // Return structured result with pseudo-state info for color rules.
      return $colorResult;
    }

    if (!empty($rule['isFractional'])) {
      return $this->applyFractionalRule($rule, $suffix);
    }

    if (!empty($rule['isRange'])) {
      return $this->applyRangeRule($rule, $suffix);
    }

    return NULL;
  }

  /**
   * Applies rules for properties that support directional sides.
   *
   * Handles utility classes that target specific sides (top, right, bottom,
   * left) for properties like padding, margin, border-width, and
   * border-radius.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition.
   * @param string $prefix
   *   The utility class prefix.
   * @param string $suffix
   *   The utility class value suffix with side specifier.
   *
   * @return string|null
   *   Generated CSS declaration or NULL if rule doesn't match pattern.
   */
  private function applySidesRule(array $rule, string $prefix, string $suffix): ?string {
    // Keyword sides: e.g. uk-bs--b-dashed → border-bottom-style: dashed.
    if (!empty($rule['isKeyword']) && preg_match('/^(t|r|b|l)-([\w-]+)$/', $suffix, $matches)) {
      $side = $matches[1];
      $keyword = $matches[2];
      $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);
      $sideMap = [
        't' => '-block-start',
        'r' => '-inline-end',
        'b' => '-block-end',
        'l' => '-inline-start',
      ];
      // e.g. border-style + -block-end → border-block-end-style.
      $sidedProperty = preg_replace('/^(border)(-\w+)$/', '$1' . $sideMap[$side] . '$2', $cssProperty);
      return "{$sidedProperty}: $keyword;";
    }

    if (!preg_match('/^(t|r|b|l)-(auto|\d+(?:d\d+)?(?:px|pr|em|rem|vh|vw)?)$/', $suffix, $matches)) {
      return NULL;
    }

    $side = $matches[1];
    $rawValue = $matches[2];

    if ($rawValue === 'auto') {
      $value = 'auto';
    }
    else {
      // Convert 'd' to '.' for decimal notation.
      $convertedValue = str_replace('d', '.', $rawValue);

      // Check if value already has a unit.
      if (preg_match('/^(\d+(?:\.\d+)?)(px|pr|em|rem|vh|vw)$/', $convertedValue, $unitMatches)) {
        // Value already has a unit.
        $unit = $unitMatches[2] === 'pr' ? '%' : $unitMatches[2];
        $value = $unitMatches[1] . $unit;
      }
      else {
        // Value has no unit, default to px.
        $value = $convertedValue . 'px';
      }
    }

    if ($prefix === 'br') {
      if ($value === 'auto') {
        return NULL;
      }
      $radiusMap = [
        't' => ['start-start', 'start-end'],
        'r' => ['start-end', 'end-end'],
        'b' => ['end-end', 'end-start'],
        'l' => ['start-start', 'end-start'],
      ];
      $declarations = [];
      foreach ($radiusMap[$side] as $corner) {
        $declarations[] = "border-{$corner}-radius: $value";
      }
      return implode('; ', $declarations) . ';';
    }
    elseif ($prefix === 'bw') {
      $sideMap = ['t' => 'block-start', 'r' => 'inline-end', 'b' => 'block-end', 'l' => 'inline-start'];
      return "border-{$sideMap[$side]}-width: $value;";
    }
    else {
      $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);
      $sideMap = ['t' => '-block-start', 'r' => '-inline-end', 'b' => '-block-end', 'l' => '-inline-start'];
      return "{$cssProperty}{$sideMap[$side]}: $value;";
    }
  }

  /**
   * Apply spacing shorthand rules for padding and margin (1-4 values).
   *
   * Examples:
   * - "uk-pd--1d5rem" => "padding: 1.5rem;"
   * - "uk-pd--0d5rem-1rem" => "padding: 0.5rem 1rem;"
   * - "uk-mg--0d5rem-auto" => "margin: 0.5rem auto;"
   *
   * @param array<string, mixed> $rule
   *   The rule definition for this prefix.
   * @param string $prefix
   *   The utility class prefix.
   * @param string $suffix
   *   The utility class value suffix.
   *
   * @return string|null
   *   The CSS declaration, or NULL when the rule does not apply.
   */
  private function applySpacingShorthandRule(array $rule, string $prefix, string $suffix): ?string {
    // Only handle rules with 'sides' property and specific prefixes.
    if (empty($rule['sides']) || !in_array($prefix, ['pd', 'mg', 'bw', 'br'])) {
      return NULL;
    }

    // Split by dash, but preserve 'auto' keyword.
    $parts = explode('-', $suffix);
    $values = [];

    foreach ($parts as $part) {
      if ($part === 'auto') {
        $values[] = 'auto';
      }
      else {
        // Updated regex to handle decimals with 'd' notation
        // Matches: 10, 1d5, 0d25, 10px, 1d5rem, 0d5em, etc.
        if (preg_match('/^(\d+(?:d\d+)?)(px|pr|em|rem|vh|vw)?$/', $part, $matches)) {
          // Convert 'd' to '.' for decimal values.
          $numericValue = str_replace('d', '.', $matches[1]);

          // Properties that don't support percentage values.
          $noPercentageProperties = ['bw'];
          if (isset($matches[2]) && $matches[2] === 'pr' && in_array($prefix, $noPercentageProperties)) {
            return NULL;
          }

          // Handle units.
          if (!empty($matches[2])) {
            $unit = $matches[2] === 'pr' ? '%' : $matches[2];
            $values[] = $numericValue . $unit;
          }
          else {
            // Default to px if no unit specified.
            $values[] = $numericValue . 'px';
          }
        }
        else {
          // Invalid format.
          return NULL;
        }
      }
    }

    // Validate we got no more than 4 values. The lower bound needs no check:
    // explode() always yields at least one part, and every path through the
    // loop above either appends a value or returns NULL, so $values cannot be
    // empty here.
    if (count($values) > 4) {
      return NULL;
    }

    // Apply CSS shorthand logic using logical properties.
    // block-start = top, inline-end = right, block-end = bottom,
    // inline-start = left (in LTR writing mode).
    $blockStart = $inlineEnd = $blockEnd = $inlineStart = NULL;

    switch (count($values)) {
      case 1:
        // All sides same.
        $blockStart = $inlineEnd = $blockEnd = $inlineStart = $values[0];
        break;

      case 2:
        // Block | Inline.
        $blockStart = $blockEnd = $values[0];
        $inlineEnd = $inlineStart = $values[1];
        break;

      case 3:
        // Block-start | Inline | Block-end.
        $blockStart = $values[0];
        $inlineEnd = $inlineStart = $values[1];
        $blockEnd = $values[2];
        break;

      case 4:
        // Block-start | Inline-end | Block-end | Inline-start.
        $blockStart = $values[0];
        $inlineEnd = $values[1];
        $blockEnd = $values[2];
        $inlineStart = $values[3];
        break;
    }

    // Generate CSS based on prefix using logical properties.
    $declarations = [];

    switch ($prefix) {
      case 'pd':
        $declarations[] = "padding-block-start: $blockStart";
        $declarations[] = "padding-inline-end: $inlineEnd";
        $declarations[] = "padding-block-end: $blockEnd";
        $declarations[] = "padding-inline-start: $inlineStart";
        break;

      case 'mg':
        $declarations[] = "margin-block-start: $blockStart";
        $declarations[] = "margin-inline-end: $inlineEnd";
        $declarations[] = "margin-block-end: $blockEnd";
        $declarations[] = "margin-inline-start: $inlineStart";
        break;

      case 'bw':
        $declarations[] = "border-block-start-width: $blockStart";
        $declarations[] = "border-inline-end-width: $inlineEnd";
        $declarations[] = "border-block-end-width: $blockEnd";
        $declarations[] = "border-inline-start-width: $inlineStart";
        break;

      case 'br':
        $declarations[] = "border-start-start-radius: $blockStart";
        $declarations[] = "border-start-end-radius: $inlineEnd";
        $declarations[] = "border-end-end-radius: $blockEnd";
        $declarations[] = "border-end-start-radius: $inlineStart";
        break;
    }

    return implode('; ', $declarations) . ';';
  }

  /**
   * Applies keyword-based CSS rules.
   *
   * Handles utility classes that use CSS keyword values like 'flex',
   * 'center', 'hidden', etc., directly as the CSS property value.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition.
   * @param string $suffix
   *   The keyword value suffix.
   *
   * @return string|null
   *   Generated CSS declaration or NULL if invalid keyword.
   */
  private function applyKeywordRule(array $rule, string $suffix): ?string {
    if (!preg_match('/^([\w-]+)$/', $suffix)) {
      return NULL;
    }

    // The value has to be a keyword the property actually accepts. Without
    // this, `uk-dp--banana` produced `display: banana;` in the generated
    // stylesheet — measured 2026-09-05 — while the JavaScript engine rejected
    // the identical class in inline mode. One class, two answers, decided by a
    // rendering-mode setting nobody associates with validity.
    //
    // Returning NULL is the same answer the engine gives: no declaration at
    // all, rather than one the browser will discard. Inline is the default
    // mode, so this aligns the other two with the behaviour most sites already
    // have.
    // Shorthand first, then validate — the same order the JavaScript engine
    // uses. `uk-jc--between` means `space-between`; without this step it
    // produced `justify-content: between`, which the browser discards.
    $value = UtilikitRules::getKeywordShorthand()[$rule['css']][$suffix] ?? $suffix;

    $allowed = UtilikitRules::getAllowedKeywords()[$rule['css']] ?? NULL;

    if ($allowed !== NULL && !in_array($value, $allowed, TRUE)) {
      return NULL;
    }

    $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);
    return "$cssProperty: $value;";
  }

  /**
   * Applies CSS transform rules (rotate, scale).
   *
   * Handles transform utility classes for rotation (in degrees) and
   * scaling (as percentage values converted to decimal scale factors).
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition with transform type.
   * @param string $suffix
   *   The transform value suffix.
   *
   * @return string|null
   *   Generated CSS transform declaration or NULL if invalid.
   */
  private function applyTransformRule(array $rule, string $suffix): ?string {
    if ($rule['isTransform'] === 'rotate') {
      if (!preg_match('/^(\d+)$/', $suffix, $matches)) {
        return NULL;
      }
      $value = (int) $matches[1];
      return "transform: rotate({$value}deg);";
    }
    elseif ($rule['isTransform'] === 'scale') {
      // Handle both percentage format (150 = 1.5x)
      // and decimal format (1d5 = 1.5x)
      if (str_contains($suffix, 'd')) {
        // Convert 'd' format to decimal for scale values.
        $suffix = str_replace('d', '.', $suffix);

        if (!preg_match('/^(\d+(?:\.\d+)?)$/', $suffix, $matches)) {
          return NULL;
        }
        $scale = (float) $matches[1];
      }
      else {
        // Convert percentage to decimal scale factor
        // (100% = 1.0) - matches JavaScript behavior.
        if (!preg_match('/^(\d+)$/', $suffix, $matches)) {
          return NULL;
        }
        $scale = (float) $matches[1] / 100;
      }

      return "transform: scale($scale);";
    }

    return NULL;
  }

  /**
   * Applies the gap rule (single or two values) with `d`-decimal support.
   *
   * Handles CSS Grid and Flexbox gap properties with support for single
   * values (both row and column) or separate row/column gap values.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition.
   * @param string $prefix
   *   The utility class prefix (should be 'gp').
   * @param string $suffix
   *   The gap value suffix with optional row/column values.
   *
   * @return string|null
   *   Generated CSS gap declaration or NULL if invalid.
   */
  private function applyGapRule(array $rule, string $prefix, string $suffix): ?string {
    if ($prefix !== 'gp') {
      return NULL;
    }

    // Accept integers or d-decimals, optional second value, and optional 'p'
    // for percent.
    if (!preg_match('/^(\d+(?:d\d+)?)(?:-(\d+(?:d\d+)?))?(p?)$/', $suffix, $matches)) {
      return NULL;
    }

    $row = $this->convertDecimalNotation($matches[1]);
    $col = !empty($matches[2]) ? $this->convertDecimalNotation($matches[2]) : $row;
    $unit = ($matches[3] === 'p') ? '%' : 'px';

    $rowGap = $row . $unit;
    $colGap = $col . $unit;

    return "gap: {$rowGap} {$colGap};";
  }

  /**
   * Applies numeric rules (opacity, integers, decimals, flexible units).
   *
   * Handles various numeric value types including opacity (0-100),
   * integers, decimal fixed values, and flexible numeric values with
   * unit support.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition with numeric type flags.
   * @param string $prefix
   *   The utility class prefix for context.
   * @param string $suffix
   *   The numeric value suffix.
   *
   * @return string|null
   *   Generated CSS declaration or NULL if invalid numeric value.
   */
  private function applyNumericRule(array $rule, string $prefix, string $suffix): ?string {
    $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);

    // Handle opacity first.
    if (!empty($rule['isOpacity'])) {
      if (!preg_match('/^(\d{1,3})$/', $suffix, $matches)) {
        return NULL;
      }
      $value = (int) $matches[1];
      if ($value < 0 || $value > 100) {
        return NULL;
      }
      $opacity = $value / 100;
      return "$cssProperty: $opacity;";
    }

    // Handle decimal fixed with 'd' notation.
    if (!empty($rule['isDecimalFixed'])) {
      if (!preg_match('/^(?:\d+|\d+d\d{1,2}|0d\d{1,2})$/', $suffix)) {
        return NULL;
      }
      $value = str_replace('d', '.', $suffix);
      return "$cssProperty: $value;";
    }

    // Handle integer.
    if (!empty($rule['isInteger'])) {
      if (!preg_match('/^(-?\d+)$/', $suffix, $matches)) {
        return NULL;
      }
      return "$cssProperty: {$matches[1]};";
    }

    // Handle numeric flexible with 'd' notation.
    if (!empty($rule['isNumericFlexible'])) {
      if (!empty($rule['allowAuto']) && $suffix === 'auto') {
        return "$cssProperty: auto;";
      }

      if (!preg_match('/^(\d+(?:d\d+)?)(px|pr|em|rem|vh|vw)?$/', $suffix, $matches)) {
        return NULL;
      }

      // Convert 'd' to '.'.
      $value = str_replace('d', '.', $matches[1]);

      // Properties that don't support percentage values.
      $noPercentageProperties = ['bw', 'ls'];
      if (isset($matches[2]) && $matches[2] === 'pr' && in_array($prefix, $noPercentageProperties)) {
        return NULL;
      }

      $viewportAllowedRules = ['wd', 'ht', 'xw', 'xh', 'nw', 'nh', 'fs'];
      if (in_array($matches[2] ?? '', ['vh', 'vw']) && !in_array($prefix, $viewportAllowedRules)) {
        return NULL;
      }

      // Special handling for line-height without units.
      if ($prefix === 'lh' && empty($matches[2]) && floatval($value) <= 10) {
        // Unitless.
        return "$cssProperty: $value;";
      }

      $unit = 'px';
      if (!empty($matches[2])) {
        $unit = $matches[2] === 'pr' ? '%' : $matches[2];
      }

      return "$cssProperty: {$value}{$unit};";
    }

    return NULL;
  }

  /**
   * Applies color rules with hex values and optional alpha transparency.
   *
   * Handles color utility classes supporting 3-digit and 6-digit hex
   * values with optional alpha transparency specified as percentage.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition for color properties.
   * @param string $suffix
   *   The color value suffix (hex with optional alpha).
   *
   * @return array<string, mixed>|null
   *   The generated colour declaration, or NULL if the colour is invalid.
   */
  private function applyColorRule(array $rule, string $suffix): ?array {
    $result = $this->parseColorWithAlpha($suffix);
    if ($result === NULL) {
      return NULL;
    }
    $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);
    return [
      'declaration' => "$cssProperty: {$result['color']};",
      'pseudoState' => $result['pseudoState'],
    ];
  }

  /**
   * Convert 'd' decimal notation to standard decimal notation.
   *
   * @param string $value
   *   Value with 'd' as decimal separator.
   *
   * @return string
   *   Value with '.' as decimal separator.
   */
  protected function convertDecimalNotation(string $value): string {
    return str_replace('d', '.', $value);
  }

  /**
   * Applies fractional rules for CSS Grid fr units.
   *
   * Handles utility classes that use CSS Grid fractional units (fr)
   * for flexible track sizing.
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition.
   * @param string $suffix
   *   The fractional value suffix.
   *
   * @return string|null
   *   Generated CSS declaration with fr unit or NULL if invalid.
   */
  private function applyFractionalRule(array $rule, string $suffix): ?string {
    if (!preg_match('/^(\d+)fr$/', $suffix, $matches)) {
      return NULL;
    }
    $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);
    return "$cssProperty: {$matches[1]}fr;";
  }

  /**
   * Applies range rules for CSS Grid line positioning.
   *
   * Handles utility classes that specify grid line ranges using
   * start/end syntax (e.g., "1-3" becomes "1 / 3").
   *
   * @param array<string, mixed> $rule
   *   The utility rule definition.
   * @param string $suffix
   *   The range value suffix (start-end format).
   *
   * @return string|null
   *   Generated CSS grid positioning declaration or NULL if invalid.
   */
  private function applyRangeRule(array $rule, string $suffix): ?string {
    if (!preg_match('/^(\d+)-(\d+)$/', $suffix, $matches)) {
      return NULL;
    }
    $cssProperty = UtilikitRules::getCssPropertyName($rule['css']);
    return "$cssProperty: {$matches[1]} / {$matches[2]};";
  }

  /**
   * Parses color values with optional alpha and pseudo-state suffix.
   *
   * Converts hex color values (3 or 6 digits) with optional alpha
   * percentage and optional pseudo-state suffix (-h, -f, -a) into
   * CSS color values and pseudo-state identifiers.
   *
   * @param string $suffix
   *   Color suffix with optional alpha and state (e.g., "ff0000-50-h").
   *
   * @return array<string, mixed>|null
   *   Array with 'color' (CSS value) and 'pseudoState' (h|f|a|null),
   *   or NULL if invalid format.
   */
  private function parseColorWithAlpha(string $suffix): ?array {
    if (preg_match('/^([0-9a-fA-F]{6})(?:-(\d{1,3}))?(?:-(h|f|a))?$/', $suffix, $matches)) {
      $hex = $matches[1];
      $alpha = isset($matches[2]) && $matches[2] !== '' ? min((int) $matches[2], 100) / 100 : 1;
      $pseudoState = $matches[3] ?? NULL;

      if ($alpha < 1) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return ['color' => "rgba($r, $g, $b, $alpha)", 'pseudoState' => $pseudoState];
      }
      return ['color' => "#$hex", 'pseudoState' => $pseudoState];
    }

    if (preg_match('/^([0-9a-fA-F]{3})(?:-(\d{1,3}))?(?:-(h|f|a))?$/', $suffix, $matches)) {
      $hex = str_split($matches[1]);
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
      $alpha = isset($matches[2]) && $matches[2] !== '' ? min((int) $matches[2], 100) / 100 : 1;
      $pseudoState = $matches[3] ?? NULL;

      if ($alpha < 1) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return ['color' => "rgba($r, $g, $b, $alpha)", 'pseudoState' => $pseudoState];
      }
      return ['color' => "#$hex", 'pseudoState' => $pseudoState];
    }

    return NULL;
  }

  /**
   * Parse grid template values with decimal support.
   *
   * Examples:
   * - "uk-gc--1fr-2d5fr-0d5fr" => "grid-template-columns: 1fr 2.5fr 0.5fr;"
   * - "uk-gc--repeat-3-1d5fr"  => "grid-template-columns: repeat(3, 1.5fr);"
   *
   * @param string $class
   *   The complete utility class name.
   * @param array<string, mixed> $rule
   *   The rule definition for this prefix.
   *
   * @return string|null
   *   The CSS declaration, or NULL when the class does not parse.
   */
  private function parseGridTrackList(string $class, array $rule): ?string {
    // Extract the suffix after prefix--.
    if (!preg_match('/^uk-(?:(?:sm|md|lg|xl|xxl)-)?(?:gc|gr)--(.+)$/', $class, $matches)) {
      return NULL;
    }

    $suffix = $matches[1];
    $isColumns = str_contains($class, '-gc--');

    // Validate against malformed patterns - reject nested repeat calls.
    if (preg_match('/repeat.*repeat/', $suffix)) {
      return NULL;
    }

    // Use smart split to handle complex cases.
    $tokens = $this->smartSplitGrid($suffix);

    $parsed = [];
    $i = 0;

    while ($i < count($tokens)) {
      $token = $tokens[$i];

      // Convert 'd' to '.' in the token BEFORE processing.
      $token = str_replace('d', '.', $token);

      // Additional validation - reject if we encounter 'repeat'
      // as a regular token
      // (not as the start of a repeat function)
      if ($token === 'repeat' && $i > 0 && $tokens[$i - 1] !== 'repeat') {
        return NULL;
      }

      // Handle different token types.
      if ($token === 'repeat') {
        // Handle repeat() function.
        if ($i + 1 < count($tokens)) {
          $count = str_replace('d', '.', $tokens[$i + 1]);

          // Check if next token is minmax for auto-fit/auto-fill patterns.
          if (($count === 'auto-fit' || $count === 'auto-fill' || $count === 'autofit' || $count === 'autofill')
              && $i + 2 < count($tokens) && $tokens[$i + 2] === 'minmax'
              && $i + 4 < count($tokens)) {

            // Handle repeat(auto-fit, minmax(...))
            $minVal = str_replace('d', '.', $tokens[$i + 3]);
            $maxVal = str_replace('d', '.', $tokens[$i + 4]);

            // Validate minmax parameters - fitc cannot be used in minmax.
            if ($maxVal === 'fitc' || $minVal === 'fitc') {
              return NULL;
            }

            // Convert units.
            $minVal = $this->convertGridUnit($minVal);
            $maxVal = $this->convertGridUnit($maxVal);

            // Normalize auto-fit/auto-fill.
            $countNormalized = str_replace('autofit', 'auto-fit', $count);
            $countNormalized = str_replace('autofill', 'auto-fill', $countNormalized);

            $parsed[] = "repeat($countNormalized, minmax($minVal, $maxVal))";
            $i += 5;
            continue;
          }
          // Check if next token is minmax for ANY repeat pattern.
          elseif ($i + 2 < count($tokens) && $tokens[$i + 2] === 'minmax'
                  && $i + 4 < count($tokens)) {
            // Handle repeat(n, minmax(...))
            $minVal = str_replace('d', '.', $tokens[$i + 3]);
            $maxVal = str_replace('d', '.', $tokens[$i + 4]);

            // Validate minmax parameters - fitc cannot be used in minmax.
            if ($maxVal === 'fitc' || $minVal === 'fitc') {
              return NULL;
            }

            // Convert units.
            $minVal = $this->convertGridUnit($minVal);
            $maxVal = $this->convertGridUnit($maxVal);

            $parsed[] = "repeat($count, minmax($minVal, $maxVal))";
            $i += 5;
            continue;
          }
          // Regular repeat for non-minmax patterns.
          elseif ($i + 2 < count($tokens)) {
            $value = str_replace('d', '.', $tokens[$i + 2]);
            $value = $this->convertGridUnit($value);
            $countNormalized = str_replace('autofit', 'auto-fit', $count);
            $countNormalized = str_replace('autofill', 'auto-fill', $countNormalized);

            $parsed[] = "repeat($countNormalized, $value)";
            $i += 3;
            continue;
          }
        }
      }
      elseif ($token === 'minmax') {
        // Handle minmax() function.
        if ($i + 2 < count($tokens)) {
          $min = str_replace('d', '.', $tokens[$i + 1]);
          $max = str_replace('d', '.', $tokens[$i + 2]);

          // Validate minmax parameters - fitc cannot be used in minmax.
          if ($max === 'fitc' || $min === 'fitc') {
            return NULL;
          }

          $min = $this->convertGridUnit($min);
          $max = $this->convertGridUnit($max);

          $parsed[] = "minmax($min, $max)";
          $i += 3;
          continue;
        }
      }
      elseif ($token === 'fitc' || $token === 'fit-content') {
        // Handle fit-content() function.
        if ($i + 1 < count($tokens)) {
          $size = str_replace('d', '.', $tokens[$i + 1]);
          $size = $this->convertGridUnit($size);

          $parsed[] = "fit-content($size)";
          $i += 2;
          continue;
        }
        else {
          // fit-content without parameters.
          $parsed[] = "fit-content()";
          $i++;
          continue;
        }
      }
      else {
        // Regular value (auto, fr, px, %, etc.)
        $value = $this->convertGridUnit($token);
        $parsed[] = $value;
        $i++;
      }
    }

    $cssValue = implode(' ', $parsed);
    $property = $isColumns ? 'grid-template-columns' : 'grid-template-rows';

    return "$property: $cssValue;";
  }

  /**
   * Convert grid unit values, handling 'd' notation.
   *
   * Processes grid-specific units and keyword values, converting
   * abbreviated forms to full CSS values.
   *
   * @param string $value
   *   Grid unit value to convert.
   *
   * @return string
   *   Converted CSS grid value.
   */
  private function convertGridUnit(string $value): string {
    // Handle 'pr' to '%' conversion.
    $value = str_replace('pr', '%', $value);

    // Handle minc/maxc keywords.
    $value = str_replace('minc', 'min-content', $value);
    $value = str_replace('maxc', 'max-content', $value);

    // Handle pure numbers without units - add px.
    if (preg_match('/^\d+(\.\d+)?$/', $value)) {
      return $value . 'px';
    }

    // Return as-is for: auto, fr units, px, %, em, rem, etc.
    return $value;
  }

  /**
   * Smart split for grid values, preserving decimal notation.
   *
   * Examples:
   * - "1fr-2d5fr-0d5fr" => ["1fr", "2d5fr", "0d5fr"]
   * - "repeat-3-1d5fr"  => ["repeat", "3", "1d5fr"]
   * - "minmax-0d5rem-2fr" => ["minmax", "0d5rem", "2fr"]
   *
   * @phpstan-return list<string>
   */
  private function smartSplitGrid(string $str): array {
    $tokens = [];
    $current = '';
    $length = strlen($str);

    for ($i = 0; $i < $length; $i++) {
      $char = $str[$i];

      if ($char === '-') {

        // Check if this dash is part of auto-fit or auto-fill.
        if ($current === 'auto' && $i + 1 < $length) {
          $remaining = substr($str, $i + 1);
          if (str_starts_with($remaining, 'fit')) {
            $current = 'auto-fit';
            // Skip 'fit'.
            $i += 3;
            $tokens[] = $current;
            $current = '';
            continue;
          }
          elseif (str_starts_with($remaining, 'fill')) {
            $current = 'auto-fill';
            // Skip 'fill'.
            $i += 4;
            $tokens[] = $current;
            $current = '';
            continue;
          }
        }

        // Check if this dash is part of min-content or max-content.
        if ($current === 'min' && str_starts_with(substr($str, $i + 1), 'content')) {
          $current = 'min-content';
          // Skip 'content'.
          $i += 7;
          $tokens[] = $current;
          $current = '';
          continue;
        }
        if ($current === 'max' && str_starts_with(substr($str, $i + 1), 'content')) {
          $current = 'max-content';
          // Skip 'content'.
          $i += 7;
          $tokens[] = $current;
          $current = '';
          continue;
        }

        // Check if this dash is part of fit-content.
        if ($current === 'fit' && str_starts_with(substr($str, $i + 1), 'content')) {
          $current = 'fit-content';
          // Skip 'content'.
          $i += 7;
          $tokens[] = $current;
          $current = '';
          continue;
        }

        // Otherwise, this dash is a separator.
        if ($current !== '') {
          $tokens[] = $current;
          $current = '';
        }
      }
      else {
        $current .= $char;
      }
    }

    // Don't forget the last token.
    if ($current !== '') {
      $tokens[] = $current;
    }

    return $tokens;
  }

}
