<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit_ai\Model\ClassVerdict;
use Drupal\utilikit_ai\Model\Utility;

/**
 * Decides whether a candidate class will actually produce CSS on this site.
 *
 * THE TWO MISTAKES A MODEL MAKES, and both are silent:
 *
 * 1. A prefix that does not exist. Fifty two-letter codes is exactly the
 *    vocabulary a model half-remembers, so it writes `uk-padding--16px` or
 *    `uk-p--16px` with complete confidence.
 * 2. A value of the wrong kind. `uk-fw--700` where a keyword is required,
 *    `#3b82f6` with the hash left on, `uk-dp--banana` from nowhere at all.
 *
 * Neither raises anything. The class sits in the markup looking correct and no
 * style appears — the defect this whole project keeps meeting.
 *
 * IT VALIDATES THE SHAPE WITH UTILIKIT'S OWN PATTERN, not a copy of it.
 * `UtilikitConstants::CLASS_VALIDATION_PATTERN` is the one declaration of what
 * a class may look like, and re-typing that regex here would be the fifth
 * duplication this module was built to stop.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class ClassValidator implements ClassValidatorInterface {

  /**
   * The marker class every UtiliKit element must carry.
   *
   * MEASURED in a browser 2026-09-05: the engine processes only
   * `document.querySelectorAll('.utilikit')`. An element with
   * `class="uk-pd--37px"` and no marker gets `padding-top: 0px`. A tool that
   * emits utility classes without it produces markup that looks right and does
   * nothing.
   */
  private const MARKER = 'utilikit';

  /**
   * Constructs the validator.
   *
   * @param \Drupal\utilikit_ai\Service\RuleCatalogueInterface $catalogue
   *   The utilities this site has.
   */
  public function __construct(
    private readonly RuleCatalogueInterface $catalogue,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function check(string $class): ClassVerdict {
    $class = trim($class);

    if ($class === '') {
      return ClassVerdict::invalid($class, 'An empty string is not a class.');
    }

    if ($class === self::MARKER) {
      return ClassVerdict::valid($class);
    }

    if (!str_starts_with($class, 'uk-')) {
      return ClassVerdict::invalid(
        $class,
        'Every UtiliKit utility class starts with "uk-".',
      );
    }

    // A leading hash on a colour fails the shape check, and "the shape is
    // wrong" is a poor answer to a mistake this specific. Name it instead.
    if (preg_match('/^uk-(?:(?:sm|md|lg|xl|xxl)-)?([a-z]{2,4})--#([0-9a-fA-F]{3,8})$/', $class, $hashed)) {
      return ClassVerdict::invalid(
        $class,
        'A colour is hex digits with no leading hash — 3b82f6, not #3b82f6.',
        sprintf('uk-%s--%s', $hashed[1], $hashed[2]),
      );
    }

    // Shape next, using UtiliKit's own pattern.
    if (!preg_match(UtilikitConstants::CLASS_VALIDATION_PATTERN, $class)) {
      return ClassVerdict::invalid(
        $class,
        'The shape is wrong. A class is uk-, an optional breakpoint, the '
        . 'two-letter prefix, then two hyphens and the value — for example '
        . 'uk-pd--16px or uk-md-pd--16px.',
      );
    }

    // Break it into breakpoint, prefix and value.
    if (!preg_match('/^uk-(?:(sm|md|lg|xl|xxl)-)?([a-z]{2,4})--(.+)$/', $class, $parts)) {
      return ClassVerdict::invalid($class, 'The class could not be read.');
    }

    $prefix = $parts[2];
    $value = $parts[3];
    $utility = $this->catalogue->get($prefix);

    if ($utility === NULL) {
      return ClassVerdict::invalid(
        $class,
        sprintf('There is no "%s" utility on this site.', $prefix),
        $this->nearestPrefix($prefix),
      );
    }

    // Directional forms — uk-pd--t-16 — only where the rule allows sides.
    if (preg_match('/^([trbl])-(.+)$/', $value, $side)) {
      if (!$utility->hasSides()) {
        return ClassVerdict::invalid(
          $class,
          sprintf('"%s" does not take a direction; %s applies to one property, not four sides.', $prefix, $utility->css),
        );
      }

      $value = $side[2];
    }

    if ($utility->isKeyword()) {
      return $this->checkKeyword($class, $utility->prefix, $value, $utility->keywords, $utility->shorthand);
    }

    return $this->checkValue($class, $utility, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function checkAll(string $classes): array {
    $out = [];

    foreach (preg_split('/\s+/', trim($classes)) ?: [] as $class) {
      if ($class !== '') {
        $out[] = $this->check($class);
      }
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function hasMarker(string $classes): bool {
    return in_array(self::MARKER, preg_split('/\s+/', trim($classes)) ?: [], TRUE);
  }

  /**
   * Checks a keyword value against the list this site allows.
   *
   * @param string $class
   *   The whole class, for the verdict.
   * @param string $prefix
   *   The utility prefix.
   * @param string $value
   *   The value, with any direction already stripped.
   * @param list<string> $keywords
   *   The accepted keywords.
   * @param array<string, string> $shorthand
   *   Shorthand => real CSS value.
   *
   * @return \Drupal\utilikit_ai\Model\ClassVerdict
   *   The verdict.
   */
  private function checkKeyword(string $class, string $prefix, string $value, array $keywords, array $shorthand): ClassVerdict {
    // Shorthand resolves before validation, exactly as both engines do.
    $resolved = $shorthand[$value] ?? $value;

    if ($keywords === []) {
      return ClassVerdict::invalid(
        $class,
        sprintf('"%s" takes a keyword but this site lists none for it — needs checking.', $prefix),
      );
    }

    if (in_array($resolved, $keywords, TRUE)) {
      return ClassVerdict::valid($class);
    }

    $suggestion = '';
    $near = $this->nearest($value, array_merge($keywords, array_keys($shorthand)));

    if ($near !== '') {
      $suggestion = sprintf('uk-%s--%s', $prefix, $near);
    }

    return ClassVerdict::invalid(
      $class,
      sprintf(
        '"%s" is not a value %s accepts here. It takes one of: %s.',
        $value,
        $prefix,
        implode(', ', array_slice($keywords, 0, 12)) . (count($keywords) > 12 ? ' …' : ''),
      ),
      $suggestion,
    );
  }

  /**
   * Checks a non-keyword value against the rule's flags.
   *
   * @param string $class
   *   The whole class.
   * @param \Drupal\utilikit_ai\Model\Utility $utility
   *   The rule.
   * @param string $value
   *   The value.
   *
   * @return \Drupal\utilikit_ai\Model\ClassVerdict
   *   The verdict.
   */
  private function checkValue(string $class, Utility $utility, string $value): ClassVerdict {
    $flags = $utility->flags;

    if ($value === 'auto') {
      return empty($flags['allowAuto'])
        ? ClassVerdict::invalid(
            $class,
            sprintf('%s does not accept auto in CSS.', $utility->css),
          )
        : ClassVerdict::valid($class);
    }

    if (!empty($flags['isColor'])) {
      return preg_match('/^[0-9a-fA-F]{3,8}$/', $value)
        ? ClassVerdict::valid($class)
        : ClassVerdict::invalid(
            $class,
            'A colour is hex digits with no leading hash — 3b82f6, not #3b82f6.',
            str_starts_with($value, '#')
              ? sprintf('uk-%s--%s', $utility->prefix, ltrim($value, '#'))
              : '',
          );
    }

    if (!empty($flags['isOpacity'])) {
      return preg_match('/^\d{1,3}$/', $value) && (int) $value <= 100
        ? ClassVerdict::valid($class)
        : ClassVerdict::invalid($class, 'Opacity is a whole number from 0 to 100.');
    }

    if (!empty($flags['isInteger'])) {
      return preg_match('/^-?\d+$/', $value)
        ? ClassVerdict::valid($class)
        : ClassVerdict::invalid($class, sprintf('%s takes a whole number.', $utility->css));
    }

    if (!empty($flags['isRange'])) {
      return preg_match('/^-?\d+--?\d+$/', $value)
        ? ClassVerdict::valid($class)
        : ClassVerdict::invalid($class, 'A range is two numbers joined by a hyphen, such as 1-3.');
    }

    if (!empty($flags['isNumericFlexible']) || !empty($flags['isDecimalFixed']) || !empty($flags['isTransform'])) {
      // `d` stands in for the decimal point: 3d75rem is 3.75rem.
      return preg_match('/^-?\d+(d\d+)?[a-z%]*$/i', $value)
        ? ClassVerdict::valid($class)
        : ClassVerdict::invalid(
            $class,
            'A number, optionally with a unit, using d for a decimal point — 16px, 3d75rem, 87vw.',
          );
    }

    if (!empty($flags['isGridTrackList'])) {
      // UtiliKit parses this grammar itself and re-implementing it here would
      // be a second opinion about it. But a plain "valid" would be a false
      // pass: measured 2026-09-05, `uk-gc--banana` yields
      // `grid-template-columns: banana;`, which the browser discards.
      return ClassVerdict::unverified(
        $class,
        'the grid track list is not checked here; it should look like '
        . 'repeat-3-1fr or minmax-100px-1fr, and anything else silently '
        . 'produces no layout',
      );
    }

    // Anything with no flag describing its values. Saying "valid" would be a
    // guess; saying so is not.
    return ClassVerdict::unverified(
      $class,
      sprintf('no rule flag describes what %s accepts, so its value was not checked', $utility->prefix),
    );
  }

  /**
   * The nearest real prefix to one that does not exist.
   *
   * @param string $wanted
   *   What was written.
   *
   * @return string
   *   A suggested class stem, or '' when nothing is close.
   */
  private function nearestPrefix(string $wanted): string {
    $near = $this->nearest($wanted, $this->catalogue->prefixes());

    return $near === '' ? '' : sprintf('uk-%s--…', $near);
  }

  /**
   * The single closest candidate, or '' when none is close enough.
   *
   * Deliberately conservative: one suggestion or none. Offering three guesses
   * is not help, and a wrong single guess a model then acts on is worse than
   * no guess at all.
   *
   * @param string $wanted
   *   The value written.
   * @param list<string> $candidates
   *   What exists.
   *
   * @return string
   *   The closest candidate, or ''.
   */
  private function nearest(string $wanted, array $candidates): string {
    $wanted = strtolower($wanted);
    $best = '';
    $bestDistance = PHP_INT_MAX;
    $ties = 0;

    foreach ($candidates as $candidate) {
      $distance = levenshtein($wanted, strtolower($candidate));

      if ($distance < $bestDistance) {
        $bestDistance = $distance;
        $best = $candidate;
        $ties = 1;
      }
      elseif ($distance === $bestDistance) {
        $ties++;
      }
    }

    // One edit for short codes, two for longer words. Beyond that it is a
    // different value and a suggestion would be noise.
    $limit = strlen($wanted) <= 4 ? 1 : 2;

    if ($bestDistance > $limit) {
      return '';
    }

    // AND THE WINNER HAS TO BE THE ONLY WINNER. The prefixes are two letters
    // wide, so "one edit away" is not distinctive: cr is one edit from ar, br,
    // gr and several more, and returning the first of those means confidently
    // pointing at aspectRatio when someone typed a colour. MEASURED — that is
    // exactly what it did before this guard. A tie means we do not know, and
    // saying nothing is the honest answer.
    return $ties === 1 ? $best : '';
  }

}
