<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Model;

/**
 * What became of one candidate class.
 *
 * WHY IT CARRIES A REASON AND A SUGGESTION. "Invalid" is useless to a model:
 * it will try again with something equally wrong. The failures here are
 * specific and each has an obvious repair — an unknown prefix usually means a
 * near-miss of a real one, a bad keyword means the value came from CSS rather
 * than from this site's list, a missing marker means the class was written
 * without `utilikit`. Saying which, and what to write instead, is the whole
 * value of the check.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final readonly class ClassVerdict {

  /**
   * Constructs a verdict.
   *
   * @param string $class
   *   The class as it was given.
   * @param bool $valid
   *   Whether it will produce CSS on this site.
   * @param string $reason
   *   Why not, as a complete sentence. Empty when valid.
   * @param string $suggestion
   *   A class to use instead, when one is obvious. Empty otherwise.
   */
  private function __construct(
    public string $class,
    public bool $valid,
    public string $reason = '',
    public string $suggestion = '',
  ) {}

  /**
   * The class is usable.
   *
   * @param string $class
   *   The class.
   *
   * @return self
   *   The verdict.
   */
  public static function valid(string $class): self {
    return new self($class, TRUE);
  }

  /**
   * The class will not work, and this is why.
   *
   * @param string $class
   *   The class.
   * @param string $reason
   *   A complete sentence naming the problem.
   * @param string $suggestion
   *   A replacement, when one is obvious.
   *
   * @return self
   *   The verdict.
   */
  public static function invalid(string $class, string $reason, string $suggestion = ''): self {
    assert($reason !== '', 'An invalid class must say what is wrong with it.');

    return new self($class, FALSE, $reason, $suggestion);
  }

  /**
   * The verdict as one readable line.
   *
   * @return string
   *   A line suitable for a tool's output.
   */
  public function line(): string {
    if ($this->valid) {
      return sprintf('  OK       %s', $this->class);
    }

    $line = sprintf('  REJECTED %s — %s', $this->class, $this->reason);

    if ($this->suggestion !== '') {
      $line .= sprintf(' Use %s instead.', $this->suggestion);
    }

    return $line;
  }

}
