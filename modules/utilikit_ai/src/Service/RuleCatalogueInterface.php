<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\utilikit_ai\Model\Utility;

/**
 * Every utility this site has, in a shape worth explaining.
 *
 * @see \Drupal\utilikit_ai\Service\RuleCatalogue
 * @see docs/UTILIKIT-AI-BRIEF.md §1a
 */
interface RuleCatalogueInterface {

  /**
   * Every utility, keyed by prefix and sorted.
   *
   * @return array<string, \Drupal\utilikit_ai\Model\Utility>
   *   Prefix => utility.
   */
  public function all(): array;

  /**
   * One utility by its prefix.
   *
   * @param string $prefix
   *   The prefix, case and whitespace insensitive.
   *
   * @return \Drupal\utilikit_ai\Model\Utility|null
   *   The utility, or NULL when this site has no such rule.
   */
  public function get(string $prefix): ?Utility;

  /**
   * Every utility, grouped as UtiliKit groups them.
   *
   * @return array<string, list<\Drupal\utilikit_ai\Model\Utility>>
   *   Group name => utilities.
   */
  public function byGroup(): array;

  /**
   * Every prefix this site has.
   *
   * @return list<string>
   *   The prefixes, sorted.
   */
  public function prefixes(): array;

}
