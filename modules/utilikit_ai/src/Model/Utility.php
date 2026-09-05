<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Model;

/**
 * One utility rule, with everything needed to explain or validate it.
 *
 * WHY A VALUE OBJECT AND NOT THE RAW ARRAY. `getRules()` returns
 * `['css' => 'padding', 'sides' => TRUE, …]` — flags whose meaning lives in a
 * docblock. A model handed that array will guess at what `isNumericFlexible`
 * permits, and guessing is the failure this module exists to prevent. Here the
 * flags are turned into a sentence and a yes/no answer, once, in the place that
 * knows the rules.
 *
 * IT HOLDS NO OPINION OF ITS OWN. Everything comes from
 * UtilikitRules::getRules(), ::getAllowedKeywords() and ::getKeywordShorthand()
 * — the one declaration each. Three separate copies of the rule data, and two
 * of the keyword data, were removed from this project on 2026-09-05 precisely
 * so that nothing downstream would need its own.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final readonly class Utility {

  /**
   * Constructs a utility.
   *
   * @param string $prefix
   *   The two-to-four letter code, e.g. `pd`.
   * @param string $css
   *   The CSS property it sets, camelCase as UtiliKit stores it.
   * @param string $group
   *   The functional group, e.g. `Box Model`.
   * @param array<string, mixed> $flags
   *   The rule's flags, minus `css` and `group`.
   * @param list<string> $keywords
   *   Accepted keyword values, empty unless this is a keyword rule.
   * @param array<string, string> $shorthand
   *   Shorthand => real CSS value, e.g. `between` => `space-between`.
   */
  public function __construct(
    public string $prefix,
    public string $css,
    public string $group,
    public array $flags,
    public array $keywords = [],
    public array $shorthand = [],
  ) {}

  /**
   * Whether this rule takes a fixed set of keywords.
   *
   * @return bool
   *   TRUE when only the listed keywords are accepted.
   */
  public function isKeyword(): bool {
    return !empty($this->flags['isKeyword']);
  }

  /**
   * Whether this rule accepts directional forms such as `uk-pd--t-16`.
   *
   * @return bool
   *   TRUE when sides are supported.
   */
  public function hasSides(): bool {
    return !empty($this->flags['sides']);
  }

  /**
   * What this utility accepts, as a sentence a person can act on.
   *
   * The flags are the value grammar and they are what a model gets wrong:
   * offering `uk-fw--700` where a keyword is required, or `#3b82f6` where the
   * hash must be dropped. Saying it plainly is most of the job.
   *
   * @return string
   *   A description of the accepted values.
   */
  public function acceptedValues(): string {
    if ($this->isKeyword()) {
      return $this->keywords === []
        ? 'A keyword, though this site lists none for it.'
        : sprintf('One of: %s.', implode(', ', $this->keywords));
    }

    $parts = [];

    if (!empty($this->flags['isColor'])) {
      $parts[] = 'a hex colour with no leading hash, such as 3b82f6';
    }

    if (!empty($this->flags['isOpacity'])) {
      $parts[] = 'a whole number from 0 to 100, converted to a 0–1 opacity';
    }

    if (!empty($this->flags['isInteger'])) {
      $parts[] = 'a whole number';
    }

    if (!empty($this->flags['isDecimalFixed'])) {
      $parts[] = 'a decimal, written with d for the point — 1d5 means 1.5';
    }

    if (!empty($this->flags['isNumericFlexible'])) {
      $parts[] = 'a number with any CSS unit, such as 16px, 3d75rem or 87vw';
    }

    if (!empty($this->flags['isGridTrackList'])) {
      $parts[] = 'a grid track list, such as repeat-3-1fr or minmax-100px-1fr';
    }

    if (!empty($this->flags['isRange'])) {
      $parts[] = 'a start-end pair, such as 1-3 for grid-column 1 / 3';
    }

    if (!empty($this->flags['isTransform'])) {
      $parts[] = sprintf('a %s value', (string) $this->flags['isTransform']);
    }

    if (!empty($this->flags['allowAuto'])) {
      $parts[] = 'the keyword auto';
    }

    if ($parts === []) {
      // Better to say so than to invent a description.
      return 'Not described by any flag on this rule — needs checking.';
    }

    return ucfirst(implode('; or ', $parts)) . '.';
  }

  /**
   * An example class a person could copy.
   *
   * @return string
   *   A complete, valid class including the marker.
   */
  public function example(): string {
    if ($this->isKeyword() && $this->keywords !== []) {
      return sprintf('utilikit uk-%s--%s', $this->prefix, $this->keywords[0]);
    }

    $value = match (TRUE) {
      !empty($this->flags['isColor']) => '3b82f6',
      !empty($this->flags['isOpacity']) => '50',
      !empty($this->flags['isRange']) => '1-3',
      !empty($this->flags['isGridTrackList']) => 'repeat-3-1fr',
      !empty($this->flags['isInteger']) => '2',
      !empty($this->flags['isDecimalFixed']) => '1d5',
      !empty($this->flags['isTransform']) => '90',
      default => '16px',
    };

    return sprintf('utilikit uk-%s--%s', $this->prefix, $value);
  }

}
