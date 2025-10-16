<?php

namespace Drupal\utilikit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\utilikit\Service\UtilikitRules;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the UtiliKit Admin Reference page.
 *
 * This controller generates a comprehensive reference page displaying all
 * available UtiliKit utility classes, their usage patterns, examples, and
 * interactive demonstrations. It organizes utility classes by category and
 * provides copy-to-clipboard functionality and live preview capabilities.
 */
class UtilikitReferenceController extends ControllerBase {

  /**
   * The UtiliKit rules service.
   *
   * @var \Drupal\utilikit\Service\UtilikitRules
   */
  protected UtilikitRules $rulesService;

  /**
   * Constructs a new UtilikitReferenceController object.
   *
   * @param \Drupal\utilikit\Service\UtilikitRules $rulesService
   *   The UtiliKit rules service for accessing utility class definitions.
   */
  public function __construct(UtilikitRules $rulesService) {
    $this->rulesService = $rulesService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
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
          ['data' => ['#markup' => '<code>uk-' . $prefix . '</code>']],
          ['data' => ['#markup' => '<code>' . $example . '</code>']],
          ['data' => ['#markup' => '<code>' . $this->getResponsiveExample($example) . '</code>']],
          $this->getValueType($rule),
          [
            'data' => [
              '#markup' => '<span title="' . htmlspecialchars($note_full) . '">' . htmlspecialchars($note_short) . '</span>',
            ],
          ],
        ];
      }

      $build[] = [
        '#markup' => '<h3>' . $group . '</h3>',
      ];

      $build[] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => ['class' => ['utilikit-admin-reference']],
      ];
    }

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
      $output .= '<button type="button" class="copy-group-btn" data-group="' . htmlspecialchars($groupName) . '">' . $this->t('Copy All') . '</button>';
      $output .= '</div>';
      $output .= '<div class="syntax-items">';

      foreach ($groupRules as $prefix => $rule) {
        $cssProperty = str_replace(['backgroundColor', 'borderWidth', 'borderRadius', 'borderStyle', 'borderColor',
          'maxWidth', 'minWidth', 'maxHeight', 'minHeight', 'fontSize', 'lineHeight',
          'fontWeight', 'letterSpacing', 'textAlign', 'zIndex', 'aspectRatio',
          'flexGrow', 'flexShrink', 'flexBasis', 'flexDirection', 'justifyContent',
          'alignItems', 'alignContent', 'flexWrap', 'gridTemplateColumns',
          'gridTemplateRows', 'gridColumn', 'gridRow', 'userSelect', 'backgroundSize',
        ],
                                  ['background-color', 'border-width', 'border-radius', 'border-style', 'border-color',
                                    'max-width', 'min-width', 'max-height', 'min-height', 'font-size', 'line-height',
                                    'font-weight', 'letter-spacing', 'text-align', 'z-index', 'aspect-ratio',
                                    'flex-grow', 'flex-shrink', 'flex-basis', 'flex-direction', 'justify-content',
                                    'align-items', 'align-content', 'flex-wrap', 'grid-template-columns',
                                    'grid-template-rows', 'grid-column', 'grid-row', 'user-select', 'background-size',
                                  ],
                                  $rule['css']);

        $output .= '<div class="syntax-item">';
        $output .= '<span class="property-name">' . $cssProperty . '</span>';
        $output .= '<span class="syntax">';
        $output .= '<span class="syntax-uk">uk</span>';
        $output .= '<span class="syntax-dash">-</span>';
        $output .= '<span class="syntax-prefix">' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '</span>';
        $output .= '<span class="syntax-dash">--</span>';
        $output .= '<span class="syntax-value">value</span>';
        $output .= '</span>';
        $output .= '</div>';
      }

      $output .= '</div></div>';
    }

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
        'fl' => 'left',
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
      $viewport = in_array($rule['css'], ['width', 'height', 'maxWidth',
        'maxHeight', 'minWidth', 'minHeight', 'fontSize',
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
      return 'Use 6-digit hex (ff0000) or 3-digit (f00). Add -50 for 50% opacity: uk-bg--ff0000-50';
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

}
