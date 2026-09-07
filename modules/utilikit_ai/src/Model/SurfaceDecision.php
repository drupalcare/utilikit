<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Model;

/**
 * Whether the chat bar appears on a node form, and why not when it does not.
 *
 * THE REASON IS THE POINT. A surface that silently fails to appear is the
 * defect class this project keeps hitting — and in this module it is named in
 * the brief as the one thing that must not be repeated: a control you can set
 * and watch do nothing. Six things must be true before the bar can render — a
 * permission, two modules, a setting, an assistant that still exists, and a
 * content type with somewhere to write — and not one of them is visible from
 * the node form.
 *
 * So the decision carries its own explanation and the settings form prints it.
 * "Nothing appears" stops being a mystery and becomes a sentence.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final readonly class SurfaceDecision {

  /**
   * Constructs a decision.
   *
   * @param bool $show
   *   Whether to render the chat bar.
   * @param string $assistant
   *   The assistant config entity id to use, when showing.
   * @param string $reason
   *   Why it is not showing. Empty when $show is TRUE.
   */
  private function __construct(
    public bool $show,
    public string $assistant = '',
    public string $reason = '',
  ) {}

  /**
   * The bar should appear, using this assistant.
   *
   * @param string $assistant
   *   Assistant config entity id.
   *
   * @return self
   *   The decision.
   */
  public static function show(string $assistant): self {
    assert($assistant !== '', 'Showing the bar requires an assistant to show.');

    return new self(TRUE, $assistant);
  }

  /**
   * The bar stays hidden, and this is what to fix.
   *
   * @param string $reason
   *   A complete sentence naming the missing precondition.
   *
   * @return self
   *   The decision.
   */
  public static function hide(string $reason): self {
    assert($reason !== '', 'A hidden surface must say what is missing.');

    return new self(FALSE, '', $reason);
  }

}
