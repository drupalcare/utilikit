<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\utilikit_ai\Model\ClassVerdict;

/**
 * Decides whether candidate classes will produce CSS on this site.
 *
 * @see \Drupal\utilikit_ai\Service\ClassValidator
 * @see docs/UTILIKIT-AI-BRIEF.md §1a
 */
interface ClassValidatorInterface {

  /**
   * Checks one class.
   *
   * @param string $class
   *   The class, with or without surrounding whitespace.
   *
   * @return \Drupal\utilikit_ai\Model\ClassVerdict
   *   Whether it works, and why not when it does not.
   */
  public function check(string $class): ClassVerdict;

  /**
   * Checks a whole space-separated class string.
   *
   * @param string $classes
   *   The classes.
   *
   * @return list<\Drupal\utilikit_ai\Model\ClassVerdict>
   *   One verdict per class, in order.
   */
  public function checkAll(string $classes): array;

  /**
   * Whether a class string carries the `utilikit` marker.
   *
   * Without it the engine never looks at the element, so every utility class
   * on it is inert. Measured in a browser 2026-09-05.
   *
   * @param string $classes
   *   The classes.
   *
   * @return bool
   *   TRUE when the marker is present.
   */
  public function hasMarker(string $classes): bool;

}
