<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

/**
 * Why a perfectly valid class still does nothing on this site.
 *
 * @see \Drupal\utilikit_ai\Service\SetupDoctor
 * @see docs/UTILIKIT-AI-BRIEF.md §1c
 */
interface SetupDoctorInterface {

  /**
   * The configured rendering mode.
   *
   * @return string
   *   `inline`, `static` or `head`.
   */
  public function renderingMode(): string;

  /**
   * Whether the current mode needs a class to be discovered first.
   *
   * @return bool
   *   TRUE in static and head mode, where the server generates CSS only for
   *   classes it already knows.
   */
  public function needsDiscovery(): bool;

  /**
   * Which entity types contribute their classes on save.
   *
   * @return array<string, bool>
   *   Entity type id => whether auto-update is on.
   */
  public function autoUpdate(): array;

  /**
   * Text formats whose filter will strip UtiliKit classes.
   *
   * @return array<string, string>
   *   Format id => label, for formats running filter_html without `class`.
   */
  public function formatsStrippingClasses(): array;

}
