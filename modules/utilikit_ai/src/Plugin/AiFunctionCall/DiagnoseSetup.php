<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\utilikit_ai\Service\SetupDoctorInterface;
use Drupal\utilikit_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Says why a valid UtiliKit class is still not doing anything.
 *
 * THE THREE CAUSES ARE INDISTINGUISHABLE FROM THE PAGE, which is exactly why
 * this is a tool rather than something a model can reason about. An element
 * that ignored its padding looks identical whether the rendering mode has not
 * discovered the class yet, the entity type does not contribute classes on
 * save, or a text filter removed the attribute before it ever reached the
 * browser. Each has a different fix, and a model asked "why isn't it working?"
 * will offer the most common one and be wrong most of the time.
 *
 * This answers about the SITE, not about a class — use `utilikit_check_classes`
 * when the class itself might be wrong. Between them they cover both halves of
 * "nothing happened".
 *
 * Reads only.
 *
 * @package Drupal\utilikit_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'utilikit:diagnose_setup',
  function_name: 'utilikit_diagnose_setup',
  name: 'Diagnose why UtiliKit classes are not applying',
  description: 'Explains why a correct UtiliKit class might still do nothing on this site: the rendering mode has not discovered it yet, the entity type does not contribute classes when saved, or a text format strips the class attribute. These look identical from the page and each has a different fix, so check here rather than guessing. Reads only; it changes no settings.',
  group: 'utilikit',
  module_dependencies: ['utilikit'],
  context_definitions: [],
)]
final class DiagnoseSetup extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Reads the three causes.
   */
  protected SetupDoctorInterface $doctor;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
    );
    $instance->doctor = $container->get(SetupDoctorInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $mode = $this->doctor->renderingMode();
    $lines = [sprintf('UtiliKit is in %s mode on this site.', $mode), ''];
    $problems = 0;

    // 1 · The mode.
    if ($this->doctor->needsDiscovery()) {
      $problems++;
      $lines[] = sprintf(
        'DISCOVERY REQUIRED. In %s mode the server generates CSS only for classes it '
        . 'already knows about, so a brand-new class does nothing until the content is '
        . 'scanned and the CSS rebuilt. Save the content again, or run "drush utilikit:scan" '
        . 'followed by "drush utilikit:generate".',
        $mode,
      );
    }
    else {
      $lines[] = 'The mode is fine: inline mode styles whatever it finds on the page, so a '
        . 'new class works immediately.';
    }

    // 2 · Auto-update, which only matters when discovery is required.
    $autoUpdate = $this->doctor->autoUpdate();
    $off = array_keys(array_filter($autoUpdate, static fn(bool $on): bool => !$on));

    if ($off !== [] && $this->doctor->needsDiscovery()) {
      $problems++;
      $lines[] = sprintf(
        'AUTO-UPDATE IS OFF for: %s. Content of those types does not contribute its classes '
        . 'when saved, so classes added there stay unknown and produce no CSS in this mode. '
        . 'Turn it on at Configuration › UtiliKit, or regenerate manually after editing.',
        implode(', ', $off),
      );
    }
    elseif ($off !== []) {
      $lines[] = sprintf(
        'Auto-update is off for %s, which does not matter in inline mode but would if the '
        . 'site moved to static or head.',
        implode(', ', $off),
      );
    }
    else {
      $lines[] = 'Auto-update is on for every entity type UtiliKit watches.';
    }

    // 3 · The filters, which matter in every mode.
    $stripping = $this->doctor->formatsStrippingClasses();

    if ($stripping !== []) {
      $problems++;
      $lines[] = sprintf(
        'CLASSES WILL BE STRIPPED by these text formats: %s. They run "Limit allowed HTML tags" '
        . 'without permitting a class attribute, so a uk- class typed into a rich-text field is '
        . 'removed when the content is saved — with no error shown anywhere. Allow class on the '
        . 'tags you need, or use a format that does.',
        implode(', ', array_map(
          static fn(string $id, string $label): string => sprintf('%s (%s)', $label, $id),
          array_keys($stripping),
          $stripping,
        )),
      );
    }
    else {
      // Not "no format strips classes". filter_html allows attributes per tag,
      // so a format permitting class on <a> but not <div> passes this check
      // and still drops the class from a div. Saying more than was checked is
      // the false all-clear this module exists to avoid.
      $lines[] = 'No text format refuses class attributes outright. Note this check is coarse: '
        . 'a format can allow class on some tags and strip it from others, so if a class '
        . 'disappears from one element type in particular, look at that format\'s allowed HTML.';
    }

    $lines[] = '';
    $lines[] = 'And whatever else is true: UtiliKit only styles elements carrying the "utilikit" '
      . 'class. A uk- class on an element without it does nothing, in every mode.';
    $lines[] = '';
    $lines[] = $problems === 0
      // Saying this explicitly matters: a list of "fine" lines with no verdict
      // invites the reader to keep hunting for a problem that is not here.
      ? 'Nothing in this site\'s configuration explains a class not applying. If one still '
        . 'does not work, check the class itself with utilikit_check_classes, and check that '
        . 'the element carries the "utilikit" class.'
      : sprintf('%d thing(s) above will stop a valid class from applying. Fix those first.', $problems);

    $this->setOutput(implode("\n", $lines));
  }

}
