<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtiliKitServiceProvider;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtiliKitCacheManagerInterface;

/**
 * Tests UtiliKitServiceProvider facade service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtiliKitServiceProvider
 */
class UtiliKitServiceProviderTest extends UnitTestCase {

  /**
   * The service provider under test.
   *
   * @var \Drupal\utilikit\Service\UtiliKitServiceProvider
   */
  protected $serviceProvider;

  /**
   * Mock CSS generator service.
   *
   * @var \Drupal\utilikit\Service\UtilikitCssGenerator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cssGenerator;

  /**
   * Mock file manager service.
   *
   * @var \Drupal\utilikit\Service\UtilikitFileManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileManager;

  /**
   * Mock content scanner service.
   *
   * @var \Drupal\utilikit\Service\UtilikitContentScanner|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $contentScanner;

  /**
   * Mock state manager service.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stateManager;

  /**
   * Mock cache manager service.
   *
   * @var \Drupal\utilikit\Service\UtiliKitCacheManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cssGenerator = $this->createMock(UtilikitCssGenerator::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->cacheManager = $this->createMock(UtiliKitCacheManagerInterface::class);

    $this->serviceProvider = new UtiliKitServiceProvider(
      $this->cssGenerator,
      $this->fileManager,
      $this->contentScanner,
      $this->stateManager,
      $this->cacheManager
    );
  }

  /**
   * Tests service construction.
   *
   * @covers ::__construct
   */
  public function testConstruct() {
    $provider = new UtiliKitServiceProvider(
      $this->cssGenerator,
      $this->fileManager,
      $this->contentScanner,
      $this->stateManager,
      $this->cacheManager
    );
    $this->assertInstanceOf(UtiliKitServiceProvider::class, $provider);
  }

  /**
   * Tests getCssGenerator returns injected service.
   *
   * @covers ::getCssGenerator
   */
  public function testGetCssGenerator() {
    $result = $this->serviceProvider->getCssGenerator();
    $this->assertSame($this->cssGenerator, $result);
  }

  /**
   * Tests getFileManager returns injected service.
   *
   * @covers ::getFileManager
   */
  public function testGetFileManager() {
    $result = $this->serviceProvider->getFileManager();
    $this->assertSame($this->fileManager, $result);
  }

  /**
   * Tests getContentScanner returns injected service.
   *
   * @covers ::getContentScanner
   */
  public function testGetContentScanner() {
    $result = $this->serviceProvider->getContentScanner();
    $this->assertSame($this->contentScanner, $result);
  }

  /**
   * Tests getStateManager returns injected service.
   *
   * @covers ::getStateManager
   */
  public function testGetStateManager() {
    $result = $this->serviceProvider->getStateManager();
    $this->assertSame($this->stateManager, $result);
  }

  /**
   * Tests getCacheManager returns injected service.
   *
   * @covers ::getCacheManager
   */
  public function testGetCacheManager() {
    $result = $this->serviceProvider->getCacheManager();
    $this->assertSame($this->cacheManager, $result);
  }

  /**
   * Tests updateCssAndFile coordinates between services.
   *
   * @covers ::updateCssAndFile
   */
  public function testUpdateCssAndFile() {
    $testClasses = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'];
    $generatedCss = '.uk-pd--20 { padding: 20px; }';

    // Expect CSS generation.
    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with($testClasses)
      ->willReturn($generatedCss);

    // Expect state update.
    $this->stateManager->expects($this->once())
      ->method('addClasses')
      ->with($testClasses);

    $this->stateManager->expects($this->once())
      ->method('setCss')
      ->with($generatedCss);

    // Expect file update.
    $this->fileManager->expects($this->once())
      ->method('updateCssFile');

    // Expect cache invalidation.
    $this->cacheManager->expects($this->once())
      ->method('clearCssCaches');

    $result = $this->serviceProvider->updateCssAndFile($testClasses);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('classes_processed', $result);
    $this->assertArrayHasKey('css_generated', $result);
    $this->assertEquals(count($testClasses), $result['classes_processed']);
    $this->assertTrue($result['css_generated']);
  }

  /**
   * Tests updateCssAndFile handles empty classes array.
   *
   * @covers ::updateCssAndFile
   */
  public function testUpdateCssAndFileEmptyClasses() {
    $emptyClasses = [];

    // Should not call any services for empty array.
    $this->cssGenerator->expects($this->never())
      ->method('generateCss');

    $this->stateManager->expects($this->never())
      ->method('addClasses');

    $this->fileManager->expects($this->never())
      ->method('updateCssFile');

    $result = $this->serviceProvider->updateCssAndFile($emptyClasses);

    $this->assertIsArray($result);
    $this->assertEquals(0, $result['classes_processed']);
    $this->assertFalse($result['css_generated']);
  }

