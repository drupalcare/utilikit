<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\utilikit_ai\Model\SurfaceDecision;

/**
 * Decides whether the node-form chat bar appears, and builds it.
 *
 * @see \Drupal\utilikit_ai\Service\ChatSurface
 * @see docs/UTILIKIT-AI-BRIEF.md §1
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
interface ChatSurfaceInterface {

  /**
   * The permission that gates the bar.
   *
   * Separate from the node access the writer checks on every call. This one
   * grants a surface; that one grants the ability to act.
   */
  public const string PERMISSION = 'use utilikit ai assistant';

  /**
   * Whether to show the bar, and what is missing when not.
   *
   * Separate from build() so the answer can be printed on the settings page.
   * Someone who turned this on and sees nothing needs the reason, and the node
   * form is the one place that cannot give it to them.
   *
   * @param string|null $contentType
   *   The node bundle whose form is being built, when there is one. A content
   *   type with no formatted-text field has nowhere for `apply_classes` to
   *   write, so the bar is pointless there — passing NULL skips that check,
   *   for callers such as the settings page that are asking about the site
   *   rather than about one form.
   *
   * @return \Drupal\utilikit_ai\Model\SurfaceDecision
   *   The decision, carrying the reason when hidden.
   */
  public function decide(?string $contentType = NULL): SurfaceDecision;

  /**
   * The render array for the chat bar.
   *
   * @param string|null $contentType
   *   The node bundle whose form is being built.
   *
   * @return array<string, mixed>
   *   A render array, or an empty array when the bar should not appear.
   */
  public function build(?string $contentType = NULL): array;

  /**
   * The content types this module can write into, keyed by machine name.
   *
   * Read from the same field-type list the writer uses, so the bar can never
   * be offered where `apply_classes` would have nowhere to put anything.
   *
   * @return array<string, string>
   *   Bundle machine name => label.
   */
  public function writableContentTypes(): array;

}
