<?php

declare(strict_types=1);

namespace Drupal\utilikit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Html;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitRules;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the UtiliKit Admin Reference page.
 *
 * This controller generates a comprehensive reference page displaying all
 * available UtiliKit utility classes, their usage patterns, examples, and
 * interactive demonstrations. It organizes utility classes by category and
 * provides copy-to-clipboard functionality and live preview capabilities.
 *
 * @phpstan-consistent-constructor
 *   Drupal's create() factory returns `new static()`. PHPStan treats that as
 *   unsafe on a non-final class because a subclass could declare a different
 *   constructor. This class's constructor is the contract subclasses inherit,
 *   which is what the annotation asserts.
 */
class UtilikitReferenceController extends ControllerBase {

  /**
   * Constructs a new UtilikitReferenceController object.
   *
   * @param \Drupal\utilikit\Service\UtilikitRules $rulesService
   *   The UtiliKit rules service for accessing utility class definitions.
   */
  public function __construct(
    protected readonly UtilikitRules $rulesService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('utilikit.rules')
    );
  }

  /**
   * Builds the UtiliKit reference admin page.
   *
   * Creates a comprehensive reference page with utility class documentation,
   * grouped by category, with interactive examples and quick reference guide.
   *
   * @return array
   *   Render array for the UtiliKit reference page including cheatsheet,
   *   categorized utility class tables, and interactive components.
   */
  public function referencePage(): array {
    $rules = $this->rulesService->getRules();
    $groups = $this->groupRules($rules);

    // At the beginning of the build array in referencePage():
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['utilikit-reference-page']],
      '#title' => $this->t('UtiliKit - Utility Class Reference'),
      '#attached' => [
        'library' => ['utilikit/utilikit.reference', 'utilikit/utilikit.engine'],
      ],
      'description' => [
        '#markup' => '<p>' . $this->t('This reference page lists all available UtiliKit dynamic utility classes, their prefixes, and usage examples for your site.') . '</p>',
      ],
    ];

    // Add cheatsheet at the top.
    $build['cheatsheet'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['utilikit-cheatsheet']],
      'content' => [
        '#markup' => $this->buildCheatsheet($rules),
        '#allowed_tags' => ['div', 'span', 'h3', 'button', 'code'],
      ],
      '#prefix' => '<div class="out-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($groups as $group => $items) {
      $header = [
        $this->t('Property'),
        $this->t('Prefix'),
        $this->t('Example'),
        $this->t('Responsive'),
        $this->t('Value Type'),
        $this->t('Notes'),
      ];

      $rows = [];
      foreach ($items as [$prefix, $rule]) {
        $example = $this->getExample($prefix, $rule);
        $note_full = $this->getNotes($rule, $prefix);
        $note_short = $this->getShortNote($rule, $prefix);

        $rows[] = [
          $rule['css'],
          ['data' => ['#markup' => '<code>' . Html::escape('uk-' . $prefix) . '</code>']],
          ['data' => ['#markup' => '<code>' . Html::escape($example) . '</code>']],
          ['data' => ['#markup' => '<code>' . Html::escape($this->getResponsiveExample($example)) . '</code>']],
          $this->getValueType($rule),
          [
            'data' => [
              '#markup' => '<span title="' . Html::escape($note_full) . '">' . Html::escape($note_short) . '</span>',
            ],
          ],
        ];
      }

      $build[] = [
        '#markup' => '<h3>' . Html::escape($group) . '</h3>',
      ];

      $build[] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => ['class' => ['utilikit-admin-reference']],
      ];
    }

    // Admin reference page: max-age 0 (dynamic, non-performance-critical).
    $build['#cache'] = [
      'contexts' => ['user.permissions'],
      'tags' => [UtilikitConstants::CACHE_TAG_CONFIG],
      'max-age' => 0,
    ];

    return $build;
  }

  /**
   * Build the cheatsheet with color-coded syntax.
   *
   * Creates an interactive quick reference guide with organized utility
   * classes, copy functionality, and a live preview playground for testing
   * utility class combinations.
   *
   * @param array $rules
   *   Array of utility class rules from the rules service.
   *
   * @return string
   *   HTML markup for the interactive cheatsheet component.
   */
  protected function buildCheatsheet(array $rules): string {
    $output = '<div class="utilikit-cheatsheet">';
    $output .= '<h3>' . $this->t('Quick Reference Guide') . '</h3>';

    // Group rules by their group property.
    $groups = [];
    foreach ($rules as $prefix => $rule) {
      $group = $rule['group'] ?? 'Other';
      if (!isset($groups[$group])) {
        $groups[$group] = [];
      }
      $groups[$group][$prefix] = $rule;
    }

    // Define group icons.
    $groupIcons = [
      'Box Model' => '▢',
      'Sizing' => '↕',
      'Positioning' => '⊞',
      'Typography' => 'T',
      'Colors' => '●',
      'Layout' => '▦',
      'Flexbox' => '⇄',
      'Grid' => '▦',
      'Transform' => '↻',
      'Effects' => '✦',
    ];

    // Output each group.
    foreach ($groups as $groupName => $groupRules) {
      $output .= '<div class="syntax-group">';
      $output .= '<div class="syntax-group-title">';
      $output .= '<span class="syntax-group-icon">' . ($groupIcons[$groupName] ?? '•') . '</span>';
      $output .= $groupName;
      // Copy All button removed — now inside each tooltip-wrapper.
      $output .= '</div>';
      $output .= '<div class="syntax-items">';

      foreach ($groupRules as $prefix => $rule) {
        $cssProperty = $this->camelToDash($rule['css']);
        $example = $this->getExample($prefix, $rule);
        $valueType = $this->getValueType($rule);
        $notes = $this->getNotes($rule, $prefix);

        // Build data attributes for JS info card.
        $dataAttrs = ' data-prefix="' . Html::escape($prefix) . '"';
        $dataAttrs .= ' data-css="' . Html::escape($cssProperty) . '"';
        $dataAttrs .= ' data-example="' . Html::escape($example) . '"';
        $dataAttrs .= ' data-type="' . Html::escape($valueType) . '"';
        $dataAttrs .= ' data-notes="' . Html::escape($notes) . '"';
        if (!empty($rule['sides'])) {
          $dataAttrs .= ' data-sides="1"';
        }
        if (in_array($prefix, ['bg', 'tc'], TRUE)) {
          $dataAttrs .= ' data-pseudo="1"';
        }

        $output .= '<div class="syntax-item"' . $dataAttrs . '>';
        $output .= '<span class="property-name">' . Html::escape($cssProperty) . '</span>';
        $output .= '<span class="syntax">';
        $output .= '<span class="syntax-uk">uk</span>';
        $output .= '<span class="syntax-dash">-</span>';
        $output .= '<span class="syntax-prefix">' . Html::escape($prefix) . '</span>';
        $output .= '<span class="syntax-dash">--</span>';
        $output .= '<span class="syntax-value">value</span>';
        $output .= '</span>';
        $output .= '</div>';
      }

      $output .= '</div></div>';
    }

    $output .= '</div>';
    return $output;
  }

  /**
   * Groups rules by category for display.
   *
   * Organizes utility class rules into logical groups for better presentation
   * and sorts items within each group alphabetically by CSS property name.
   *
   * @param array $rules
   *   Array of utility class rules from the rules service.
   *
   * @return array
   *   Grouped and sorted rules organized by category.
   */
  protected function groupRules(array $rules): array {
    $groups = [];
    foreach ($rules as $prefix => $rule) {
      $group = $rule['group'] ?? 'Other';
      $groups[$group][] = [$prefix, $rule];
    }

    foreach ($groups as &$items) {
      usort($items, fn($a, $b) => strcmp($a[1]['css'], $b[1]['css']));
    }

    ksort($groups);
    return $groups;
  }

  /**
   * Generates example usage strings.
   *
   * Creates contextually appropriate examples for each utility class type,
   * demonstrating proper syntax and common use cases.
   *
   * @param string $prefix
   *   The utility class prefix (e.g., 'pd', 'mg', 'bg').
   * @param array $rule
   *   The rule definition containing type and configuration information.
   *
   * @return string
   *   Example utility class usage string (e.g., 'uk-pd--20').
   */
  protected function getExample(string $prefix, array $rule): string {
    // Color properties.
    if (!empty($rule['isColor'])) {
      if (in_array($prefix, ['bg', 'tc'], TRUE)) {
        return "uk-{$prefix}--ff0000, uk-{$prefix}--ff0000-h";
      }
      return "uk-{$prefix}--ff0000";
    }

    // Properties with sides.
    if (!empty($rule['sides'])) {
      return "uk-{$prefix}--t-20";
    }

    // Keyword properties.
    if (!empty($rule['isKeyword'])) {
      $examples = [
        'dp' => 'flex',
        'ps' => 'relative',
        'ta' => 'center',
        'ov' => 'hidden',
        'cu' => 'pointer',
        'fl' => 'inline-start',
        'cl' => 'both',
        'us' => 'none',
        'bs' => 'solid',
        'bz' => 'cover',
        'fd' => 'row',
        'jc' => 'center',
        'ai' => 'center',
        'ac' => 'center',
        'fx' => 'wrap',
      ];
      return "uk-{$prefix}--" . ($examples[$prefix] ?? 'value');
    }

    // Transform properties.
    if (!empty($rule['isTransform'])) {
      return $rule['isTransform'] === 'rotate'
        ? "uk-{$prefix}--45"
        : "uk-{$prefix}--150";
    }

    // Grid template properties.
    if (!empty($rule['isGridTrackList'])) {
      return $prefix === 'gc'
        ? "uk-gc--repeat-3-1fr"
        : "uk-gr--100px-auto-1fr";
    }

    // Range properties (grid positioning, aspect ratio)
    if (!empty($rule['isRange'])) {
      return $prefix === 'ar'
        ? "uk-ar--16-9"
        : "uk-{$prefix}--1-3";
    }

    // Opacity (0-100)
    if (!empty($rule['isOpacity'])) {
      return "uk-op--50";
    }

    // Integer properties (z-index, font-weight, order)
    if (!empty($rule['isInteger']) && empty($rule['isOpacity'])) {
      $examples = [
        'zi' => '10',
        'fw' => '700',
        'or' => '2',
      ];
      return "uk-{$prefix}--" . ($examples[$prefix] ?? '1');
    }

    // Decimal fixed (flex-grow, flex-shrink)
    if (!empty($rule['isDecimalFixed'])) {
      return "uk-{$prefix}--1d500";
    }

    // Numeric flexible (most common - px, %, em, rem)
    if (!empty($rule['isNumericFlexible'])) {
      // Show different unit examples.
      if (in_array($prefix, ['wd', 'ht', 'xw', 'xh', 'nw', 'nh'])) {
        // Show percentage.
        return "uk-{$prefix}--100pr";
      }
      if (in_array($prefix, ['fs', 'lh'])) {
        // Show rem.
        return "uk-{$prefix}--1d5rem";
      }
      if ($prefix === 'gp') {
        // Gap with two values.
        return "uk-gp--16-32";
      }
      // Default pixels.
      return "uk-{$prefix}--20";
    }

    return "uk-{$prefix}--20";
  }

  /**
   * Generates a responsive example string.
   *
   * Creates a responsive variant of the given utility class example by
   * adding a breakpoint prefix.
   *
   * @param string $example
   *   Base utility class example string.
   *
   * @return string
   *   Responsive variant with breakpoint prefix (e.g., 'uk-md-pd--20').
   */
  protected function getResponsiveExample(string $example): string {
    return preg_replace('/^uk-/', 'uk-md-', $example);
  }

  /**
   * Returns the value type for the rule.
   *
   * Determines and returns a human-readable description of the value type
   * accepted by a utility class rule.
   *
   * @param array $rule
   *   The rule definition containing type flags and configuration.
   *
   * @return string
   *   Human-readable value type description.
   */
  protected function getValueType(array $rule): string {
    if (!empty($rule['isColor'])) {
      return 'Hex color';
    }
    if (!empty($rule['isKeyword'])) {
      return 'CSS keyword';
    }
    if (!empty($rule['isOpacity'])) {
      return '0-100 (%)';
    }
    if (!empty($rule['isInteger'])) {
      return 'Integer';
    }
    if (!empty($rule['isDecimalFixed'])) {
      return 'Decimal number';
    }
    if (!empty($rule['isTransform'])) {
      return $rule['isTransform'] === 'rotate' ? 'Degrees' : 'Scale (%)';
    }
    if (!empty($rule['isGridTrackList'])) {
      return 'Grid track list';
    }
    if (!empty($rule['isRange'])) {
      return 'Range (start-end)';
    }
    if (!empty($rule['isNumericFlexible'])) {
      $viewport = in_array($rule['css'], ['inlineSize', 'blockSize',
        'maxInlineSize', 'maxBlockSize', 'minInlineSize', 'minBlockSize',
        'fontSize',
      ]);
      return $viewport ? 'px, %, em, rem, vh, vw' : 'px, %, em, rem';
    }
    return 'Value';
  }

  /**
   * Returns notes for the rule.
   *
   * Provides detailed usage notes and syntax information for specific
   * utility class types to help users understand proper usage patterns.
   *
   * @param array $rule
   *   The rule definition containing type flags and configuration.
   * @param string $prefix
   *   The utility class prefix for context-specific notes.
   *
   * @return string
   *   Detailed usage notes and syntax information.
   */
  protected function getNotes(array $rule, string $prefix): string {
    if (!empty($rule['isColor'])) {
      $note = 'Use 6-digit hex (ff0000) or 3-digit (f00). Add -50 for 50% opacity: uk-bg--ff0000-50';
      if (in_array($prefix, ['bg', 'tc'], TRUE)) {
        $note .= '. Pseudo-states (static/head mode): -h (hover), -f (focus), -a (active). Example: uk-' . $prefix . '--007bff-h';
      }
      return $note;
    }

    if (!empty($rule['sides'])) {
      return $prefix === 'br'
        ? 'Use t/r/b/l for specific corners (uk-br--t-10) or shorthand (uk-br--10-20-30-40)'
        : 'Use t/r/b/l for specific sides (uk-pd--t-20) or shorthand (uk-pd--10-20)';
    }

    if (!empty($rule['isNumericFlexible'])) {
      $units = 'Units: px (default), pr (%), em, rem';
      if (in_array($prefix, ['wd', 'ht', 'xw', 'xh', 'nw', 'nh', 'fs'])) {
        $units .= ', vh, vw';
      }
      if (!empty($rule['allowAuto'])) {
        $units .= ', auto';
      }
      return $units;
    }

    if (!empty($rule['isTransform'])) {
      return $rule['isTransform'] === 'rotate'
        ? 'Rotation in degrees (0-360)'
        : 'Scale as percentage (100 = 1x, 150 = 1.5x)';
    }

    if (!empty($rule['isGridTrackList'])) {
      return 'Complex grid syntax: repeat(), minmax(), fr units, auto-fit/auto-fill';
    }

    if (!empty($rule['isRange'])) {
      return $prefix === 'ar'
        ? 'Common ratios: 16-9, 4-3, 1-1, 21-9'
        : 'Grid line numbers: 1-3 (start at 1, end at 3)';
    }

    if (!empty($rule['isOpacity'])) {
      return 'Value 0-100 converts to 0-1 opacity';
    }

    if (!empty($rule['isInteger'])) {
      $notes = [
        'zi' => 'Can be negative (-1) or positive (9999)',
        'fw' => 'Font weights: 100-900 in steps of 100',
        'or' => 'Flex/grid item order, can be negative',
      ];
      return $notes[$prefix] ?? 'Integer values only';
    }

    if (!empty($rule['isDecimalFixed'])) {
      return 'Decimal values like 1, 1.5, 2.25';
    }

    if ($prefix === 'gp') {
      return 'Single value (uk-gp--20) or two values (uk-gp--20-40) for row/column gap';
    }

    return '';
  }

  /**
   * Returns short notes for the rule.
   *
   * Provides concise summary notes suitable for table display with limited
   * space, focusing on the most important usage information.
   *
   * @param array $rule
   *   The rule definition containing type flags and configuration.
   * @param string $prefix
   *   The utility class prefix for context-specific notes.
   *
   * @return string
   *   Short summary of usage notes.
   */
  protected function getShortNote(array $rule, string $prefix): string {
    if (!empty($rule['isColor'])) {
      if (in_array($prefix, ['bg', 'tc'], TRUE)) {
        return 'Hex + opacity + hover/focus/active';
      }
      return 'Hex + optional opacity';
    }
    if (!empty($rule['sides'])) {
      return 'Supports sides + shorthand';
    }
    if (!empty($rule['isNumericFlexible'])) {
      if (!empty($rule['allowAuto'])) {
        return 'px, %, em, rem, auto';
      }
      return 'Multiple units';
    }
    if (!empty($rule['isKeyword'])) {
      return 'CSS keywords';
    }
    if (!empty($rule['isTransform'])) {
      return 'Transform value';
    }
    if (!empty($rule['isGridTrackList'])) {
      return 'Grid syntax';
    }
    if (!empty($rule['isRange'])) {
      return 'Range format';
    }
    if (!empty($rule['isOpacity'])) {
      return '0-100 → 0-1';
    }
    if (!empty($rule['isInteger'])) {
      return 'Integer only';
    }
    if (!empty($rule['isDecimalFixed'])) {
      return 'Decimal values';
    }

    return '';
  }

  /**
   * Converts a camelCase CSS property name to dash-case.
   *
   * @param string $property
   *   The camelCase property name (e.g., 'backgroundColor').
   *
   * @return string
   *   The dash-case property name (e.g., 'background-color').
   */
  protected function camelToDash(string $property): string {
    return strtolower(preg_replace('/([A-Z])/', '-$1', $property));
  }

}
