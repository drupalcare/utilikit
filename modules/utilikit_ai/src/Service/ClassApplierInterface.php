<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

/**
 * Adds UtiliKit classes to elements inside a node's rich-text field.
 *
 * The only thing in this module that writes, and it only ever adds a class
 * attribute — never text, never structure.
 *
 * @see \Drupal\utilikit_ai\Service\ClassApplier
 * @see docs/UTILIKIT-AI-BRIEF.md §1d
 */
interface ClassApplierInterface {

  /**
   * Adds classes to every matching element, or refuses and explains why.
   *
   * Does not throw for ordinary refusals — a missing page, a format that
   * strips classes, an invalid class are all results rather than exceptions,
   * because each is something a person can act on.
   *
   * @param int|null $nid
   *   The node to change.
   * @param string|null $field
   *   The rich-text field, or NULL to use the node's first one.
   * @param string|null $tag
   *   The HTML tag whose elements should get the classes.
   * @param string|null $classes
   *   The classes to add, space separated. The `utilikit` marker is added
   *   automatically and need not be included.
   *
   * @return array{applied: bool, nid?: int, label?: string, field?: string, elements?: int, refusals: list<string>, notes: list<string>}
   *   What changed, or why nothing did.
   */
  public function apply(?int $nid, ?string $field, ?string $tag, ?string $classes): array;

}
