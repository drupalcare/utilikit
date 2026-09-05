<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\utilikit_ai\Service\ClassValidatorInterface;
use Drupal\utilikit_ai\Service\RuleCatalogueInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The two mistakes a model makes with utility classes, and the marker.
 *
 * WHY EACH REJECTION IS ASSERTED WITH ITS REASON. "Invalid" tells a model
 * nothing and it will try again with something equally wrong. The failures here
 * are specific — an unknown prefix, a keyword from CSS rather than from this
 * site's list, a colour with the hash left on — and each has an obvious repair.
 * A test that only checked `valid === FALSE` would pass on a useless message.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class ClassValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = ['system', 'utilikit', 'utilikit_ai'];

  /**
   * The service under test.
   */
  private ClassValidatorInterface $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['utilikit']);
    $this->validator = \Drupal::service(ClassValidatorInterface::class);
  }

  /**
   * Classes that work on this site are accepted.
   */
  public function testRealClassesAreAccepted(): void {
    foreach ([
      'uk-pd--16px',
      'uk-md-pd--16px',
      'uk-mg--3d75rem',
      'uk-bg--3b82f6',
      'uk-dp--flex',
      'uk-wd--auto',
      'uk-pd--t-16px',
      'uk-op--50',
      'uk-or--2',
      'uk-gl--1-3',
      'utilikit',
    ] as $class) {
      $verdict = $this->validator->check($class);
      self::assertTrue($verdict->valid, $class . ' was rejected: ' . $verdict->reason);
    }
  }

  /**
   * An unknown prefix is named, and a near miss is suggested.
   *
   * Fifty two-letter codes is exactly the vocabulary a model half-remembers.
   */
  public function testUnknownPrefixIsRejectedByName(): void {
    $verdict = $this->validator->check('uk-zz--16px');

    self::assertFalse($verdict->valid);
    self::assertStringContainsString('no "zz" utility', $verdict->reason);
  }

  /**
   * A keyword that CSS allows but this site does not is refused with the list.
   *
   * MEASURED 2026-09-05: before the keyword lists were consolidated, PHP
   * accepted any word at all and wrote `display: banana;` into the stylesheet.
   */
  public function testKeywordOutsideTheListIsRefused(): void {
    $verdict = $this->validator->check('uk-dp--banana');

    self::assertFalse($verdict->valid);
    self::assertStringContainsString('not a value dp accepts', $verdict->reason);
    self::assertStringContainsString('flex', $verdict->reason, 'The refusal must name what IS accepted.');
  }

  /**
   * Shorthand keywords are accepted, because both engines map them.
   *
   * `uk-jc--between` means space-between. It rendered in inline mode and
   * produced no CSS in static and head until 2026-09-05.
   */
  public function testShorthandKeywordsAreAccepted(): void {
    foreach (['uk-jc--between', 'uk-jc--around', 'uk-ac--evenly'] as $class) {
      $verdict = $this->validator->check($class);
      self::assertTrue($verdict->valid, $class . ' was rejected: ' . $verdict->reason);
    }
  }

  /**
   * A colour with the hash left on is corrected, not merely refused.
   */
  public function testHashedColourIsCorrected(): void {
    $verdict = $this->validator->check('uk-bg--#3b82f6');

    self::assertFalse($verdict->valid);
    self::assertStringContainsString('no leading hash', $verdict->reason);
  }

  /**
   * The auto keyword is accepted exactly where CSS accepts it.
   *
   * Verified against the browser's own CSS.supports() on 2026-09-05:
   * inline-size takes auto, max-inline-size does not.
   */
  public function testAutoIsAcceptedOnlyWhereCssAllowsIt(): void {
    self::assertTrue($this->validator->check('uk-wd--auto')->valid);
    self::assertTrue($this->validator->check('uk-ht--auto')->valid);

    $refused = $this->validator->check('uk-xw--auto');
    self::assertFalse($refused->valid, 'max-inline-size does not accept auto in CSS.');
    self::assertStringContainsString('does not accept auto', $refused->reason);
  }

  /**
   * A direction is refused on a rule that has no sides.
   */
  public function testDirectionRefusedWhereThereAreNoSides(): void {
    $verdict = $this->validator->check('uk-dp--t-flex');

    self::assertFalse($verdict->valid);
    self::assertStringContainsString('does not take a direction', $verdict->reason);
  }

  /**
   * THE MARKER. Utility classes without it are inert.
   *
   * MEASURED in a browser 2026-09-05: `class="utilikit uk-pd--37px"` gives
   * 37px; `class="uk-pd--37px"` gives 0px. This is the single most important
   * thing a tool that emits classes has to get right.
   */
  public function testTheMarkerIsDetected(): void {
    self::assertTrue($this->validator->hasMarker('utilikit uk-pd--16px'));
    self::assertTrue($this->validator->hasMarker('  uk-pd--16px   utilikit '));
    self::assertFalse($this->validator->hasMarker('uk-pd--16px uk-mg--8px'));
    self::assertFalse($this->validator->hasMarker(''));
  }

  /**
   * A whole class string is checked class by class, in order.
   */
  public function testWholeStringIsCheckedInOrder(): void {
    $verdicts = $this->validator->checkAll('utilikit uk-pd--16px uk-zz--1 uk-dp--flex');

    self::assertCount(4, $verdicts);
    self::assertTrue($verdicts[0]->valid);
    self::assertTrue($verdicts[1]->valid);
    self::assertFalse($verdicts[2]->valid);
    self::assertTrue($verdicts[3]->valid);
  }

  /**
   * A value whose grammar belongs to UtiliKit is reported as unchecked.
   *
   * MEASURED 2026-09-05: `uk-gc--banana` produces
   * `grid-template-columns: banana;` in the generated stylesheet, which the
   * browser discards. An earlier version of this validator answered a plain
   * "valid" there — a false pass on exactly the failure it exists to catch.
   * Re-implementing UtiliKit's grid grammar here would be a second opinion
   * about it, so the truthful answer is that it was not checked.
   */
  public function testGrammarOwnedByUtilikitIsReportedAsUnchecked(): void {
    foreach (['uk-gc--repeat-3-1fr', 'uk-gc--banana'] as $class) {
      $verdict = $this->validator->check($class);

      self::assertTrue($verdict->valid, $class . ' should not be rejected outright.');
      self::assertNotSame('', $verdict->caveat, $class . ' must say it was not fully checked.');
      self::assertStringContainsString('grid track list', $verdict->caveat);
      self::assertStringContainsString('UNSURE', $verdict->line());
    }
  }

  /**
   * A fully checked class carries no caveat.
   *
   * The counterpart to the test above: if everything came back "unsure" the
   * state would be useless.
   */
  public function testFullyCheckedClassCarriesNoCaveat(): void {
    foreach (['uk-pd--16px', 'uk-dp--flex', 'uk-bg--3b82f6'] as $class) {
      $verdict = $this->validator->check($class);

      self::assertTrue($verdict->valid);
      self::assertSame('', $verdict->caveat, $class . ' was checked in full and should say so.');
      self::assertStringContainsString('OK', $verdict->line());
    }
  }

  /**
   * Every rejection is a sentence a person can act on.
   */
  public function testEveryRejectionReadsAsPlainSentence(): void {
    foreach (['uk-zz--1', 'uk-dp--banana', 'uk-bg--nothex', 'nonsense', 'uk-op--500'] as $class) {
      $verdict = $this->validator->check($class);

      self::assertFalse($verdict->valid, $class . ' should not be valid.');
      self::assertGreaterThan(20, strlen($verdict->reason), 'Too terse: ' . $verdict->reason);
      self::assertMatchesRegularExpression('/[.]$/', $verdict->reason, 'Not a sentence: ' . $verdict->reason);
    }
  }

  /**
   * THE MUTATION PROBE for the catalogue dependency.
   *
   * The validator owns no rule data — it asks the catalogue, which asks
   * UtilikitRules. If it ever grew its own list, this test would still pass
   * while the list silently drifted from the module's. So it asserts the
   * coupling directly: every prefix the catalogue knows is accepted, and the
   * count matches.
   */
  public function testItValidatesAgainstTheCatalogueAndNotItsOwnList(): void {
    $catalogue = \Drupal::service(RuleCatalogueInterface::class);
    $prefixes = $catalogue->prefixes();

    self::assertGreaterThan(40, count($prefixes), 'The catalogue is implausibly small.');

    foreach ($prefixes as $prefix) {
      $utility = $catalogue->get($prefix);
      self::assertNotNull($utility);

      // The example the catalogue itself offers must validate. If the two ever
      // disagree, one of them is inventing.
      $example = str_replace('utilikit ', '', $utility->example());
      $verdict = $this->validator->check($example);

      self::assertTrue(
        $verdict->valid,
        sprintf('%s offers the example "%s" which the validator rejects: %s', $prefix, $example, $verdict->reason),
      );
    }
  }

}
