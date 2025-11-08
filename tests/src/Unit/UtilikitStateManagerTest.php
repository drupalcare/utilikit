<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitStateManager service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitStateManager
 */
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
   * @var \Drupal\Core\State\StateInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected StateInterface&MockObject $state;

  /**
   * Mock lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LockBackendInterface&MockObject $lock;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->state = $this->createMock(StateInterface::class);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->stateManager = new UtilikitStateManager(
      $this->state,
      $this->lock,
      $this->logger
    );
  }

  /**
   * Tests getStoredClasses method.
   *
   * @covers ::getStoredClasses
   */
  public function testGetStoredClasses(): void {
    $classes = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($classes);

    $result = $this->stateManager->getStoredClasses();
    $this->assertEquals($classes, $result);
  }

  /**
   * Tests addClasses method.
   *
   * @covers ::addClasses
   */
  public function testAddClasses(): void {
    $existingClasses = [
      '.uk-pd--20' => 'padding: 20px;',
    ];

    $newClasses = [
      '.uk-mg--10' => 'margin: 10px;',
      '.uk-text--center' => 'text-align: center;',
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($existingClasses);

    $expectedClasses = array_merge($existingClasses, $newClasses);

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(TRUE);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        UtilikitConstants::STATE_CLASSES_KEY,
        $expectedClasses
      );

    $this->lock->expects($this->once())
      ->method('release')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->stateManager->addClasses($newClasses);
  }

  /**
   * Tests addClasses with lock failure.
   *
   * @covers ::addClasses
   */
  public function testAddClassesLockFailure(): void {
    $newClasses = [
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(FALSE);

    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Could not acquire lock'));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Could not acquire lock');

    $this->stateManager->addClasses($newClasses);
  }

  /**
   * Tests removeClasses method.
   *
   * @covers ::removeClasses
   */
  public function testRemoveClasses(): void {
    $existingClasses = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
      '.uk-text--center' => 'text-align: center;',
    ];

    $classesToRemove = ['.uk-pd--20', '.uk-text--center'];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($existingClasses);

    $expectedClasses = [
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(TRUE);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        UtilikitConstants::STATE_CLASSES_KEY,
        $expectedClasses
      );

    $this->lock->expects($this->once())
      ->method('release')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->stateManager->removeClasses($classesToRemove);
  }

  /**
   * Tests clearAllClasses method.
   *
   * @covers ::clearAllClasses
   */
  public function testClearAllClasses(): void {
    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(TRUE);

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, []);

    $this->lock->expects($this->once())
      ->method('release')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->stateManager->clearAllClasses();
  }

  /**
   * Tests hasClass method.
   *
   * @covers ::hasClass
   */
  public function testHasClass(): void {
    $classes = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->state->expects($this->exactly(2))
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($classes);

    $this->assertTrue($this->stateManager->hasClass('.uk-pd--20'));
    $this->assertFalse($this->stateManager->hasClass('.uk-text--center'));
  }

  /**
   * Tests getClassCount method.
   *
   * @covers ::getClassCount
   */
  public function testGetClassCount(): void {
    $classes = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
      '.uk-text--center' => 'text-align: center;',
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($classes);

    $count = $this->stateManager->getClassCount();
    $this->assertEquals(3, $count);
  }

  /**
   * Tests getStatistics method.
   *
   * @covers ::getStatistics
   */
  public function testGetStatistics(): void {
    $classes = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $timestamp = time();

    $this->state->expects($this->exactly(3))
      ->method('get')
      ->willReturnMap([
        [UtilikitConstants::STATE_CLASSES_KEY, [], $classes],
        [UtilikitConstants::STATE_LAST_UPDATED_KEY, NULL, $timestamp],
        [UtilikitConstants::STATE_STATISTICS_KEY, [], ['cache_hits' => 100]],
      ]);

    $stats = $this->stateManager->getStatistics();

    $this->assertArrayHasKey('total_classes', $stats);
    $this->assertArrayHasKey('last_updated', $stats);
    $this->assertArrayHasKey('cache_hits', $stats);
    $this->assertEquals(2, $stats['total_classes']);
    $this->assertEquals($timestamp, $stats['last_updated']);
    $this->assertEquals(100, $stats['cache_hits']);
  }

  /**
   * Tests updateStatistics method.
   *
   * @covers ::updateStatistics
   */
  public function testUpdateStatistics(): void {
    $existingStats = [
      'cache_hits' => 100,
      'cache_misses' => 20,
    ];

    $updates = [
      'cache_hits' => 110,
      'new_classes_added' => 5,
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_STATISTICS_KEY, [])
      ->willReturn($existingStats);

    $expectedStats = [
      'cache_hits' => 110,
      'cache_misses' => 20,
      'new_classes_added' => 5,
    ];

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(TRUE);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        UtilikitConstants::STATE_STATISTICS_KEY,
        $expectedStats
      );

    $this->lock->expects($this->once())
      ->method('release')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->stateManager->updateStatistics($updates);
  }

  /**
   * Tests getLastUpdated method.
   *
   * @covers ::getLastUpdated
   */
  public function testGetLastUpdated(): void {
    $timestamp = time();

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_LAST_UPDATED_KEY)
      ->willReturn($timestamp);

    $result = $this->stateManager->getLastUpdated();
    $this->assertEquals($timestamp, $result);
  }

  /**
   * Tests setLastUpdated method.
   *
   * @covers ::setLastUpdated
   */
  public function testSetLastUpdated(): void {
    $timestamp = time();

    $this->state->expects($this->once())
      ->method('set')
      ->with(UtilikitConstants::STATE_LAST_UPDATED_KEY, $timestamp);

    $this->stateManager->setLastUpdated($timestamp);
  }

  /**
   * Tests batch operations.
   *
   * @covers ::batchAddClasses
   */
  public function testBatchAddClasses(): void {
    $existingClasses = [
      '.uk-pd--10' => 'padding: 10px;',
    ];

    $batch1 = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $batch2 = [
      '.uk-text--center' => 'text-align: center;',
      '.uk-bg--primary' => 'background-color: var(--color-primary);',
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($existingClasses);

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(TRUE);

    $expectedFinal = array_merge($existingClasses, $batch1, $batch2);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        UtilikitConstants::STATE_CLASSES_KEY,
        $expectedFinal
      );

    $this->lock->expects($this->once())
      ->method('release')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->stateManager->batchAddClasses([$batch1, $batch2]);
  }

  /**
   * Tests getClassesByPrefix method.
   *
   * @covers ::getClassesByPrefix
   */
  public function testGetClassesByPrefix(): void {
    $classes = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-pd--10' => 'padding: 10px;',
      '.uk-mg--20' => 'margin: 20px;',
      '.uk-text--center' => 'text-align: center;',
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($classes);

    $pdClasses = $this->stateManager->getClassesByPrefix('.uk-pd');

    $this->assertCount(2, $pdClasses);
    $this->assertArrayHasKey('.uk-pd--20', $pdClasses);
    $this->assertArrayHasKey('.uk-pd--10', $pdClasses);
  }

  /**
   * Tests importClasses method.
   *
   * @covers ::importClasses
   */
  public function testImportClasses(): void {
    $importData = [
      '.uk-pd--30' => 'padding: 30px;',
      '.uk-mg--30' => 'margin: 30px;',
    ];

    $this->lock->expects($this->once())
      ->method('acquire')
      ->with(UtilikitConstants::LOCK_KEY)
      ->willReturn(TRUE);

    $this->state->expects($this->once())
      ->method('set')
      ->with(
        UtilikitConstants::STATE_CLASSES_KEY,
        $importData
      );

    $this->lock->expects($this->once())
      ->method('release')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->stateManager->importClasses($importData);
  }

  /**
   * Tests exportClasses method.
   *
   * @covers ::exportClasses
   */
  public function testExportClasses(): void {
    $classes = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->state->expects($this->once())
      ->method('get')
      ->with(UtilikitConstants::STATE_CLASSES_KEY, [])
      ->willReturn($classes);

    $exported = $this->stateManager->exportClasses();

    $this->assertArrayHasKey('classes', $exported);
    $this->assertArrayHasKey('metadata', $exported);
    $this->assertEquals($classes, $exported['classes']);
    $this->assertArrayHasKey('exported_at', $exported['metadata']);
    $this->assertArrayHasKey('count', $exported['metadata']);
    $this->assertEquals(2, $exported['metadata']['count']);
  }

  /**
   * Tests error handling in state operations.
   *
   * @covers ::addClasses
   */
  public function testErrorHandling(): void {
    $this->state->expects($this->once())
      ->method('get')
      ->willThrowException(new \Exception('Database error'));

    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Error reading state'));

    $classes = $this->stateManager->getStoredClasses();
    $this->assertEquals([], $classes);
  }

  /**
   * Tests concurrent access handling.
   *
   * @covers ::addClasses
   */
  public function testConcurrentAccess(): void {
    $attempts = 0;

    $this->lock->expects($this->exactly(3))
      ->method('acquire')
      ->willReturnCallback(function () use (&$attempts) {
        $attempts++;
        return $attempts === 3;
      });

    $this->lock->expects($this->exactly(2))
      ->method('wait')
      ->with(UtilikitConstants::LOCK_KEY);

    $this->state->expects($this->once())
      ->method('get')
      ->willReturn([]);

    $this->state->expects($this->once())
      ->method('set');

    $this->lock->expects($this->once())
      ->method('release');

    $this->stateManager->addClasses(['.uk-test--1' => 'test: 1;']);
  }

}
