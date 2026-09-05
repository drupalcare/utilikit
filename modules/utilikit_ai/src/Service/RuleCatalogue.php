<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\utilikit\Service\UtilikitRules;
use Drupal\utilikit_ai\Model\Utility;

/**
 * Every utility this site has, in a shape worth explaining.
 *
 * READS THE ONE DECLARATION AND NOTHING ELSE. `UtilikitRules::getRules()`,
 * `::getAllowedKeywords()` and `::getKeywordShorthand()`. This module adds no
 * list of its own, and that is deliberate: on 2026-09-05 the rule data was
 * found declared four times in this project and the keyword data four times
 * again, already disagreeing — `uk-wd--auto` rendered in one mode and produced
 * no CSS in the others, `uk-jc--between` likewise. A fifth copy living here
 * would have been the same bug wearing this module's name.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class RuleCatalogue implements RuleCatalogueInterface {

  /**
   * The utilities, built once per request.
   *
   * @var array<string, \Drupal\utilikit_ai\Model\Utility>|null
   */
  private ?array $utilities = NULL;

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    if ($this->utilities !== NULL) {
      return $this->utilities;
    }

    $keywords = UtilikitRules::getAllowedKeywords();
    $shorthand = UtilikitRules::getKeywordShorthand();
    $out = [];

    foreach (UtilikitRules::getRules() as $prefix => $rule) {
      // A rule is `mixed` to the type system. Indexing into it unchecked is
      // the assumption this module refuses to make about a site.
      if (!is_array($rule)) {
        continue;
      }

      $css = $rule['css'] ?? NULL;

      if (!is_string($css) || $css === '') {
        continue;
      }

      $group = $rule['group'] ?? '';
      $flags = array_diff_key($rule, ['css' => 1, 'group' => 1]);

      $out[(string) $prefix] = new Utility(
        (string) $prefix,
        $css,
        is_string($group) ? $group : '',
        $flags,
        $this->stringList($keywords[$css] ?? []),
        $this->stringMap($shorthand[$css] ?? []),
      );
    }

    ksort($out);

    return $this->utilities = $out;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $prefix): ?Utility {
    return $this->all()[strtolower(trim($prefix))] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function byGroup(): array {
    $out = [];

    foreach ($this->all() as $utility) {
      $out[$utility->group ?: 'Other'][] = $utility;
    }

    ksort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function prefixes(): array {
    return array_keys($this->all());
  }

  /**
   * A mixed value as a list of non-empty strings.
   *
   * @param mixed $value
   *   The value.
   *
   * @return list<string>
   *   The strings it contained, in order.
   */
  private function stringList(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $out = [];

    foreach ($value as $item) {
      if (is_string($item) && $item !== '') {
        $out[] = $item;
      }
    }

    return $out;
  }

  /**
   * A mixed value as a string-to-string map.
   *
   * @param mixed $value
   *   The value.
   *
   * @return array<string, string>
   *   The pairs it contained.
   */
  private function stringMap(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $out = [];

    foreach ($value as $key => $item) {
      if (is_string($key) && is_string($item)) {
        $out[$key] = $item;
      }
    }

    return $out;
  }

}
