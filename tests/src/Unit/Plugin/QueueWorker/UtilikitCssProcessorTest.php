<?php

namespace Drupal\Tests\utilikit\Unit\Plugin\QueueWorker;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor;
use Drupal\utilikit\Service\UtiliKitServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitCssProcessor queue worker.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor
 */
class UtilikitCssProcessorTest extends UnitTestCase {

  /**
   * The queue worker under test.
   *
   * @var \Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor
   */
  protected $queueWorker;

  /**
   * Mock service provider.
   *
   * @var \Drupal\utilikit\Service\UtiliKitServiceProvider|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $serviceProvider;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->serviceProvider = $this->createMock(UtiliKitServiceProvider::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->queueWorker = new UtilikitCssProcessor(
      [],
      'utilikit_css_processor',
      [],
      $this->serviceProvider,
      $this->logger
    );
  }

  /**
   * Tests processItem with valid data.
   *
   * @covers ::processItem
   */
  public function testProcessItemValidData() {
    $data = [
      'classes' => ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'],
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    // Expect service provider to process new classes.
    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($data['classes'])
      ->willReturn([
        'new_classes' => ['uk-bg--ff0000'],
        'classes_processed' => 1,
      ]);

    // Expect success logging.
    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully processed'),
        $this->arrayHasKey('classes_count')
      );

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with empty classes array.
   *
   * @covers ::processItem
   */
  public function testProcessItemEmptyClasses() {
    $data = [
      'classes' => [],
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    // Should not call service provider for empty classes.
    $this->serviceProvider->expects($this->never())
      ->method('processNewClasses');

    // Expect warning logging.
    $this->logger->expects($this->once())
      ->method('warning')
      ->with('Queue item contains no classes to process');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with malformed data structure.
   *
   * @covers ::processItem
   */
  public function testProcessItemMalformedData() {
    $data = [
      'invalid_key' => 'invalid_value',
      // Missing 'classes' key.
    ];

    // Should not call service provider for malformed data.
    $this->serviceProvider->expects($this->never())
      ->method('processNewClasses');

    // Expect error logging.
    $this->logger->expects($this->once())
      ->method('error')
      ->with('Queue item data is malformed or missing classes array');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with non-array classes.
   *
   * @covers ::processItem
   */
  public function testProcessItemNonArrayClasses() {
    $data = [
    // String instead of array.
      'classes' => 'uk-pd--20,uk-mg--10',
      'entity_type' => 'node',
      'entity_id' => 123,
    ];

    // Should not call service provider for non-array classes.
    $this->serviceProvider->expects($this->never())
      ->method('processNewClasses');

    // Expect error logging.
    $this->logger->expects($this->once())
      ->method('error')
      ->with('Queue item data is malformed or missing classes array');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem when service provider throws exception.
   *
   * @covers ::processItem
   */
  public function testProcessItemServiceProviderException() {
    $data = [
      'classes' => ['uk-pd--20', 'uk-mg--10'],
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    $exception = new \Exception('Test exception');

    // Service provider throws exception.
    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($data['classes'])
      ->willThrowException($exception);

    // Expect error logging.
    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Failed to process CSS classes'),
        $this->arrayHasKey('error')
      );

    // Exception should be re-thrown to mark queue item as failed.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Test exception');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem filters out invalid utility classes.
   *
   * @covers ::processItem
   */
  public function testProcessItemFiltersInvalidClasses() {
    $data = [
      'classes' => [
    // Valid.
        'uk-pd--20',
    // Invalid (not uk- prefixed)
        'invalid-class',
    // Valid.
        'uk-mg--auto',
    // Invalid.
        'another-invalid',
      ],
      'entity_type' => 'node',
      'entity_id' => 123,
    ];

    // Should only process valid UtiliKit classes.
    $expectedValidClasses = ['uk-pd--20', 'uk-mg--auto'];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($expectedValidClasses)
      ->willReturn([
        'new_classes' => $expectedValidClasses,
        'classes_processed' => 2,
      ]);

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully processed'),
        $this->callback(function ($context) {
          return $context['classes_count'] === 2;
        })
      );

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with large number of classes.
   *
   * @covers ::processItem
   */
  public function testProcessItemLargeClassSet() {
    // Generate large number of test classes.
    $largeClassSet = [];
    for ($i = 1; $i <= 1000; $i++) {
      $largeClassSet[] = "uk-pd--{$i}";
    }

    $data = [
      'classes' => $largeClassSet,
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    // Should process large class sets.
    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($largeClassSet)
      ->willReturn([
        'new_classes' => $largeClassSet,
        'classes_processed' => 1000,
      ]);

    // Expect success logging with large count.
    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully processed'),
        $this->callback(function ($context) {
          return $context['classes_count'] === 1000;
        })
      );

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with mixed valid and invalid classes in large set.
   *
   * @covers ::processItem
   */
  public function testProcessItemLargeClassSetWithInvalid() {
    // Generate mix of valid and invalid classes.
    $mixedClasses = [];
    for ($i = 1; $i <= 500; $i++) {
      // Valid.
      $mixedClasses[] = "uk-pd--{$i}";
      // Invalid.
      $mixedClasses[] = "invalid-class-{$i}";
    }

    $data = [
      'classes' => $mixedClasses,
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    // Should only process the 500 valid classes.
    $expectedValidClasses = array_filter($mixedClasses, function ($class) {
      return strpos($class, 'uk-') === 0;
    });

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($expectedValidClasses)
      ->willReturn([
        'new_classes' => $expectedValidClasses,
        'classes_processed' => 500,
      ]);

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully processed'),
        $this->callback(function ($context) {
          return $context['classes_count'] === 500;
        })
      );

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem performance with very large class sets.
   *
   * @covers ::processItem
   */
  public function testProcessItemPerformanceLargeSet() {
    // Generate very large class set to test performance.
    $veryLargeClassSet = [];
    for ($i = 1; $i <= 5000; $i++) {
      $veryLargeClassSet[] = "uk-pd--{$i}";
    }

    $data = [
      'classes' => $veryLargeClassSet,
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($veryLargeClassSet)
      ->willReturn([
        'new_classes' => $veryLargeClassSet,
        'classes_processed' => 5000,
      ]);

    $this->logger->expects($this->once())
      ->method('info');

    // Measure execution time.
    $startTime = microtime(TRUE);
    $this->queueWorker->processItem($data);
    $executionTime = microtime(TRUE) - $startTime;

    // Should complete within reasonable time (adjust threshold as needed)
    $this->assertLessThan(1.0, $executionTime, 'Processing should complete within 1 second');
  }

  /**
   * Tests processItem with duplicate classes in large set.
   *
   * @covers ::processItem
   */
  public function testProcessItemLargeSetWithDuplicates() {
    // Generate class set with many duplicates.
    $classesWithDuplicates = [];
    for ($i = 1; $i <= 100; $i++) {
      // Each class appears 10 times.
      for ($j = 1; $j <= 10; $j++) {
        $classesWithDuplicates[] = "uk-pd--{$i}";
      }
    }

    $data = [
      'classes' => $classesWithDuplicates,
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    // Should deduplicate before processing.
    $expectedUniqueClasses = [];
    for ($i = 1; $i <= 100; $i++) {
      $expectedUniqueClasses[] = "uk-pd--{$i}";
    }

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with($expectedUniqueClasses)
      ->willReturn([
        'new_classes' => $expectedUniqueClasses,
        'classes_processed' => 100,
      ]);

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully processed'),
        $this->callback(function ($context) {
          return $context['classes_count'] === 100;
        })
      );

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem logs processing statistics.
   *
   * @covers ::processItem
   */
  public function testProcessItemLogsStatistics() {
    $data = [
      'classes' => ['uk-pd--20', 'uk-mg--10'],
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => 1234567890,
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->willReturn([
        'new_classes' => ['uk-pd--20'],
        'classes_processed' => 1,
      ]);

    // Check that logging includes proper context.
    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully processed'),
        $this->callback(function ($context) {
          return isset($context['classes_count']) &&
                 isset($context['entity_type']) &&
                 isset($context['entity_id']) &&
                 $context['classes_count'] === 1 &&
                 $context['entity_type'] === 'node' &&
                 $context['entity_id'] === 123;
        })
      );

    $this->queueWorker->processItem($data);
  }

}
