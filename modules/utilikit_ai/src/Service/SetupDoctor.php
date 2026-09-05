<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Why a perfectly valid class still does nothing on this site.
 *
 * THREE CAUSES, none of them visible from the page, all measured on
 * 2026-09-05:
 *
 * 1. THE MODE NEEDS DISCOVERY. In `inline` the engine reads the DOM and styles
 *    whatever it finds, so a class works the moment it is on the page. In
 *    `static` and `head` the server generates CSS only for classes it already
 *    knows, so a brand-new class does nothing until content is scanned and the
 *    CSS rebuilt.
 * 2. AUTO-UPDATE IS OFF FOR THAT ENTITY TYPE. Discovery runs in
 *    hook_entity_presave for node, block_content and paragraph, each gated by
 *    its own setting. Off means saved content never contributes its classes.
 * 3. A TEXT FILTER STRIPS THE CLASS. The class never reaches the page at all,
 *    and nothing anywhere reports it.
 *
 * A person looking at a blank-looking element cannot tell these apart, and a
 * model asked "why isn't my class working?" will offer the most common cause
 * and be wrong most of the time. Reads only; changes nothing.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class SetupDoctor implements SetupDoctorInterface {

  /**
   * Constructs the doctor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads the rendering mode and the auto-update settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Lists the text formats.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function renderingMode(): string {
    $mode = $this->configFactory->get('utilikit.settings')->get('rendering_mode');

    return is_string($mode) && $mode !== '' ? $mode : 'inline';
  }

  /**
   * {@inheritdoc}
   */
  public function needsDiscovery(): bool {
    return in_array($this->renderingMode(), ['static', 'head'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function autoUpdate(): array {
    $config = $this->configFactory->get('utilikit.settings');
    $out = [];

    foreach (UtilikitConstants::AUTO_UPDATE_ENTITY_TYPES as $entityType => $settingKey) {
      $out[(string) $entityType] = (bool) $config->get($settingKey);
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function formatsStrippingClasses(): array {
    $stripping = [];

    try {
      $formats = $this->entityTypeManager->getStorage('filter_format')->loadMultiple();
    }
    catch (\Throwable) {
      // The filter module need not be installed. That is not a fault, and
      // guessing at an answer would be worse than saying nothing.
      return [];
    }

    foreach ($formats as $format) {
      if (!method_exists($format, 'filters') || !method_exists($format, 'label')) {
        continue;
      }

      $filters = $format->filters();

      if (!method_exists($filters, 'has') || !$filters->has('filter_html')) {
        continue;
      }

      $filter = $filters->get('filter_html');

      if (!is_object($filter) || empty($filter->status)) {
        continue;
      }

      $settings = $filter->settings ?? [];
      $allowed = is_array($settings) ? ($settings['allowed_html'] ?? '') : '';
      $allowed = is_string($allowed) ? $allowed : '';

      // A DELIBERATELY COARSE TEST, and its limits are stated rather than
      // hidden. filter_html permits attributes per TAG, so a format may allow
      // `class` on <a> and strip it from <div>. Reproducing that matching here
      // would mean re-implementing core's HTMLRestrictions, and getting it
      // subtly wrong would be worse than not answering.
      //
      // So: no mention of `class` at all is a certain strip and is reported.
      // Any mention is treated as "not certainly stripping" — which can be a
      // false all-clear for the tag you happen to use. The tool says so in its
      // output rather than implying the check is complete.
      if (!str_contains($allowed, 'class')) {
        $stripping[(string) $format->id()] = (string) $format->label();
      }
    }

    return $stripping;
  }

}
