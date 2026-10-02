<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor;
use Drupal\utilikit\Service\UtilikitCacheManager;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Service\UtilikitStateManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitCssProcessor queue worker.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor
 */
#[CoversClass(\Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor::class)]
#[Group('utilikit')]
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
   * Mock cache manager (returned by serviceProvider->getCacheManager()).
   *
   * @var \Drupal\utilikit\Service\UtilikitCacheManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitCacheManager&MockObject $cacheManager;

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
    $this->cacheManager = $this->createMock(UtilikitCacheManager::class);
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
      $this->logger,
      $this->serviceProvider
    );
  }

  /**
   * Tests processItem with valid data and new classes.
   *
   * @covers ::processItem
   */
  public function testProcessItemValid(): void {
    $data = [
      'classes' => ['uk-pd--20', 'uk-mg--10'],
    ];

    $contentScanner = $this->createMock(UtilikitContentScanner::class);
    $contentScanner->expects($this->once())
      ->method('validateUtilityClasses')
      ->with(['uk-pd--20', 'uk-mg--10'])
      ->willReturn(['uk-pd--20', 'uk-mg--10']);

    $stateManager = $this->createMock(UtilikitStateManager::class);
    $stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn([]);

    $this->serviceProvider->expects($this->once())
      ->method('getContentScanner')
      ->willReturn($contentScanner);
    $this->serviceProvider->expects($this->once())
      ->method('getStateManager')
      ->willReturn($stateManager);
    $this->serviceProvider->expects($this->once())
      ->method('updateCssAndFile')
      ->with(['uk-pd--20', 'uk-mg--10'])
      ->willReturn(TRUE);
    $this->serviceProvider->expects($this->once())
      ->method('getCacheManager')
      ->willReturn($this->cacheManager);

    $this->cacheManager->expects($this->once())
      ->method('clearCssCaches');

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Processed queued CSS update with @count new classes', $this->anything());

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem when all classes are already known (no update).
   *
   * @covers ::processItem
   */
  public function testProcessItemNoNewClasses(): void {
    $data = [
      'classes' => ['uk-pd--20', 'uk-mg--10'],
    ];

    $contentScanner = $this->createMock(UtilikitContentScanner::class);
    $contentScanner->expects($this->once())
      ->method('validateUtilityClasses')
      ->with(['uk-pd--20', 'uk-mg--10'])
      ->willReturn(['uk-pd--20', 'uk-mg--10']);

    $stateManager = $this->createMock(UtilikitStateManager::class);
    $stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn(['uk-pd--20', 'uk-mg--10']);

    $this->serviceProvider->expects($this->once())
      ->method('getContentScanner')
      ->willReturn($contentScanner);
    $this->serviceProvider->expects($this->once())
      ->method('getStateManager')
      ->willReturn($stateManager);
    $this->serviceProvider->expects($this->never())
      ->method('updateCssAndFile');
    $this->serviceProvider->expects($this->never())
      ->method('getCacheManager');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with invalid queue data (missing classes).
   *
   * @covers ::processItem
   */
  public function testProcessItemMissingClasses(): void {
    $data = ['user_id' => 1];

    $this->serviceProvider->expects($this->never())
      ->method('getContentScanner');

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Invalid queue item data');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem when classes is not an array.
   *
   * @covers ::processItem
   */
  public function testProcessItemClassesNotArray(): void {
    $data = ['classes' => 'not-array'];

    $this->serviceProvider->expects($this->never())
      ->method('getContentScanner');

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Invalid queue item data');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem when no valid utility classes after validation.
   *
   * @covers ::processItem
   */
  public function testProcessItemNoValidClasses(): void {
    $data = ['classes' => ['uk-pd--20', 'invalid']];

    $contentScanner = $this->createMock(UtilikitContentScanner::class);
    $contentScanner->expects($this->once())
      ->method('validateUtilityClasses')
      ->with(['uk-pd--20', 'invalid'])
      ->willReturn([]);

    $this->serviceProvider->expects($this->once())
      ->method('getContentScanner')
      ->willReturn($contentScanner);
    $this->serviceProvider->expects($this->never())
      ->method('getStateManager');
    $this->serviceProvider->expects($this->never())
      ->method('updateCssAndFile');

    $this->logger->expects($this->once())
      ->method('warning')
      ->with('No valid utility classes found in queue item');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem when updateCssAndFile returns FALSE.
   *
   * @covers ::processItem
   */
  public function testProcessItemUpdateFails(): void {
    $data = ['classes' => ['uk-pd--20']];

    $contentScanner = $this->createMock(UtilikitContentScanner::class);
    $contentScanner->expects($this->once())
      ->method('validateUtilityClasses')
      ->willReturn(['uk-pd--20']);

    $stateManager = $this->createMock(UtilikitStateManager::class);
    $stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn([]);

    $this->serviceProvider->expects($this->once())
      ->method('getContentScanner')
      ->willReturn($contentScanner);
    $this->serviceProvider->expects($this->once())
      ->method('getStateManager')
      ->willReturn($stateManager);
    $this->serviceProvider->expects($this->once())
      ->method('updateCssAndFile')
      ->willReturn(FALSE);
    $this->serviceProvider->expects($this->never())
      ->method('getCacheManager');

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Failed to update CSS file during queue processing');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem with exception during processing.
   *
   * @covers ::processItem
   */
  public function testProcessItemErrorHandling(): void {
    $data = ['classes' => ['uk-pd--20']];

    $contentScanner = $this->createMock(UtilikitContentScanner::class);
    $contentScanner->expects($this->once())
      ->method('validateUtilityClasses')
      ->willThrowException(new \Exception('Service error'));

    $this->serviceProvider->expects($this->once())
      ->method('getContentScanner')
      ->willReturn($contentScanner);

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Failed to process CSS update: @message', $this->anything());

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Service error');

    $this->queueWorker->processItem($data);
  }

  /**
   * Tests processItem does not clear cache when updateCssAndFile fails.
   *
   * @covers ::processItem
   */
  public function testProcessItemNoCacheClearOnUpdateFailure(): void {
    $data = ['classes' => ['uk-pd--20']];

    $contentScanner = $this->createMock(UtilikitContentScanner::class);
    $contentScanner->expects($this->once())
      ->method('validateUtilityClasses')
      ->willReturn(['uk-pd--20']);

    $stateManager = $this->createMock(UtilikitStateManager::class);
    $stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn([]);

    $this->serviceProvider->expects($this->once())
      ->method('getContentScanner')
      ->willReturn($contentScanner);
    $this->serviceProvider->expects($this->once())
      ->method('getStateManager')
      ->willReturn($stateManager);
    $this->serviceProvider->expects($this->once())
      ->method('updateCssAndFile')
      ->willReturn(FALSE);
    $this->serviceProvider->expects($this->never())
      ->method('getCacheManager');

    $this->cacheManager->expects($this->never())
      ->method('clearCssCaches');

    $this->queueWorker->processItem($data);
  }

}