  /**
   * Tests scanAndUpdate performs full content scan and update.
   *
   * @covers ::scanAndUpdate
   */
  public function testScanAndUpdate() {
    $foundClasses = ['uk-pd--20', 'uk-mg--auto', 'uk-bg--007bff'];
    $scanResult = [
      'classes' => $foundClasses,
      'scanned_count' => 150,
    ];

    // Expect content scan.
    $this->contentScanner->expects($this->once())
      ->method('scanAllContent')
      ->willReturn($scanResult);

    // Expect the rest of the update process.
    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with($foundClasses)
      ->willReturn('.test { css: here; }');

    $this->stateManager->expects($this->once())
      ->method('addClasses')
      ->with($foundClasses);

    $result = $this->serviceProvider->scanAndUpdate();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('scanned_count', $result);
    $this->assertArrayHasKey('classes_found', $result);
    $this->assertArrayHasKey('css_updated', $result);
    $this->assertEquals(150, $result['scanned_count']);
    $this->assertEquals(count($foundClasses), $result['classes_found']);
    $this->assertTrue($result['css_updated']);
  }

  /**
   * Tests processNewClasses identifies and processes only new classes.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClasses() {
    $inputClasses = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'];
    // Already known.
    $knownClasses = ['uk-pd--20'];
    // New ones.
    $newClasses = ['uk-mg--10', 'uk-bg--ff0000'];

    // Mock state manager to return known classes.
    $this->stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn($knownClasses);

    // Should only process new classes.
    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with($newClasses)
      ->willReturn('.new { css: rules; }');

    $this->stateManager->expects($this->once())
      ->method('addClasses')
      ->with($newClasses);

    $result = $this->serviceProvider->processNewClasses($inputClasses);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('new_classes', $result);
    $this->assertArrayHasKey('classes_processed', $result);
    $this->assertEquals($newClasses, $result['new_classes']);
    $this->assertEquals(count($newClasses), $result['classes_processed']);
  }

  /**
   * Tests processNewClasses handles case where no new classes found.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClassesNoNewClasses() {
    $inputClasses = ['uk-pd--20', 'uk-mg--10'];
    // All already known.
    $knownClasses = ['uk-pd--20', 'uk-mg--10'];

    $this->stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn($knownClasses);

    // Should not call CSS generation for empty new classes.
    $this->cssGenerator->expects($this->never())
      ->method('generateCss');

    $result = $this->serviceProvider->processNewClasses($inputClasses);

    $this->assertIsArray($result);
    $this->assertEquals([], $result['new_classes']);
    $this->assertEquals(0, $result['classes_processed']);
  }

  /**
   * Tests clearAllData coordinates cleanup across all services.
   *
   * @covers ::clearAllData
   */
  public function testClearAllData() {
    // Expect state cleanup.
    $this->stateManager->expects($this->once())
      ->method('clearUtiliKitData');

    // Expect file cleanup.
    $this->fileManager->expects($this->once())
      ->method('cleanupStaticFiles');

    // Expect cache cleanup.
    $this->cacheManager->expects($this->once())
      ->method('clearAllCaches');

    $result = $this->serviceProvider->clearAllData();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('state_cleared', $result);
    $this->assertArrayHasKey('files_cleaned', $result);
    $this->assertArrayHasKey('caches_cleared', $result);
    $this->assertTrue($result['state_cleared']);
    $this->assertTrue($result['files_cleaned']);
    $this->assertTrue($result['caches_cleared']);
  }

  /**
   * Tests getSystemStatus returns status from all services.
   *
   * @covers ::getSystemStatus
   */
  public function testGetSystemStatus() {
    $knownClasses = ['uk-pd--20', 'uk-mg--10'];
    $cssContent = '.uk-pd--20 { padding: 20px; }';

    $this->stateManager->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn($knownClasses);

    $this->stateManager->expects($this->once())
      ->method('getCss')
      ->willReturn($cssContent);

    $this->stateManager->expects($this->once())
      ->method('getCssTimestamp')
      ->willReturn(1234567890);

    $this->fileManager->expects($this->once())
      ->method('getCssFileUrl')
      ->willReturn('https://example.com/css/generated.css');

    $result = $this->serviceProvider->getSystemStatus();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('known_classes_count', $result);
    $this->assertArrayHasKey('css_generated', $result);
    $this->assertArrayHasKey('last_updated', $result);
    $this->assertArrayHasKey('css_file_url', $result);
    $this->assertEquals(count($knownClasses), $result['known_classes_count']);
    $this->assertTrue($result['css_generated']);
    $this->assertEquals(1234567890, $result['last_updated']);
    $this->assertEquals('https://example.com/css/generated.css', $result['css_file_url']);
  }

  /**
   * Tests that all service getters work after construction.
   */
  public function testAllServiceGetters() {
    $this->assertSame($this->cssGenerator, $this->serviceProvider->getCssGenerator());
    $this->assertSame($this->fileManager, $this->serviceProvider->getFileManager());
    $this->assertSame($this->contentScanner, $this->serviceProvider->getContentScanner());
    $this->assertSame($this->stateManager, $this->serviceProvider->getStateManager());
    $this->assertSame($this->cacheManager, $this->serviceProvider->getCacheManager());
  }

}
