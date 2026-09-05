<?php

declare(strict_types=1);

namespace Drupal\utilikit_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Adds UtiliKit classes to elements inside a node's rich-text field.
 *
 * THE ONLY THING IN THIS MODULE THAT WRITES, and it only ever ADDS a class
 * attribute. It never rewrites text, never removes an element, never replaces
 * a field wholesale.
 *
 * THE RULES, none of them configurable:
 *
 * 1. A NEW REVISION every time, so the previous version is one click away.
 *    Anything a machine changes in someone's content must be reversible by
 *    someone who did not watch it happen.
 * 2. IT REFUSES WHEN THE TEXT FORMAT WOULD STRIP THE CLASS. Measured on
 *    sts.local 2026-09-05: Basic HTML and Restricted HTML both drop class
 *    attributes, so the save would appear to succeed and the classes would be
 *    gone. Writing something that is silently discarded is worse than
 *    refusing, because it looks like it worked.
 * 3. EVERY CLASS IS VALIDATED FIRST, and one bad class refuses the whole
 *    request. A partial application leaves content in a state nobody asked
 *    for and nobody can see.
 * 4. THE `utilikit` MARKER IS ALWAYS ADDED. Without it the engine never looks
 *    at the element and every class on it is inert — measured in a browser.
 * 5. IT RE-CHECKS EVERY PRECONDITION ITSELF rather than trusting that
 *    `check_classes` or `diagnose_setup` was called, because a model can skip
 *    either. That redundancy is the safety model.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class ClassApplier implements ClassApplierInterface {

  /**
   * The marker class UtiliKit requires on every styled element.
   */
  private const MARKER = 'utilikit';

  /**
   * Constructs the applier.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads and saves the node.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Checked for update access, so the tool cannot exceed what the person
   *   operating it may do by hand.
   * @param \Drupal\utilikit_ai\Service\ClassValidatorInterface $validator
   *   Rule 3 — every class is checked before anything is written.
   * @param \Drupal\utilikit_ai\Service\SetupDoctorInterface $doctor
   *   Rule 2 — refuses where the format would strip the class.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly ClassValidatorInterface $validator,
    private readonly SetupDoctorInterface $doctor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function apply(?int $nid, ?string $field, ?string $tag, ?string $classes): array {
    $refusals = [];
    $classes = trim((string) $classes);
    $tag = strtolower(trim((string) $tag));

    // 5 · Every precondition, here, every time.
    if ($nid === NULL || $nid <= 0) {
      $refusals[] = 'Which page should this apply to? Give the node id.';
    }

    if ($tag === '') {
      $refusals[] = 'Which elements should get the classes? Give an HTML tag such as p, div, h2 or li.';
    }
    elseif (!preg_match('/^[a-z][a-z0-9]{0,9}$/', $tag)) {
      $refusals[] = sprintf('"%s" is not an HTML tag name.', $tag);
    }

    if ($classes === '') {
      $refusals[] = 'Which classes? For example "uk-pd--16px uk-bg--3b82f6".';
    }

    if ($refusals !== []) {
      return $this->refuse($refusals);
    }

    // 3 · Validate before touching anything.
    $bad = [];

    foreach ($this->validator->checkAll($classes) as $verdict) {
      if (!$verdict->valid) {
        $bad[] = $verdict->line();
      }
    }

    if ($bad !== []) {
      return $this->refuse(array_merge(
        ['These classes would produce no CSS, so nothing was written:'],
        $bad,
      ));
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if (!$node instanceof NodeInterface) {
      return $this->refuse([sprintf('There is no page with id %d on this site.', $nid)]);
    }

    if (!$node->access('update', $this->currentUser)) {
      return $this->refuse([sprintf('You do not have permission to edit "%s".', $node->label())]);
    }

    $field = $this->resolveField($node, $field);

    if ($field === NULL) {
      return $this->refuse([sprintf(
        '"%s" has no rich-text field to change. This tool edits formatted text, not plain fields.',
        $node->label(),
        ),
      ]);
    }

    $item = $node->get($field)->first();

    if ($item === NULL) {
      return $this->refuse([sprintf('The %s field on "%s" is empty.', $field, $node->label())]);
    }

    $value = (string) ($item->get('value')->getValue() ?? '');
    $format = (string) ($item->get('format')->getValue() ?? '');

    // 2 · The format must keep what we are about to write.
    $stripping = $this->doctor->formatsStrippingClasses();

    if ($format !== '' && isset($stripping[$format])) {
      return $this->refuse([sprintf(
        'The "%s" text format strips class attributes, so these classes would be removed the moment '
        . 'the page was saved — with no error shown. Nothing was written. Allow class on the tags you '
        . 'need in that format, or move this content to a format that does.',
        $stripping[$format],
        ),
      ]);
    }

    $result = $this->addClasses($value, $tag, $classes);

    if ($result['changed'] === 0) {
      return $this->refuse([sprintf(
        'No <%s> element was found in the %s field of "%s", so nothing was changed.',
        $tag,
        $field,
        $node->label(),
        ),
      ]);
    }

    $item->set('value', $result['html']);

    // 1 · A new revision, always.
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage(sprintf(
      'UtiliKit AI added "%s" to %d <%s> element(s).',
      $classes,
      $result['changed'],
      $tag,
    ));

    // NodeInterface guarantees this through RevisionLogInterface, so there is
    // nothing to guard against — a method_exists() check here was flagged by
    // PHPStan as always true, and it was right.
    $node->setRevisionUserId((int) $this->currentUser->id());

    $node->save();

    return [
      'applied' => TRUE,
      'nid' => (int) $node->id(),
      'label' => (string) $node->label(),
      'field' => $field,
      'elements' => $result['changed'],
      'refusals' => [],
      'notes' => [
        sprintf(
          'Added "%s" to %d <%s> element(s).',
          $result['marked'] > 0 ? self::MARKER . ' ' . $classes : $classes,
          $result['changed'],
          $tag,
        ),
        'Saved as a NEW REVISION — the previous version is intact under Revisions.',
        $result['marked'] > 0
          ? sprintf('The "%s" class was added too; without it UtiliKit ignores the element entirely.', self::MARKER)
          : sprintf('The "%s" class was already on those elements, so it was left as it was.', self::MARKER),
        $this->doctor->needsDiscovery()
          ? sprintf(
              'This site is in %s mode, so the CSS is generated only for classes it knows. Saving the '
              . 'page should have registered these; if they do not appear, run drush utilikit:generate.',
              $this->doctor->renderingMode(),
        )
          : 'This site is in inline mode, so the classes take effect immediately.',
      ],
    ];
  }

  /**
   * Adds the classes to every matching element, without touching anything else.
   *
   * @param string $html
   *   The field's current HTML.
   * @param string $tag
   *   The tag name to match.
   * @param string $classes
   *   The classes to add.
   *
   * @return array{html: string, changed: int, marked: int}
   *   The new HTML, how many elements changed, and how many of those needed
   *   the marker adding — which is 0 on a second run over the same elements.
   */
  private function addClasses(string $html, string $tag, string $classes): array {
    $document = new \DOMDocument();
    $wanted = array_values(array_filter(array_merge([self::MARKER], preg_split('/\s+/', $classes) ?: [])));

    // A field is a fragment, not a document. LIBXML_HTML_NOIMPLIED and
    // NODEFDTD keep libxml from wrapping it in <html><body>, which would
    // otherwise be written back into the field.
    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadHTML(
      '<?xml encoding="utf-8" ?>' . $html,
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
      return ['html' => $html, 'changed' => 0, 'marked' => 0];
    }

    $changed = 0;
    $marked = 0;

    foreach ($document->getElementsByTagName($tag) as $element) {
      $existing = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
      $existing = array_values(array_filter($existing));
      $merged = $existing;

      if (!in_array(self::MARKER, $existing, TRUE)) {
        $marked++;
      }

      foreach ($wanted as $class) {
        // Adding a class twice would not break anything, but it would make the
        // markup grow every time the tool ran on the same page.
        if (!in_array($class, $merged, TRUE)) {
          $merged[] = $class;
        }
      }

      if ($merged !== $existing) {
        $element->setAttribute('class', implode(' ', $merged));
        $changed++;
      }
    }

    if ($changed === 0) {
      return ['html' => $html, 'changed' => 0, 'marked' => 0];
    }

    $out = '';

    foreach ($document->childNodes as $child) {
      if ($child instanceof \DOMProcessingInstruction) {
        continue;
      }

      $out .= $document->saveHTML($child);
    }

    return ['html' => $out, 'changed' => $changed, 'marked' => $marked];
  }

  /**
   * The rich-text field to edit.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string|null $wanted
   *   A field name the caller asked for, or NULL to find one.
   *
   * @return string|null
   *   The field name, or NULL when the node has no formatted-text field.
   */
  private function resolveField(NodeInterface $node, ?string $wanted): ?string {
    $wanted = trim((string) $wanted);
    $candidates = [];

    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if (in_array($definition->getType(), ['text_long', 'text_with_summary'], TRUE)) {
        $candidates[] = (string) $name;
      }
    }

    if ($wanted !== '') {
      return in_array($wanted, $candidates, TRUE) ? $wanted : NULL;
    }

    return $candidates[0] ?? NULL;
  }

  /**
   * A refusal, shaped like a result.
   *
   * @param list<string> $refusals
   *   The reasons.
   *
   * @return array{applied: bool, refusals: list<string>, notes: list<string>}
   *   The result.
   */
  private function refuse(array $refusals): array {
    return ['applied' => FALSE, 'refusals' => $refusals, 'notes' => []];
  }

}
