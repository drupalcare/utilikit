<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ai\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\utilikit_ai\Service\ClassApplierInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Writing classes into content, and every way it must refuse instead.
 *
 * WHY THE MUTATION PROBE IS IN HERE. Every other test passes valid input and
 * would still pass if the applier trusted its caller. A model can skip
 * check_classes and diagnose_setup entirely, so the applier re-runs both —
 * and testItRefusesFormatsThatStripClasses is the test written for the guard
 * that matters most, because a strip is the one failure that looks like
 * success from every angle: the save returns, the revision exists, and the
 * classes are simply gone.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class ClassApplierTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'utilikit',
    'utilikit_ai',
  ];

  /**
   * The service under test.
   */
  private ClassApplierInterface $applier;

  /**
   * A node with formatted text to work on.
   */
  private Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node', 'utilikit']);

    // A format that keeps class attributes, so the happy path is reachable.
    FilterFormat::create([
      'format' => 'permissive',
      'name' => 'Permissive',
      'filters' => [],
    ])->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();

    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
    ])->save();

    $this->setUpCurrentUser(permissions: [
      'access content',
      'bypass node access',
    ]);

    $this->node = Node::create([
      'type' => 'page',
      'title' => 'A page',
      'body' => [
        'value' => '<p>One</p><p>Two</p><div>Not a paragraph</div>',
        'format' => 'permissive',
      ],
    ]);
    $this->node->save();

    $this->applier = \Drupal::service(ClassApplierInterface::class);
  }

  /**
   * The body HTML as it currently stands.
   *
   * @return string
   *   The stored value.
   */
  private function body(): string {
    $node = Node::load((int) $this->node->id());

    return (string) $node->get('body')->first()->get('value')->getValue();
  }

  /**
   * Classes are added to every matching element, with the marker.
   */
  public function testItAddsClassesAndTheMarker(): void {
    $result = $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px');

    self::assertTrue($result['applied'], implode(' | ', $result['refusals']));
    self::assertSame(2, $result['elements'], 'Both paragraphs should change.');

    $body = $this->body();
    self::assertStringContainsString('class="utilikit uk-pd--16px"', $body);
    self::assertSame(2, substr_count($body, 'uk-pd--16px'));
    self::assertStringContainsString('<div>Not a paragraph</div>', $body, 'Other tags are untouched.');
  }

  /**
   * The text is never altered, only the class attribute.
   */
  public function testItChangesNoText(): void {
    $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px');
    $body = $this->body();

    self::assertStringContainsString('One', $body);
    self::assertStringContainsString('Two', $body);
    self::assertStringContainsString('Not a paragraph', $body);
  }

  /**
   * A new revision is created, so the change can be undone.
   */
  public function testItSavesNewRevision(): void {
    $before = (int) $this->node->getRevisionId();

    $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px');

    $after = (int) Node::load((int) $this->node->id())->getRevisionId();
    self::assertGreaterThan($before, $after, 'The previous version must remain reachable.');
  }

  /**
   * Existing classes are kept, and running twice does not duplicate.
   */
  public function testItPreservesExistingClassesAndDoesNotDuplicate(): void {
    $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px');
    $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-mg--8px');
    $body = $this->body();

    self::assertStringContainsString('uk-pd--16px', $body);
    self::assertStringContainsString('uk-mg--8px', $body);
    // The marker must appear once per element, not once per run.
    self::assertSame(2, substr_count($body, 'utilikit'));
  }

  /**
   * The report says what actually happened, not what usually happens.
   *
   * A second run over the same elements does not add the marker, because it is
   * already there. Claiming it did would be a small lie in a message a person
   * reads to decide whether to check the page.
   */
  public function testTheMarkerIsOnlyClaimedWhenItWasAdded(): void {
    $first = $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px');
    $second = $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-mg--8px');

    self::assertStringContainsString('was added too', implode(' ', $first['notes']));
    self::assertStringContainsString('already on those elements', implode(' ', $second['notes']));
    self::assertStringNotContainsString('was added too', implode(' ', $second['notes']));
  }

  /**
   * THE MUTATION PROBE. A format that strips classes is refused.
   *
   * MEASURED on sts.local 2026-09-05: Basic HTML and Restricted HTML both drop
   * class attributes. Writing into one of those would return success, create a
   * revision, and lose the classes — a failure that looks like success from
   * every angle. Remove the applier's own format check and only this fails.
   */
  public function testItRefusesFormatsThatStripClasses(): void {
    FilterFormat::create([
      'format' => 'locked_down',
      'name' => 'Locked down',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => ['allowed_html' => '<p> <em> <strong>'],
        ],
      ],
    ])->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Restricted page',
      'body' => ['value' => '<p>Text</p>', 'format' => 'locked_down'],
    ]);
    $node->save();
    $before = (string) $node->get('body')->first()->get('value')->getValue();

    $result = $this->applier->apply((int) $node->id(), NULL, 'p', 'uk-pd--16px');

    self::assertFalse($result['applied'], 'A stripping format must be refused.');
    self::assertStringContainsString('strips class attributes', implode(' ', $result['refusals']));
    self::assertSame(
      $before,
      (string) Node::load((int) $node->id())->get('body')->first()->get('value')->getValue(),
      'A refusal must change nothing.',
    );
  }

  /**
   * One invalid class refuses the whole request.
   */
  public function testOneBadClassRefusesEverything(): void {
    $before = $this->body();

    $result = $this->applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px uk-dp--banana');

    self::assertFalse($result['applied']);
    self::assertStringContainsString('would produce no CSS', implode(' ', $result['refusals']));
    self::assertSame($before, $this->body(), 'Nothing may be written when one class is bad.');
  }

  /**
   * A page that does not exist is refused by number.
   */
  public function testMissingPageIsRefused(): void {
    $result = $this->applier->apply(999999, NULL, 'p', 'uk-pd--16px');

    self::assertFalse($result['applied']);
    self::assertStringContainsString('no page with id 999999', implode(' ', $result['refusals']));
  }

  /**
   * A tag that is not in the content changes nothing, and says so.
   */
  public function testAnAbsentTagChangesNothing(): void {
    $before = $this->body();

    $result = $this->applier->apply((int) $this->node->id(), NULL, 'h2', 'uk-pd--16px');

    self::assertFalse($result['applied']);
    self::assertStringContainsString('No <h2> element was found', implode(' ', $result['refusals']));
    self::assertSame($before, $this->body());
  }

  /**
   * Called with nothing, it asks for everything at once.
   */
  public function testItAsksForEverythingAtOnce(): void {
    $result = $this->applier->apply(NULL, NULL, NULL, NULL);

    self::assertFalse($result['applied']);
    self::assertGreaterThanOrEqual(3, count($result['refusals']), 'All missing answers come back together.');

    foreach ($result['refusals'] as $refusal) {
      self::assertGreaterThan(20, strlen($refusal), 'Too terse to act on: ' . $refusal);
    }
  }

  /**
   * Without update access, nothing is written.
   */
  public function testWithoutAccessNothingIsWritten(): void {
    $this->setUpCurrentUser(permissions: ['access content']);
    $applier = \Drupal::service(ClassApplierInterface::class);
    $before = $this->body();

    $result = $applier->apply((int) $this->node->id(), NULL, 'p', 'uk-pd--16px');

    self::assertFalse($result['applied']);
    self::assertStringContainsString('permission', implode(' ', $result['refusals']));
    self::assertSame($before, $this->body());
  }

}
