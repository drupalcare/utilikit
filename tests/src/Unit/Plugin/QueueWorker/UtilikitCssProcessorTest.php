<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit\Plugin\QueueWorker;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Service\UtiliKitCacheManager;
use PHPUnit\Framework\MockObject\MockObject;
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
  protected UtilikitCssProcessor $queueWorker;

  /**
   * Mock service provider.
   *
   * @var \Drupal\utilikit\Service\UtilikitServiceProvider&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitServiceProvider&MockObject $serviceProvider;

  /**
   * Mock cache manager.
   *
   * @var \Drupal\utilikit\Service\UtiliKitCacheManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtiliKitCacheManager&MockObject $cacheManager;

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

    $this->serviceProvider = $this->createMock(UtilikitServiceProvider::class);
    $this->cacheManager = $this->createMock(UtiliKitCacheManager::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $configuration = [];
    $plugin_id = 'utilikit_css_processor';
    $plugin_definition = [
      'id' => $plugin_id,
      'title' => 'UtiliKit CSS Processor',
      'cron' => ['time' => 60],
    ];

    $this->queueWorker = new UtilikitCssProcessor(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->serviceProvider,
      $this->cacheManager,
      $this->logger
    );
  }

  /**
   * Tests processItem with valid data.
   *
   * @covers ::processItem
   */
  public function testProcessItemValid(): void {
    $data = [
      'action' => 'add',
      'classes' => ['uk-pd--20', 'uk-mg--10'],
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with(['uk-pd--20', 'uk-mg--10'])
      ->willReturn([
        'added' => ['uk-pd--20', 'uk-mg--10'],
        'duplicates' => [],
        'errors' => [],
      ]);

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with remove action.
   *
   * @covers ::processItem
   */
  public function testProcessItemRemove(): void {
    $data = [
      'action' => 'remove',
      'classes' => ['uk-pd--20', 'uk-mg--10'],
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->serviceProvider->expects($this->once())
      ->method('removeClasses')
      ->with(['uk-pd--20', 'uk-mg--10'])
      ->willReturn([
        'removed' => ['uk-pd--20', 'uk-mg--10'],
        'notFound' => [],
      ]);

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with optimize action.
   *
   * @covers ::processItem
   */
  public function testProcessItemOptimize(): void {
    $data = [
      'action' => 'optimize',
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->serviceProvider->expects($this->once())
      ->method('optimizeCss')
      ->willReturn(TRUE);

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with rebuild action.
   *
   * @covers ::processItem
   */
  public function testProcessItemRebuild(): void {
    $data = [
      'action' => 'rebuild',
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->cacheManager->expects($this->once())
      ->method('invalidateAll');

    $this->logger->expects($this->once())
      ->method('info')
      ->with('CSS rebuild completed for user @user', ['@user' => 1]);

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with invalid action.
   *
   * @covers ::processItem
   */
  public function testProcessItemInvalidAction(): void {
    $data = [
      'action' => 'invalid',
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Unknown action: invalid'));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown action: invalid');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with missing action.
   *
   * @covers ::processItem
   */
  public function testProcessItemMissingAction(): void {
    $data = [
      'classes' => ['uk-pd--20'],
      'user_id' => 1,
    ];

    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Missing action in queue item'));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing action');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with batch processing.
   *
   * @covers ::processItem
   */
  public function testProcessItemBatch(): void {
    $data = [
      'action' => 'batch_add',
      'batches' => [
        ['uk-pd--20', 'uk-pd--30'],
        ['uk-mg--10', 'uk-mg--20'],
      ],
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->serviceProvider->expects($this->exactly(2))
      ->method('processNewClasses')
      ->willReturnOnConsecutiveCalls(
        [
          'added' => ['uk-pd--20', 'uk-pd--30'],
          'duplicates' => [],
          'errors' => [],
        ],
        [
          'added' => ['uk-mg--10', 'uk-mg--20'],
          'duplicates' => [],
          'errors' => [],
        ]
      );

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with error handling.
   *
   * @covers ::processItem
   */
  public function testProcessItemErrorHandling(): void {
    $data = [
      'action' => 'add',
      'classes' => ['uk-pd--20'],
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->willThrowException(new \Exception('Service error'));

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Error processing queue item'),
        $this->arrayHasKey('exception')
      );

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Service error');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with partial failure.
   *
   * @covers ::processItem
   */
  public function testProcessItemPartialFailure(): void {
    $data = [
      'action' => 'add',
      'classes' => ['uk-pd--20', 'uk-invalid--class', 'uk-mg--10'],
      'user_id' => 1,
      'timestamp' => time(),
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->willReturn([
        'added' => ['uk-pd--20', 'uk-mg--10'],
        'duplicates' => [],
        'errors' => ['uk-invalid--class: Invalid format'],
      ]);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with(
        'Partial failure processing classes: @errors',
        ['@errors' => 'uk-invalid--class: Invalid format']
      );

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with metadata.
   *
   * @covers ::processItem
   */
  public function testProcessItemWithMetadata(): void {
    $data = [
      'action' => 'add',
      'classes' => ['uk-pd--20'],
      'user_id' => 1,
      'timestamp' => time(),
      'metadata' => [
        'source' => 'admin_ui',
        'entity_type' => 'node',
        'entity_id' => 123,
      ],
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->with(['uk-pd--20'])
      ->willReturn([
        'added' => ['uk-pd--20'],
        'duplicates' => [],
        'errors' => [],
      ]);

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        'Processed classes from @source for @entity_type:@entity_id',
        [
          '@source' => 'admin_ui',
          '@entity_type' => 'node',
          '@entity_id' => 123,
        ]
      );

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with priority handling.
   *
   * @covers ::processItem
   */
  public function testProcessItemPriority(): void {
    $data = [
      'action' => 'add',
      'classes' => ['uk-pd--20'],
      'user_id' => 1,
      'timestamp' => time(),
      'priority' => 'high',
    ];

    $this->serviceProvider->expects($this->once())
      ->method('processNewClasses')
      ->willReturn([
        'added' => ['uk-pd--20'],
        'duplicates' => [],
        'errors' => [],
      ]);

    $this->logger->expects($this->once())
      ->method('info')
      ->with($this->stringContains('Processing high priority'));

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with dry run mode.
   *
   * @covers ::processItem
   */
  public function testProcessItemDryRun(): void {
    $data = [
      'action' => 'add',
      'classes' => ['uk-pd--20'],
      'user_id' => 1,
      'timestamp' => time(),
      'dry_run' => TRUE,
    ];

    // Service provider should not be called in dry run mode.
    $this->serviceProvider->expects($this->never())
      ->method('processNewClasses');

    // Cache should not be invalidated in dry run mode.
    $this->cacheManager->expects($this->never())
      ->method('invalidateCssCaches');

    $this->logger->expects($this->once())
      ->method('info')
      ->with($this->stringContains('Dry run mode'));

    $this->queueWorker->processItem($data);
  }

}
