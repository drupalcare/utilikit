<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\utilikit\Service\UtilikitConstants;

/**
 * Tests the UtiliKit queue worker functionality.
 *
 * @group utilikit
 */
class UtilikitQueueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'utilikit',
  ];

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The queue worker plugin.
   *
   * @var \Drupal\utilikit\Plugin\QueueWorker\UtilikitCssProcessor
   */
  protected $queueWorker;

  /**
   * The service provider.
   *
   * @var \Drupal\utilikit\Service\UtilikitServiceProvider
   */
  protected $serviceProvider;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install config.
    $this->installConfig(['utilikit']);

    // Get services.
    $this->queue = $this->container->get('queue')->get(UtilikitConstants::QUEUE_CSS_PROCESSOR);
    $this->serviceProvider = $this->container->get('utilikit.service_provider');
    $this->state = $this->container->get('state');
    $this->logger = $this->container->get('logger.channel.utilikit');
    $this->queueWorkerManager = $this->container->get('plugin.manager.queue_worker');

    // Create queue worker instance.
    $this->queueWorker = $this->queueWorkerManager->createInstance('utilikit_css_processor');

    // Set rendering mode to static.
    $config = $this->container->get('config.factory')->getEditable('utilikit.settings');
    $config->set('rendering_mode', 'static');
    $config->save();
  }

  /**
   * Tests basic queue item processing.
   */
  public function testBasicQueueProcessing() {
    // Clear existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create queue item.
    $data = [
      'classes' => ['uk-pd--20', 'uk-mg--10'],
      'entity_type' => 'node',
      'entity_id' => 1,
      'timestamp' => time(),
    ];

    $this->queue->createItem($data);

    // Verify item was queued.
    $this->assertEquals(1, $this->queue->numberOfItems());

    // Process the item.
    $item = $this->queue->claimItem();
    $this->assertNotFalse($item);

    // Process using worker.
    $this->queueWorker->processItem($item->data);

    // Verify classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--20', $knownClasses);
    $this->assertContains('uk-mg--10', $knownClasses);

    // Verify CSS was generated.
    $css = $this->state->get(UtilikitConstants::STATE_GENERATED_CSS);
    $this->assertNotEmpty($css);
    $this->assertStringContainsString('.uk-pd--20', $css);

    // Delete the item.
    $this->queue->deleteItem($item);
    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Tests processing with invalid data.
   */
  public function testInvalidQueueData() {
    // Test missing classes.
    $invalidData = [
      'entity_type' => 'node',
      'timestamp' => time(),
    ];

    $this->queue->createItem($invalidData);
    $item = $this->queue->claimItem();

    // Process should handle gracefully.
    $this->queueWorker->processItem($item->data);

    // No classes should be added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertEmpty($knownClasses);

    // Clean up.
    $this->queue->deleteItem($item);
  }

  /**
   * Tests processing with invalid classes.
   */
  public function testInvalidClasses() {
    // Create item with invalid classes.
    $data = [
      'classes' => ['invalid-class', 'uk-invalid--test', 'uk-pd--20'],
      'timestamp' => time(),
    ];

    $this->queue->createItem($data);
    $item = $this->queue->claimItem();

    // Process item.
    $this->queueWorker->processItem($item->data);

    // Only valid class should be added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-pd--20', $knownClasses);
    $this->assertNotContains('invalid-class', $knownClasses);
    $this->assertNotContains('uk-invalid--test', $knownClasses);

    // Clean up.
    $this->queue->deleteItem($item);
  }

  /**
   * Tests processing multiple queue items.
   */
  public function testMultipleQueueItems() {
    // Clear existing classes.
    $this->state->delete(UtilikitConstants::STATE_KNOWN_CLASSES);

    // Create multiple queue items.
    $items = [
      ['classes' => ['uk-pd--20', 'uk-mg--10']],
      ['classes' => ['uk-bg--ff0000', 'uk-tc--ffffff']],
      ['classes' => ['uk-dp--flex', 'uk-jc--center']],
    ];

    foreach ($items as $data) {
      $data['timestamp'] = time();
      $this->queue->createItem($data);
    }

    $this->assertEquals(3, $this->queue->numberOfItems());

    // Process all items.
    while ($item = $this->queue->claimItem()) {
      $this->queueWorker->processItem($item->data);
      $this->queue->deleteItem($item);
    }

    // Verify all classes were processed.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $expectedClasses = [
      'uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000',
      'uk-tc--ffffff', 'uk-dp--flex', 'uk-jc--center',
    ];

    foreach ($expectedClasses as $class) {
      $this->assertContains($class, $knownClasses);
    }
  }

  /**
   * Tests queue item with duplicate classes.
   */
  public function testDuplicateClasses() {
    // Add some existing classes.
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, ['uk-pd--20', 'uk-mg--10']);

    // Queue item with duplicates.
    $data = [
      'classes' => ['uk-pd--20', 'uk-bg--ff0000', 'uk-mg--10'],
      'timestamp' => time(),
    ];

    $this->queue->createItem($data);
    $item = $this->queue->claimItem();

    // Process item.
    $this->queueWorker->processItem($item->data);

    // Verify new class was added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertContains('uk-bg--ff0000', $knownClasses);

    // Verify no duplicates.
    $classCounts = array_count_values($knownClasses);
    foreach ($classCounts as $count) {
      $this->assertEquals(1, $count);
    }

    // Clean up.
    $this->queue->deleteItem($item);
  }

  /**
   * Tests queue processing with existing CSS.
   */
  public function testQueueWithExistingCss() {
    // Set up existing CSS.
    $existingCss = '.uk-pd--20 { padding: 20px; }';
    $this->state->set(UtilikitConstants::STATE_GENERATED_CSS, $existingCss);
    $this->state->set(UtilikitConstants::STATE_KNOWN_CLASSES, ['uk-pd--20']);

    // Queue new classes.
    $data = [
      'classes' => ['uk-mg--20'],
      'timestamp' => time(),
    ];

    $this->queue->createItem($data);
    $item = $this->queue->claimItem();

    // Process item.
    $this->queueWorker->processItem($item->data);

    // Verify CSS was updated.
    $css = $this->state->get(UtilikitConstants::STATE_GENERATED_CSS);
    $this->assertStringContainsString('.uk-pd--20', $css);
    $this->assertStringContainsString('.uk-mg--20', $css);

    // Clean up.
    $this->queue->deleteItem($item);
  }

  /**
   * Tests queue error handling.
   */
  public function testQueueErrorHandling() {
    // Create a mock service provider that throws exception.
    $mockServiceProvider = $this->createMock('\Drupal\utilikit\Service\UtilikitServiceProvider');
    $mockServiceProvider->method('getContentScanner')
      ->willThrowException(new \Exception('Test exception'));

    // Replace service.
    $this->container->set('utilikit.service_provider', $mockServiceProvider);

    // Create queue item.
    $data = [
      'classes' => ['uk-pd--20'],
      'timestamp' => time(),
    ];

    $this->queue->createItem($data);
    $item = $this->queue->claimItem();

    // Processing should throw exception.
    $this->expectException(\Exception::class);
    $this->queueWorker->processItem($item->data);
  }

  /**
   * Tests cron processing.
   */
  public function testCronProcessing() {
    // Create multiple items.
    for ($i = 0; $i < 5; $i++) {
      $this->queue->createItem([
        'classes' => ['uk-pd--' . ($i * 10)],
        'timestamp' => time(),
      ]);
    }

    $this->assertEquals(5, $this->queue->numberOfItems());

    // Get cron time limit from annotation.
    $definition = $this->queueWorkerManager->getDefinition('utilikit_css_processor');
    $timeLimit = $definition['cron']['time'] ?? 30;

    // Simulate cron run.
    $endTime = time() + $timeLimit;
    $processed = 0;

    while (time() < $endTime && ($item = $this->queue->claimItem())) {
      try {
        $this->queueWorker->processItem($item->data);
        $this->queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        // Release item on error.
        $this->queue->releaseItem($item);
        break;
      }
    }

    // Should have processed at least some items.
    $this->assertGreaterThan(0, $processed);
    $this->assertLessThan(5, $this->queue->numberOfItems());
  }

  /**
   * Tests queue item metadata.
   */
  public function testQueueItemMetadata() {
    // Create item with full metadata.
    $data = [
      'classes' => ['uk-pd--30'],
      'entity_type' => 'node',
      'entity_id' => 123,
      'timestamp' => time(),
      'source' => 'entity_save',
    ];

    $this->queue->createItem($data);
    $item = $this->queue->claimItem();

    // Verify all data is preserved.
    $this->assertEquals($data['classes'], $item->data['classes']);
    $this->assertEquals($data['entity_type'], $item->data['entity_type']);
    $this->assertEquals($data['entity_id'], $item->data['entity_id']);
    $this->assertEquals($data['timestamp'], $item->data['timestamp']);
    $this->assertEquals($data['source'], $item->data['source']);

    // Clean up.
    $this->queue->deleteItem($item);
  }

  /**
   * Tests queue reliability (claim/release).
   */
  public function testQueueReliability() {
    // Create item.
    $this->queue->createItem([
      'classes' => ['uk-pd--40'],
      'timestamp' => time(),
    ]);

    // Claim item.
    $item1 = $this->queue->claimItem();
    $this->assertNotFalse($item1);

    // Try to claim again (should not get same item)
    $item2 = $this->queue->claimItem(1);
    $this->assertFalse($item2);

    // Release item.
    $this->queue->releaseItem($item1);

    // Should be able to claim again.
    $item3 = $this->queue->claimItem();
    $this->assertNotFalse($item3);
    $this->assertEquals($item1->data['classes'], $item3->data['classes']);

    // Clean up.
    $this->queue->deleteItem($item3);
  }

  /**
   * Tests large batch processing.
   */
  public function testLargeBatchProcessing() {
    // Create many items.
    $totalItems = 100;
    for ($i = 0; $i < $totalItems; $i++) {
      $this->queue->createItem([
        'classes' => ['uk-pd--' . $i, 'uk-mg--' . $i],
        'timestamp' => time(),
      ]);
    }

    $this->assertEquals($totalItems, $this->queue->numberOfItems());

    // Process all items.
    $processed = 0;
    $startTime = microtime(TRUE);

    while ($item = $this->queue->claimItem()) {
      $this->queueWorker->processItem($item->data);
      $this->queue->deleteItem($item);
      $processed++;
    }

    $duration = microtime(TRUE) - $startTime;

    // Verify all processed.
    $this->assertEquals($totalItems, $processed);
    $this->assertEquals(0, $this->queue->numberOfItems());

    // Should complete in reasonable time.
    $this->assertLessThan(30, $duration, 'Large batch should process within 30 seconds');

    // Verify classes were added.
    $knownClasses = $this->state->get(UtilikitConstants::STATE_KNOWN_CLASSES, []);
    $this->assertGreaterThanOrEqual($totalItems * 2, count($knownClasses));
  }

  /**
   * Tests empty queue handling.
   */
  public function testEmptyQueue() {
    // Ensure queue is empty.
    while ($item = $this->queue->claimItem()) {
      $this->queue->deleteItem($item);
    }

    $this->assertEquals(0, $this->queue->numberOfItems());

    // Try to claim from empty queue.
    $item = $this->queue->claimItem();
    $this->assertFalse($item);
  }

  /**
   * Tests queue persistence.
   */
  public function testQueuePersistence() {
    // Create items.
    $this->queue->createItem(['classes' => ['uk-pd--50'], 'timestamp' => time()]);
    $this->queue->createItem(['classes' => ['uk-mg--50'], 'timestamp' => time()]);

    $count = $this->queue->numberOfItems();

    // Create new queue instance (simulating new request)
    $newQueue = $this->container->get('queue')->get(UtilikitConstants::QUEUE_CSS_PROCESSOR);

    // Should have same items.
    $this->assertEquals($count, $newQueue->numberOfItems());

    // Clean up.
    while ($item = $newQueue->claimItem()) {
      $newQueue->deleteItem($item);
    }
  }

}
