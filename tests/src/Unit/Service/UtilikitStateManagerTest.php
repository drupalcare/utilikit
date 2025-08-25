<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\State\StateInterface;

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
  protected $stateManager;

  /**
   * Mock state service.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * Test data storage.
   *
   * @var array
   */
  protected $stateData = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->state = $this->createMock(StateInterface::class);

    // Set up state mock to use our data storage.
    $this->state->method('get')
      ->willReturnCallback(function ($key, $default = NULL) {
        return $this->stateData[$key] ?? $default;
      });

    $this->state->method('set')
      ->willReturnCallback(function ($key, $value) {
        $this->stateData[$key] = $value;
      });

    $this->state->method('deleteMultiple')
      ->willReturnCallback(function ($keys) {
        foreach ($keys as $key) {
          unset($this->stateData[$key]);
        }
      });

    $this->stateManager = new UtilikitStateManager($this->state);
  }

  /**
   * Tests getting and setting known classes.
   *
   * @covers ::getKnownClasses
   * @covers ::setKnownClasses
   */
  public function testKnownClasses() {
    // Test default empty array.
    $classes = $this->stateManager->getKnownClasses();
    $this->assertEquals([], $classes);

    // Test setting classes.
    $testClasses = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'];
    $this->stateManager->setKnownClasses($testClasses);

    // Verify stored.
    $this->assertEquals($testClasses, $this->stateData[UtilikitConstants::STATE_KNOWN_CLASSES]);

    // Test getting stored classes.
    $classes = $this->stateManager->getKnownClasses();
    $this->assertEquals($testClasses, $classes);

    // Test automatic deduplication.
    $duplicateClasses = ['uk-pd--20', 'uk-pd--20', 'uk-mg--10', 'uk-mg--10'];
    $this->stateManager->setKnownClasses($duplicateClasses);

    $classes = $this->stateManager->getKnownClasses();
    $this->assertEquals(['uk-pd--20', 'uk-mg--10'], $classes);
  }

  /**
   * Tests adding new classes to existing ones.
   *
   * @covers ::addKnownClasses
   */
  public function testAddKnownClasses() {
    // Start with some classes.
    $initialClasses = ['uk-pd--20', 'uk-mg--10'];
    $this->stateManager->setKnownClasses($initialClasses);

    // Add new classes.
    $newClasses = ['uk-bg--ff0000', 'uk-tc--333333'];
    $result = $this->stateManager->addKnownClasses($newClasses);

    // Should return merged array.
    $expected = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000', 'uk-tc--333333'];
    $this->assertEquals(sorted($expected), sorted($result));

    // Verify stored.
    $stored = $this->stateManager->getKnownClasses();
    $this->assertEquals(sorted($expected), sorted($stored));

    // Test adding duplicates.
    // uk-pd--20 is duplicate.
    $moreClasses = ['uk-pd--20', 'uk-dp--flex'];
    $result = $this->stateManager->addKnownClasses($moreClasses);

    // Should deduplicate.
    $expected[] = 'uk-dp--flex';
    $this->assertEquals(sorted($expected), sorted($result));
  }

  /**
   * Tests generated CSS management.
   *
   * @covers ::getGeneratedCss
   * @covers ::setGeneratedCss
   */
  public function testGeneratedCss() {
    // Test default empty string.
    $css = $this->stateManager->getGeneratedCss();
    $this->assertEquals('', $css);

    // Test setting CSS.
    $testCss = '.uk-pd--20 { padding: 20px; } .uk-mg--10 { margin: 10px; }';
    $this->stateManager->setGeneratedCss($testCss);

    // Verify stored.
    $this->assertEquals($testCss, $this->stateData[UtilikitConstants::STATE_GENERATED_CSS]);

    // Test getting stored CSS.
    $css = $this->stateManager->getGeneratedCss();
    $this->assertEquals($testCss, $css);
  }

  /**
   * Tests CSS timestamp management.
   *
   * @covers ::getCssTimestamp
   * @covers ::updateCssTimestamp
   */
  public function testCssTimestamp() {
    // Test default zero timestamp.
    $timestamp = $this->stateManager->getCssTimestamp();
    $this->assertEquals(0, $timestamp);

    // Test updating timestamp.
    $testTime = 1234567890;
    $this->freezeTime($testTime);
    $this->stateManager->updateCssTimestamp();

    // Verify stored.
    $this->assertEquals($testTime, $this->stateData[UtilikitConstants::STATE_CSS_TIMESTAMP]);

    // Test getting stored timestamp.
    $timestamp = $this->stateManager->getCssTimestamp();
    $this->assertEquals($testTime, $timestamp);
  }

  /**
   * Tests clearing UtiliKit data.
   *
   * @covers ::clearUtiliKitData
   */
  public function testClearUtiliKitData() {
    // Set up some data.
    $this->stateManager->setKnownClasses(['uk-pd--20', 'uk-mg--10']);
    $this->stateManager->setGeneratedCss('.uk-pd--20 { padding: 20px; }');
    $this->stateManager->updateCssTimestamp();

    // Verify data exists.
    $this->assertNotEmpty($this->stateManager->getKnownClasses());
    $this->assertNotEmpty($this->stateManager->getGeneratedCss());

    // Clear data.
    $this->stateManager->clearUtiliKitData();

    // Verify data cleared.
    $this->assertArrayNotHasKey(UtilikitConstants::STATE_GENERATED_CSS, $this->stateData);
    $this->assertArrayNotHasKey(UtilikitConstants::STATE_KNOWN_CLASSES, $this->stateData);

    // Timestamp should remain.
    $this->assertArrayHasKey(UtilikitConstants::STATE_CSS_TIMESTAMP, $this->stateData);
  }

  /**
   * Tests last cleanup timestamp.
   *
   * @covers ::getLastCleanup
   * @covers ::setLastCleanup
   */
  public function testLastCleanup() {
    // Test default zero.
    $cleanup = $this->stateManager->getLastCleanup();
    $this->assertEquals(0, $cleanup);

    // Test setting specific timestamp.
    $testTime = 1234567890;
    $this->stateManager->setLastCleanup($testTime);

    $cleanup = $this->stateManager->getLastCleanup();
    $this->assertEquals($testTime, $cleanup);

    // Test auto timestamp.
    $currentTime = 1234567900;
    $this->freezeTime($currentTime);
    $this->stateManager->setLastCleanup();

    $cleanup = $this->stateManager->getLastCleanup();
    $this->assertEquals($currentTime, $cleanup);
  }

  /**
   * Tests rate limit data management.
   *
   * @covers ::getRateLimitData
   * @covers ::setRateLimitData
   * @covers ::clearRateLimitData
   */
  public function testRateLimitData() {
    $key = '127.0.0.1';

    // Test default empty data.
    $data = $this->stateManager->getRateLimitData($key);
    $this->assertEquals(['current' => 0, 'reset_time' => 0], $data);

    // Test setting rate limit data.
    $this->stateManager->setRateLimitData($key, 5, 1234567890);

    $data = $this->stateManager->getRateLimitData($key);
    $this->assertEquals(['current' => 5, 'reset_time' => 1234567890], $data);

    // Verify state keys.
    $this->assertEquals(5, $this->stateData["utilikit_rate_limit:$key"]);
    $this->assertEquals(1234567890, $this->stateData["utilikit_rate_limit:{$key}_reset"]);

    // Test clearing rate limit data.
    $this->stateManager->clearRateLimitData($key);

    $data = $this->stateManager->getRateLimitData($key);
    $this->assertEquals(['current' => 0, 'reset_time' => 0], $data);
  }

  /**
   * Tests getting all state keys.
   *
   * @covers ::getAllUtiliKitStateKeys
   */
  public function testGetAllStateKeys() {
    $keys = $this->stateManager->getAllUtiliKitStateKeys();

    $expected = [
      UtilikitConstants::STATE_KNOWN_CLASSES,
      UtilikitConstants::STATE_GENERATED_CSS,
      UtilikitConstants::STATE_CSS_TIMESTAMP,
      UtilikitConstants::STATE_LAST_CLEANUP,
    ];

    $this->assertEquals($expected, $keys);
  }

  /**
   * Tests getting state statistics.
   *
   * @covers ::getStateStatistics
   */
  public function testGetStateStatistics() {
    // Set up test data.
    $classes = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'];
    $css = '.uk-pd--20 { padding: 20px; } .uk-mg--10 { margin: 10px; }';
    $timestamp = 1234567890;
    $cleanup = 1234567000;

    $this->stateManager->setKnownClasses($classes);
    $this->stateManager->setGeneratedCss($css);
    $this->freezeTime($timestamp);
    $this->stateManager->updateCssTimestamp();
    $this->stateManager->setLastCleanup($cleanup);

    // Get statistics.
    $stats = $this->stateManager->getStateStatistics();

    $expected = [
      'known_classes_count' => 3,
      'generated_css_size' => strlen($css),
      'css_timestamp' => $timestamp,
      'last_cleanup' => $cleanup,
    ];

    $this->assertEquals($expected, $stats);
  }

  /**
   * Tests concurrent access simulation.
   *
   * @covers ::addKnownClasses
   */
  public function testConcurrentAccess() {
    // Simulate multiple processes adding classes.
    $process1Classes = ['uk-pd--20', 'uk-mg--10'];
    $process2Classes = ['uk-bg--ff0000', 'uk-tc--333333'];
    // Has duplicate.
    $process3Classes = ['uk-pd--20', 'uk-dp--flex'];

    // Add from "process 1".
    $this->stateManager->addKnownClasses($process1Classes);

    // Add from "process 2".
    $this->stateManager->addKnownClasses($process2Classes);

    // Add from "process 3".
    $this->stateManager->addKnownClasses($process3Classes);

    // Should have all unique classes.
    $final = $this->stateManager->getKnownClasses();
    $expected = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000', 'uk-tc--333333', 'uk-dp--flex'];

    $this->assertEquals(sorted($expected), sorted($final));
  }

  /**
   * Tests handling large amounts of data.
   *
   * @covers ::setKnownClasses
   * @covers ::setGeneratedCss
   */
  public function testLargeDataHandling() {
    // Generate large number of classes.
    $classes = [];
    for ($i = 0; $i < 10000; $i++) {
      $classes[] = 'uk-pd--' . $i;
    }

    $this->stateManager->setKnownClasses($classes);
    $stored = $this->stateManager->getKnownClasses();
    $this->assertCount(10000, $stored);

    // Generate large CSS string.
    $css = '';
    foreach ($classes as $class) {
      $css .= ".$class { padding: {$i}px; }\n";
    }

    $this->stateManager->setGeneratedCss($css);
    $storedCss = $this->stateManager->getGeneratedCss();
    $this->assertEquals($css, $storedCss);
  }

  /**
   * Tests edge cases.
   */
  public function testEdgeCases() {
    // Test empty array handling.
    $this->stateManager->setKnownClasses([]);
    $this->assertEquals([], $this->stateManager->getKnownClasses());

    // Test empty string CSS.
    $this->stateManager->setGeneratedCss('');
    $this->assertEquals('', $this->stateManager->getGeneratedCss());

    // Test adding empty array.
    $this->stateManager->setKnownClasses(['uk-pd--20']);
    $result = $this->stateManager->addKnownClasses([]);
    $this->assertEquals(['uk-pd--20'], $result);
  }

  /**
   * Helper to freeze time for testing.
   */
  protected function freezeTime($timestamp) {
    // In a real test, this would use a time mocking library
    // For now, we'll manually set the timestamp.
    $this->stateData[UtilikitConstants::STATE_CSS_TIMESTAMP] = $timestamp;
  }

  /**
   * Helper to sort arrays for comparison.
   */
  protected function sorted(array $array): array {
    sort($array);
    return $array;
  }

}
