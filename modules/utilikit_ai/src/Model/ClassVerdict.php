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
   * @param string $caveat
   *   What could not be checked, when the value's grammar is UtiliKit's own.
   *   Empty when the class was checked fully.
   */
  private function __construct(
    public string $class,
    public bool $valid,
    public string $reason = '',
    public string $suggestion = '',
    public string $caveat = '',
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
   * The class is well formed, but its value could not be fully checked.
   *
   * WHY THIS STATE EXISTS. Grid track lists — `uk-gc--repeat-3-1fr` — are
   * parsed by UtiliKit itself, and re-implementing that grammar here would be a
   * second opinion about it, which is the defect this whole module was built
   * after removing. But answering a plain "OK" is worse: MEASURED 2026-09-05,
   * `uk-gc--banana` produces `grid-template-columns: banana;` in the generated
   * stylesheet, which the browser discards. Saying "valid" there tells a model
   * the class works when it does nothing.
   *
   * "I could not check this" is the truthful answer, and it is more useful than
   * either a false pass or a false rejection.
   *
   * @param string $class
   *   The class.
   * @param string $caveat
   *   What could not be checked, and what to look at instead.
   *
   * @return self
   *   The verdict.
   */
  public static function unverified(string $class, string $caveat): self {
    assert($caveat !== '', 'An unverified class must say what could not be checked.');

    return new self($class, TRUE, '', '', $caveat);
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
      return $this->caveat === ''
        ? sprintf('  OK       %s', $this->class)
        : sprintf('  UNSURE   %s — %s', $this->class, $this->caveat);
    }

    $line = sprintf('  REJECTED %s — %s', $this->class, $this->reason);

    if ($this->suggestion !== '') {
      $line .= sprintf(' Use %s instead.', $this->suggestion);
    }

    return $line;
  }

}
