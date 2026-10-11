<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\State\StateInterface;
use PHPUnit\Framework\MockObject\Stub;

/**
 * Tests the UtilikitStateManager service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitStateManager
 */
#[CoversClass(\Drupal\utilikit\Service\UtilikitStateManager::class)]
#[Group('utilikit')]
class UtilikitStateManagerTest extends UnitTestCase {

  /**
   * The state manager service under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager
   */
  protected UtilikitStateManager $stateManager;

  /**
   * Mock state service.
   *
   * @var \Drupal\Core\State\StateInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected StateInterface&Stub $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->state = $this->createStub(StateInterface::class);
    $this->buildSubject();
  }

  /**
   * Builds the subject under test from the current collaborators.
   *
   * Collaborators are stubs by default; a test that asserts calls on one
   * replaces it with a mock and calls this again.
   */
  private function buildSubject(): void {
    $this->stateManager = new UtilikitStateManager($this->state);
  }

  /**
   * Tests getKnownClasses method.
   *
   * @covers ::getKnownClasses
   */
  public function testGetKnownClasses(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $classes = ['uk-pd--20', 'uk-mg--10'];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_KNOWN_CLASSES, [])
      ->willReturn($classes);

    $result = $this->stateManager->getKnownClasses();
    $this->assertEquals($classes, $result);
  }

  /**
   * Tests setKnownClasses method.
   *
   * @covers ::setKnownClasses
   */
  public function testSetKnownClasses(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $classes = ['uk-pd--20', 'uk-mg--10'];

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_KNOWN_CLASSES, $this->callback(function ($arg) {
        return is_array($arg) && count($arg) === 2
          && in_array('uk-pd--20', $arg, TRUE) && in_array('uk-mg--10', $arg, TRUE);
      }));

    $this->stateManager->setKnownClasses($classes);
  }

  /**
   * Tests addKnownClasses method.
   *
   * @covers ::addKnownClasses
   */
  public function testAddKnownClasses(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $existing = ['uk-pd--20'];
    $newClasses = ['uk-mg--10', 'uk-pd--20'];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_KNOWN_CLASSES, [])
      ->willReturn($existing);

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_KNOWN_CLASSES, $this->callback(function ($arg) {
        return count($arg) === 2 && in_array('uk-pd--20', $arg, TRUE) && in_array('uk-mg--10', $arg, TRUE);
      }));

    $result = $this->stateManager->addKnownClasses($newClasses);
    $this->assertCount(2, $result);
  }

  /**
   * Tests getGeneratedCss method.
   *
   * @covers ::getGeneratedCss
   */
  public function testGetGeneratedCss(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $css = '.uk-pd--20 { padding: 20px; }';

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_GENERATED_CSS, '')
      ->willReturn($css);

    $result = $this->stateManager->getGeneratedCss();
    $this->assertEquals($css, $result);
  }

  /**
   * Tests setGeneratedCss method.
   *
   * @covers ::setGeneratedCss
   */
  public function testSetGeneratedCss(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $css = '.uk-pd--20 { padding: 20px; }';

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_GENERATED_CSS, $css);

    $this->stateManager->setGeneratedCss($css);
  }

  /**
   * Tests getCssTimestamp method.
   *
   * @covers ::getCssTimestamp
   */
  public function testGetCssTimestamp(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $timestamp = 1234567890;

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, 0)
      ->willReturn($timestamp);

    $result = $this->stateManager->getCssTimestamp();
    $this->assertEquals($timestamp, $result);
  }

  /**
   * Tests updateCssTimestamp method.
   *
   * @covers ::updateCssTimestamp
   */
  public function testUpdateCssTimestamp(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CSS_TIMESTAMP, $this->callback(static fn ($value): bool => is_int($value)));

    $this->stateManager->updateCssTimestamp();
  }

  /**
   * Tests clearUtilikitData method.
   *
   * @covers ::clearUtilikitData
   */
  public function testClearUtilikitData(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $this->state->expects($this->once())
      ->method('deleteMultiple')
      ->with([
        UtilikitConstants::STATE_GENERATED_CSS,
        UtilikitConstants::STATE_KNOWN_CLASSES,
      ]);

    $this->stateManager->clearUtilikitData();
  }

  /**
   * Tests getLastCleanup method.
   *
   * @covers ::getLastCleanup
   */
  public function testGetLastCleanup(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $timestamp = 1234567890;

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_LAST_CLEANUP, 0)
      ->willReturn($timestamp);

    $result = $this->stateManager->getLastCleanup();
    $this->assertEquals($timestamp, $result);
  }

  /**
   * Tests setLastCleanup method.
   *
   * @covers ::setLastCleanup
   */
  public function testSetLastCleanup(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $timestamp = 1234567890;

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_LAST_CLEANUP, $timestamp);

    $this->stateManager->setLastCleanup($timestamp);
  }

  /**
   * Tests getRateLimitData method.
   *
   * @covers ::getRateLimitData
   */
  public function testGetRateLimitData(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $this->state->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['utilikit_rate_limit:192.168.1.1', 0, 5],
        ['utilikit_rate_limit:192.168.1.1_reset', 0, 1234567890],
      ]);

    $result = $this->stateManager->getRateLimitData('192.168.1.1');
    $this->assertArrayHasKey('current', $result);
    $this->assertArrayHasKey('reset_time', $result);
    $this->assertEquals(5, $result['current']);
    $this->assertEquals(1234567890, $result['reset_time']);
  }

  /**
   * Tests setRateLimitData method.
   *
   * @covers ::setRateLimitData
   */
  public function testSetRateLimitData(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $calls = [];
    $this->state->expects($this->exactly(2))
      ->method('set')
      ->willReturnCallback(function ($key, $value) use (&$calls) {
        $calls[] = [$key, $value];
      });

    $this->stateManager->setRateLimitData('key1', 3, 1234567890);

    $this->assertSame('utilikit_rate_limit:key1', $calls[0][0]);
    $this->assertSame(3, $calls[0][1]);
    $this->assertSame('utilikit_rate_limit:key1_reset', $calls[1][0]);
    $this->assertSame(1234567890, $calls[1][1]);
  }

  /**
   * Tests clearRateLimitData method.
   *
   * @covers ::clearRateLimitData
   */
  public function testClearRateLimitData(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $this->state->expects($this->once())
      ->method('deleteMultiple')
      ->with([
        'utilikit_rate_limit:key1',
        'utilikit_rate_limit:key1_reset',
      ]);

    $this->stateManager->clearRateLimitData('key1');
  }

  /**
   * Tests getStateStatistics method.
   *
   * @covers ::getStateStatistics
   */
  public function testGetStateStatistics(): void {
    $this->state = $this->createMock(StateInterface::class);
    $this->buildSubject();

    $this->state->expects($this->exactly(4))
      ->method('get')
      ->willReturnMap([
        [UtilikitConstants::STATE_KNOWN_CLASSES, [], ['uk-pd--20', 'uk-mg--10']],
        [UtilikitConstants::STATE_GENERATED_CSS, '', '.uk-pd--20{}'],
        [UtilikitConstants::STATE_CSS_TIMESTAMP, 0, 1234567890],
        [UtilikitConstants::STATE_LAST_CLEANUP, 0, 1234567800],
      ]);

    $result = $this->stateManager->getStateStatistics();
    $this->assertArrayHasKey('known_classes_count', $result);
    $this->assertArrayHasKey('generated_css_size', $result);
    $this->assertArrayHasKey('css_timestamp', $result);
    $this->assertArrayHasKey('last_cleanup', $result);
    $this->assertEquals(2, $result['known_classes_count']);
    $this->assertEquals(12, $result['generated_css_size']);
  }

  /**
   * Tests getAllUtilikitStateKeys method.
   *
   * @covers ::getAllUtilikitStateKeys
   */
  public function testGetAllUtilikitStateKeys(): void {
    $keys = $this->stateManager->getAllUtilikitStateKeys();
    $this->assertContains(UtilikitConstants::STATE_KNOWN_CLASSES, $keys);
    $this->assertContains(UtilikitConstants::STATE_GENERATED_CSS, $keys);
    $this->assertContains(UtilikitConstants::STATE_CSS_TIMESTAMP, $keys);
    $this->assertContains(UtilikitConstants::STATE_LAST_CLEANUP, $keys);
  }

}
