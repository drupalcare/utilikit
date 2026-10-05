<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\utilikit_ai\Service\ChatSurfaceInterface;
use Drupal\utilikit_ai\Service\ClassApplierInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every reason the node-form chat bar stays hidden, in words.
 *
 * WHY EACH REFUSAL IS ASSERTED SEPARATELY. Six preconditions gate the bar and
 * not one of them is visible from the node form. A person who turns this on
 * and sees nothing has no way to tell which one is missing — so each refusal
 * has to name itself, and a test that only checked `show === FALSE` would let
 * a useless message through.
 *
 * The bar is NOT expected to show in these tests: ai_chatbot and
 * ai_assistant_api are not dependencies of this module and are not installed
 * here. That is the point of the first refusals — the module has to behave on
 * a site that has only what it actually requires.
 *
 * @group utilikit
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('utilikit')]
#[RunTestsInSeparateProcesses]
final class ChatSurfaceTest extends KernelTestBase {

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
    'filter',
    'text',
    'node',
    'utilikit',
    'utilikit_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'utilikit_ai']);
  }

  /**
   * Without the permission, the bar is hidden and says so.
   *
   * Checked first, and refused first, so that someone without the permission
   * is never told about the site's AI configuration through the reason text.
   */
  public function testWithoutThePermissionItSaysSo(): void {
    $this->setUpCurrentUser(permissions: ['access content']);

    $decision = $this->surface()->decide();

    self::assertFalse($decision->show);
    self::assertStringContainsString('permission', $decision->reason);
  }

  /**
   * Without ai_chatbot, the reason names ai_chatbot.
   */
  public function testWithoutTheChatbotModuleItNamesIt(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);

    $decision = $this->surface()->decide();

    self::assertFalse($decision->show);
    self::assertStringContainsString('ai_chatbot', $decision->reason);
  }

  /**
   * Nothing is rendered when the bar is hidden.
   *
   * A half-built container with no chat inside would leave an empty box on
   * every node form — visible, useless, and hard to trace back to here.
   */
  public function testHiddenBarRendersNothingAtAll(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);

    self::assertSame([], $this->surface()->build());
    self::assertSame([], $this->surface()->build('article'));
  }

  /**
   * Every refusal is a sentence someone can act on.
   *
   * The whole purpose of carrying a reason is defeated by a reason that reads
   * like an error code.
   */
  public function testEveryRefusalReadsAsPlainSentence(): void {
    $surface = $this->surface();

    foreach ([['access content'], [ChatSurfaceInterface::PERMISSION]] as $permissions) {
      $this->setUpCurrentUser(permissions: $permissions);
      $reason = $surface->decide()->reason;

      self::assertGreaterThan(30, strlen($reason), 'Too terse to act on: ' . $reason);
      self::assertMatchesRegularExpression('/[.]$/', $reason, 'Not a sentence: ' . $reason);
    }
  }

  /**
   * The bar never writes configuration just by being asked about itself.
   *
   * Deciding whether to show a bar is a read. If asking the question altered
   * the site's configuration, the settings page — which asks on every load —
   * would rewrite config on each visit.
   */
  public function testDecidingWritesNoConfiguration(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);
    $before = $this->config('utilikit_ai.settings')->getRawData();

    $surface = $this->surface();
    $surface->decide();
    $surface->build();

    self::assertSame($before, $this->config('utilikit_ai.settings')->getRawData());
  }

  /**
   * The shipped setting is empty, and that is the intended resting state.
   *
   * An assistant cannot be shipped: the entity names an llm_provider and
   * llm_model that exist on one site only, so a default would install
   * something that looks configured and fails on first use.
   */
  public function testItShipsWithNoAssistantChosen(): void {
    self::assertSame('', $this->config('utilikit_ai.settings')->get('assistant'));
  }

  /**
   * A content type is offered only when the writer could write into it.
   *
   * THE ASSERTION THAT MATTERS IS THE NEGATIVE ONE. A content type with no
   * formatted-text field is where `apply_classes` can only ever refuse, and
   * offering the bar there produces a conversation that was doomed before it
   * started. Both types exist here, so a version that returned everything —
   * or nothing — fails rather than passing vacuously.
   */
  public function testOnlyContentTypesWithSomewhereToWriteAreOffered(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);

    NodeType::create(['type' => 'with_text', 'name' => 'With text'])->save();
    NodeType::create(['type' => 'no_text', 'name' => 'No text'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'bundle' => 'with_text',
      'label' => 'Body',
    ])->save();

    $writable = $this->surface()->writableContentTypes();

    self::assertArrayHasKey('with_text', $writable);
    self::assertArrayNotHasKey('no_text', $writable);
    self::assertSame('With text', $writable['with_text']);
  }

  /**
   * Every field type the writer accepts is one the surface offers.
   *
   * THE DRIFT TEST, and it is driven by the constant rather than by a copy of
   * it: each type in RICH_TEXT_FIELD_TYPES gets its own content type here, and
   * all of them must be offered. A surface that recognised only the first —
   * the obvious way for these two to drift apart — fails on the second, and
   * adding a type to the writer's list extends this test with no edit.
   *
   * The defect it prevents is silent in both directions: a bar offered where
   * nothing can be written, or hidden where something can. Neither reports
   * anything.
   */
  public function testEveryFieldTypeTheWriterAcceptsIsOffered(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);

    self::assertNotSame([], ClassApplierInterface::RICH_TEXT_FIELD_TYPES);

    // text_with_summary was removed from core in Drupal 12, so a field of that
    // type cannot be created there; the writer still lists it for Drupal 11.
    // Test every listed type the running core can actually create.
    $fieldTypes = $this->container->get('plugin.manager.field.field_type');
    $creatable = array_filter(
      ClassApplierInterface::RICH_TEXT_FIELD_TYPES,
      static fn (string $type): bool => $fieldTypes->hasDefinition($type),
    );
    self::assertNotSame([], $creatable, 'At least one rich-text type exists on this core.');

    foreach ($creatable as $index => $type) {
      $bundle = 'takes_' . $index;

      NodeType::create(['type' => $bundle, 'name' => 'Takes ' . $type])->save();

      FieldStorageConfig::create([
        'field_name' => 'field_text_' . $index,
        'entity_type' => 'node',
        'type' => $type,
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_text_' . $index,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => 'Text',
      ])->save();
    }

    $writable = $this->surface()->writableContentTypes();

    foreach ($creatable as $index => $type) {
      self::assertArrayHasKey(
        'takes_' . $index,
        $writable,
        sprintf('The writer accepts %s, so the surface must offer it.', $type),
      );
    }
  }

  /**
   * The surface, typed.
   *
   * The container returns `object`, so every property read off a decision was
   * `mixed` and the assertions were testing types rather than behaviour.
   *
   * @return \Drupal\utilikit_ai\Service\ChatSurfaceInterface
   *   The surface.
   */
  private function surface(): ChatSurfaceInterface {
    // \Drupal::service() and not $this->container->get(): on Drupal 12
    // KernelTestBase types $container as ?ContainerBuilder, so every call
    // through it is a null-call. Measured on drupal.org CI 2026-09-05 — ten
    // findings across six test classes, in the `phpstan (next major)` job
    // only, because Drupal 11 does not type it that way. The static accessor
    // has no nullable receiver, so the whole class of error is gone by
    // construction rather than suppressed by an assertion.
    $surface = \Drupal::service(ChatSurfaceInterface::class);
    self::assertInstanceOf(ChatSurfaceInterface::class, $surface);

    return $surface;
  }

}
