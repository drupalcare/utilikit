<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitCacheManagerInterface;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitTestCssGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitServiceProvider
 */
final class UtilikitServiceProviderTest extends UnitTestCase {

  /**
   * @var \Drupal\utilikit\Service\UtilikitServiceProvider
   */
  protected UtilikitServiceProvider $serviceProvider;

  /**
   * @var \Drupal\utilikit\Service\UtilikitCssGenerator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitCssGenerator&MockObject $cssGenerator;

  /**
   * @var \Drupal\utilikit\Service\UtilikitFileManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitFileManager&MockObject $fileManager;

  /**
   * @var \Drupal\utilikit\Service\UtilikitContentScanner|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitContentScanner&MockObject $contentScanner;

  /**
   * @var \Drupal\utilikit\Service\UtilikitStateManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitStateManager&MockObject $stateManager;

  /**
   * @var \Drupal\utilikit\Service\UtilikitCacheManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitCacheManagerInterface&MockObject $cacheManager;

  /**
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * @var \Drupal\utilikit\Service\UtilikitTestCssGenerator|\PHPUnit\Framework\MockObject\MockObject|null
   */
  protected ?UtilikitTestCssGenerator $testCssGenerator = null;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cssGenerator = $this->createMock(UtilikitCssGenerator::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->cacheManager = $this->createMock(UtilikitCacheManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->testCssGenerator = $this->createMock(UtilikitTestCssGenerator::class);

    $this->serviceProvider = new UtilikitServiceProvider(
      $this->cssGenerator,
      $this->fileManager,
      $this->contentScanner,
      $this->stateManager,
      $this->cacheManager,
      $this->logger,
      $this->testCssGenerator,
    );
  }

  /**
   * @covers ::__construct
   * @covers ::getCssGenerator
   * @covers ::getFileManager
   * @covers ::getContentScanner
   * @covers ::getStateManager
   * @covers ::getCacheManager
   * @covers ::getTestCssGenerator
   */
  public function testGettersReturnInjectedServices(): void {
    $this->assertSame($this->cssGenerator, $this->serviceProvider->getCssGenerator());
    $this->assertSame($this->fileManager, $this->serviceProvider->getFileManager());
    $this->assertSame($this->contentScanner, $this->serviceProvider->getContentScanner());
    $this->assertSame($this->stateManager, $this->serviceProvider->getStateManager());
    $this->assertSame($this->cacheManager, $this->serviceProvider->getCacheManager());
    $this->assertSame($this->testCssGenerator, $this->serviceProvider->getTestCssGenerator());
  }

  /**
   * @covers ::switchToStaticMode
   */
  public function testSwitchToStaticModeWithKnownClasses(): void {
    $scanResult = [
      'classes' => ['uk-m--10'],
      'scanned_count' => 10,
    ];

    $this->stateManager
      ->expects($this->once())
      ->method('clearUtilikitData');

    $this->fileManager
      ->expects($this->once())
      ->method('cleanupStaticFiles');

    $this->contentScanner
      ->expects($this->once())
      ->method('scanAllContent')
      ->willReturn($scanResult);

    $this->stateManager
      ->expects($this->once())
      ->method('setKnownClasses')
      ->with($scanResult['classes']);

    $this->cssGenerator
      ->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($scanResult['classes'])
      ->willReturn('/* utilikit css */');

    $this->stateManager
      ->expects($this->once())
      ->method('setGeneratedCss')
      ->with('/* utilikit css */');

    $this->fileManager
      ->expects($this->once())
      ->method('ensureStaticCssFile')
      ->willReturn(true);

    $result = $this->serviceProvider->switchToStaticMode();

    $this->assertSame(1, $result['classes_count']);
    $this->assertSame(10, $result['scanned_count']);
  }

  /**
   * @covers ::switchToStaticMode
   */
  public function testSwitchToStaticModeWithoutClasses(): void {
    $scanResult = [
      'classes' => [],
      'scanned_count' => 5,
    ];

    $this->stateManager
      ->expects($this->once())
      ->method('clearUtilikitData');

    $this->fileManager
      ->expects($this->once())
      ->method('cleanupStaticFiles');

    $this->contentScanner
      ->expects($this->once())
      ->method('scanAllContent')
      ->willReturn($scanResult);

    $this->stateManager
      ->expects($this->once())
      ->method('setGeneratedCss')
      ->with($this->stringContains('Utilikit Static CSS'));

    $this->fileManager
      ->expects($this->never())
      ->method('ensureStaticCssFile');

    $result = $this->serviceProvider->switchToStaticMode();

    $this->assertSame(0, $result['classes_count']);
    $this->assertSame(5, $result['scanned_count']);
  }

  /**
   * @covers ::switchToInlineMode
   */
  public function testSwitchToInlineMode(): void {
    $known = ['uk-m--10', 'uk-p--4'];

    $this->fileManager
      ->expects($this->once())
      ->method('cleanupStaticFiles');

    $this->stateManager
      ->expects($this->once())
      ->method('updateCssTimestamp');

    $this->cacheManager
      ->expects($this->once())
      ->method('clearAllCaches');

    $this->stateManager
      ->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn($known);

    $count = $this->serviceProvider->switchToInlineMode();

    $this->assertSame(count($known), $count);
  }

  /**
   * @covers ::updateCssAndFile
   */
  public function testUpdateCssAndFileWithValidClasses(): void {
    $input = ['uk-m--10', 'uk-p--4'];
    $valid = ['uk-m--10', 'uk-p--4'];
    $all = ['uk-m--10', 'uk-p--4', 'uk-gap--2'];

    $this->contentScanner
      ->expects($this->once())
      ->method('validateUtilityClasses')
      ->with($input)
      ->willReturn($valid);

    $this->stateManager
      ->expects($this->once())
      ->method('addKnownClasses')
      ->with($valid)
      ->willReturn($all);

    $this->cssGenerator
      ->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($all)
      ->willReturn('/* css */');

    $this->stateManager
      ->expects($this->once())
      ->method('setGeneratedCss')
      ->with('/* css */');

    $this->fileManager
      ->expects($this->once())
      ->method('ensureStaticCssFile')
      ->willReturn(true);

    $this->stateManager
      ->expects($this->once())
      ->method('updateCssTimestamp');

    $this->cacheManager
      ->expects($this->once())
      ->method('clearCssCaches');

    $result = $this->serviceProvider->updateCssAndFile($input);

    $this->assertTrue($result);
  }

  /**
   * @covers ::updateCssAndFile
   */
  public function testUpdateCssAndFileWithEmptyInputReturnsFalse(): void {
    $this->contentScanner
      ->expects($this->never())
      ->method('validateUtilityClasses');

    $this->assertFalse($this->serviceProvider->updateCssAndFile([]));
  }

  /**
   * @covers ::regenerateStaticCss
   */
  public function testRegenerateStaticCssWithNoKnownClasses(): void {
    $this->stateManager
      ->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn([]);

    $this->logger
      ->expects($this->once())
      ->method('info')
      ->with($this->stringContains('No known classes'));

    $this->assertFalse($this->serviceProvider->regenerateStaticCss());
  }

  /**
   * @covers ::regenerateStaticCss
   */
  public function testRegenerateStaticCssWithKnownClasses(): void {
    $known = ['uk-m--10'];

    $this->stateManager
      ->expects($this->once())
      ->method('getKnownClasses')
      ->willReturn($known);

    $this->cssGenerator
      ->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($known)
      ->willReturn('/* regenerated css */');

    $this->stateManager
      ->expects($this->once())
      ->method('setGeneratedCss')
      ->with('/* regenerated css */');

    $this->fileManager
      ->expects($this->once())
      ->method('ensureStaticCssFile')
      ->willReturn(true);

    $this->stateManager
      ->expects($this->once())
      ->method('updateCssTimestamp');

    $this->cacheManager
      ->expects($this->once())
      ->method('clearCssCaches');

    $this->assertTrue($this->serviceProvider->regenerateStaticCss());
  }

}
