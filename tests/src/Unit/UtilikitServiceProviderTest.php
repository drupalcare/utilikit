<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitCacheManagerInterface;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitTestCssGenerator;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;

/**
 * Tests for the UtilikitServiceProvider service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitServiceProvider
 */
#[CoversClass(\Drupal\utilikit\Service\UtilikitServiceProvider::class)]
#[Group('utilikit')]
final class UtilikitServiceProviderTest extends UnitTestCase {

  /**
   * The service provider under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitServiceProvider
   */
  protected UtilikitServiceProvider $serviceProvider;

  /**
   * The CSS generator mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitCssGenerator&\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitCssGenerator&Stub $cssGenerator;

  /**
   * The file manager mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitFileManager&\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitFileManager&Stub $fileManager;

  /**
   * The content scanner mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitContentScanner&\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitContentScanner&Stub $contentScanner;

  /**
   * The state manager mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager&\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitStateManager&Stub $stateManager;

  /**
   * The cache manager mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitCacheManagerInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected UtilikitCacheManagerInterface&Stub $cacheManager;

  /**
   * The logger mock.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected LoggerInterface&Stub $logger;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\Stub
   */
  protected ConfigFactoryInterface&Stub $configFactory;

  /**
   * The test CSS generator mock.
   *
   * @var \Drupal\utilikit\Service\UtilikitTestCssGenerator|\PHPUnit\Framework\MockObject\Stub|null
   */
  protected ?UtilikitTestCssGenerator $testCssGenerator = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cssGenerator = $this->createStub(UtilikitCssGenerator::class);
    $this->fileManager = $this->createStub(UtilikitFileManager::class);
    $this->contentScanner = $this->createStub(UtilikitContentScanner::class);
    $this->stateManager = $this->createStub(UtilikitStateManager::class);
    $this->cacheManager = $this->createStub(UtilikitCacheManagerInterface::class);
    $this->logger = $this->createStub(LoggerInterface::class);
    $this->configFactory = $this->createStub(ConfigFactoryInterface::class);
    $this->testCssGenerator = $this->createStub(UtilikitTestCssGenerator::class);

    $this->buildSubject();
  }

  /**
   * Builds the subject under test from the current collaborators.
   *
   * Collaborators are stubs by default; a test that asserts calls on one
   * replaces it with a mock and calls this again.
   */
  private function buildSubject(): void {
    $this->serviceProvider = new UtilikitServiceProvider(
      $this->cssGenerator,
      $this->fileManager,
      $this->contentScanner,
      $this->stateManager,
      $this->cacheManager,
      $this->logger,
      $this->configFactory,
      $this->testCssGenerator,
    );
  }

  /**
   * Tests service getters.
   *
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
   * Tests getRenderingMode returns configured value.
   *
   * @covers ::getRenderingMode
   */
  public function testGetRenderingModeReturnsConfiguredValue(): void {
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->buildSubject();

    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->once())
      ->method('get')
      ->with('rendering_mode')
      ->willReturn('static');

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $this->assertSame('static', $this->serviceProvider->getRenderingMode());
  }

  /**
   * Tests getRenderingMode returns default when not configured.
   *
   * @covers ::getRenderingMode
   */
  public function testGetRenderingModeReturnsDefaultWhenNotConfigured(): void {
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->buildSubject();

    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->once())
      ->method('get')
      ->with('rendering_mode')
      ->willReturn(NULL);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $this->assertSame('inline', $this->serviceProvider->getRenderingMode());
  }

  /**
   * Tests switchToStaticMode with known classes.
   *
   * @covers ::switchToStaticMode
   */
  public function testSwitchToStaticModeWithKnownClasses(): void {
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->cssGenerator = $this->createMock(UtilikitCssGenerator::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->buildSubject();

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
      ->willReturn(TRUE);

    $result = $this->serviceProvider->switchToStaticMode();

    $this->assertSame(1, $result['classes_count']);
    $this->assertSame(10, $result['scanned_count']);
  }

  /**
   * Tests switchToStaticMode without classes.
   *
   * @covers ::switchToStaticMode
   */
  public function testSwitchToStaticModeWithoutClasses(): void {
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->buildSubject();

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
      ->expects($this->once())
      ->method('ensureStaticCssFile');

    $result = $this->serviceProvider->switchToStaticMode();

    $this->assertSame(0, $result['classes_count']);
    $this->assertSame(5, $result['scanned_count']);
  }

  /**
   * Tests switchToInlineMode.
   *
   * @covers ::switchToInlineMode
   */
  public function testSwitchToInlineMode(): void {
    $this->cacheManager = $this->createMock(UtilikitCacheManagerInterface::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->buildSubject();

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
   * Tests updateCssAndFile with valid classes.
   *
   * @covers ::updateCssAndFile
   */
  public function testUpdateCssAndFileWithValidClasses(): void {
    $this->cacheManager = $this->createMock(UtilikitCacheManagerInterface::class);
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->cssGenerator = $this->createMock(UtilikitCssGenerator::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->buildSubject();

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
      ->willReturn(TRUE);

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
   * Tests updateCssAndFile with empty input.
   *
   * @covers ::updateCssAndFile
   */
  public function testUpdateCssAndFileWithEmptyInputReturnsFalse(): void {
    $this->contentScanner = $this->createMock(UtilikitContentScanner::class);
    $this->buildSubject();

    $this->contentScanner
      ->expects($this->never())
      ->method('validateUtilityClasses');

    $this->assertFalse($this->serviceProvider->updateCssAndFile([]));
  }

  /**
   * Tests regenerateStaticCss with no known classes.
   *
   * @covers ::regenerateStaticCss
   */
  public function testRegenerateStaticCssWithNoKnownClasses(): void {
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->buildSubject();

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
   * Tests regenerateStaticCss with known classes.
   *
   * @covers ::regenerateStaticCss
   */
  public function testRegenerateStaticCssWithKnownClasses(): void {
    $this->cacheManager = $this->createMock(UtilikitCacheManagerInterface::class);
    $this->cssGenerator = $this->createMock(UtilikitCssGenerator::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->buildSubject();

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
      ->willReturn(TRUE);

    $this->stateManager
      ->expects($this->once())
      ->method('updateCssTimestamp');

    $this->cacheManager
      ->expects($this->once())
      ->method('clearCssCaches');

    $this->assertTrue($this->serviceProvider->regenerateStaticCss());
  }

}
