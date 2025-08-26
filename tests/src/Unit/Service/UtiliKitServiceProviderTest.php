<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitServiceProvider;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitCssParser;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtiliKitCacheManager;
use Drupal\utilikit\Service\UtilikitClassNameValidator;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitServiceProvider service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitServiceProvider
 */
class UtilikitServiceProviderTest extends UnitTestCase {

  /**
   * The service provider under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitServiceProvider
   */
  protected UtilikitServiceProvider $serviceProvider;

  /**
   * Mock state manager.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitStateManager&MockObject $stateManager;

  /**
   * Mock CSS parser.
   *
   * @var \Drupal\utilikit\Service\UtilikitCssParser&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitCssParser&MockObject $cssParser;

  /**
   * Mock file manager.
   *
   * @var \Drupal\utilikit\Service\UtilikitFileManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitFileManager&MockObject $fileManager;

  /**
   * Mock cache manager.
   *
   * @var \Drupal\utilikit\Service\UtiliKitCacheManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtiliKitCacheManager&MockObject $cacheManager;

  /**
   * Mock class validator.
   *
   * @var \Drupal\utilikit\Service\UtilikitClassNameValidator&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitClassNameValidator&MockObject $classValidator;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface&MockObject $configFactory;

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

    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->cssParser = $this->createMock(UtilikitCssParser::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->cacheManager = $this->createMock(UtiliKitCacheManager::class);
    $this->classValidator = $this->createMock(UtilikitClassNameValidator::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    // Setup default config.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['class_limit', 1000],
        ['storage_mode', 'file'],
        ['security_level', 'strict'],
      ]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);

    $this->serviceProvider = new UtilikitServiceProvider(
      $this->stateManager,
      $this->cssParser,
      $this->fileManager,
      $this->cacheManager,
      $this->classValidator,
      $this->configFactory,
      $this->logger
    );
  }

  /**
   * Tests processNewClasses with valid classes.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClassesValid(): void {
    $classes = ['uk-pd--20', 'uk-mg--10'];
    $expectedCss = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    // Setup mocks.
    $this->classValidator->expects($this->exactly(2))
      ->method('isValid')
      ->willReturn(TRUE);

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn([]);

    $this->cssParser->expects($this->exactly(2))
      ->method('parseClass')
      ->willReturnCallback(function ($class) use ($expectedCss) {
        return $expectedCss['.' . $class] ?? NULL;
      });

    $this->stateManager->expects($this->once())
      ->method('addClasses')
      ->with($expectedCss);

    $this->fileManager->expects($this->once())
      ->method('saveCssFile')
      ->with($this->anything());

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $result = $this->serviceProvider->processNewClasses($classes);

    $this->assertArrayHasKey('added', $result);
    $this->assertArrayHasKey('duplicates', $result);
    $this->assertArrayHasKey('errors', $result);
    $this->assertCount(2, $result['added']);
  }

  /**
   * Tests processNewClasses with invalid classes.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClassesInvalid(): void {
    $classes = ['invalid-class', 'uk-valid--10'];

    $this->classValidator->expects($this->exactly(2))
      ->method('isValid')
      ->willReturnOnConsecutiveCalls(FALSE, TRUE);

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn([]);

    $this->cssParser->expects($this->once())
      ->method('parseClass')
      ->with('uk-valid--10')
      ->willReturn('padding: 10px;');

    $result = $this->serviceProvider->processNewClasses($classes);

    $this->assertCount(1, $result['errors']);
    $this->assertCount(1, $result['added']);
  }

  /**
   * Tests processNewClasses with duplicate classes.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClassesDuplicates(): void {
    $classes = ['uk-pd--20', 'uk-mg--10'];
    $existingClasses = [
      '.uk-pd--20' => 'padding: 20px;',
    ];

    $this->classValidator->expects($this->exactly(2))
      ->method('isValid')
      ->willReturn(TRUE);

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn($existingClasses);

    $this->cssParser->expects($this->once())
      ->method('parseClass')
      ->with('uk-mg--10')
      ->willReturn('margin: 10px;');

    $result = $this->serviceProvider->processNewClasses($classes);

    $this->assertCount(1, $result['duplicates']);
    $this->assertCount(1, $result['added']);
  }

  /**
   * Tests processNewClasses with class limit exceeded.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClassesLimitExceeded(): void {
    // Create array with 999 existing classes.
    $existingClasses = [];
    for ($i = 1; $i <= 999; $i++) {
      $existingClasses['.uk-test--' . $i] = 'test: ' . $i . 'px;';
    }

    $classes = ['uk-new--1', 'uk-new--2'];

    $this->classValidator->expects($this->any())
      ->method('isValid')
      ->willReturn(TRUE);

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn($existingClasses);

    $this->logger->expects($this->once())
      ->method('warning')
      ->with($this->stringContains('Class limit reached'));

    $result = $this->serviceProvider->processNewClasses($classes);

    $this->assertArrayHasKey('errors', $result);
    $this->assertStringContains('limit', $result['errors'][0]);
  }

  /**
   * Tests removeClasses method.
   *
   * @covers ::removeClasses
   */
  public function testRemoveClasses(): void {
    $classes = ['uk-pd--20', 'uk-mg--10'];
    $existingClasses = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
      '.uk-test--5' => 'test: 5px;',
    ];

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn($existingClasses);

    $this->stateManager->expects($this->once())
      ->method('removeClasses')
      ->with(['.uk-pd--20', '.uk-mg--10']);

    $this->fileManager->expects($this->once())
      ->method('saveCssFile')
      ->with($this->anything());

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $result = $this->serviceProvider->removeClasses($classes);

    $this->assertArrayHasKey('removed', $result);
    $this->assertArrayHasKey('notFound', $result);
    $this->assertCount(2, $result['removed']);
  }

  /**
   * Tests getClassInfo method.
   *
   * @covers ::getClassInfo
   */
  public function testGetClassInfo(): void {
    $existingClasses = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn($existingClasses);

    $info = $this->serviceProvider->getClassInfo('uk-pd--20');

    $this->assertArrayHasKey('exists', $info);
    $this->assertArrayHasKey('css', $info);
    $this->assertTrue($info['exists']);
    $this->assertEquals('padding: 20px;', $info['css']);
  }

  /**
   * Tests optimizeCss method.
   *
   * @covers ::optimizeCss
   */
  public function testOptimizeCss(): void {
    $this->fileManager->expects($this->once())
      ->method('optimizeCssFile')
      ->willReturn(TRUE);

    $this->cacheManager->expects($this->once())
      ->method('invalidateCssCaches');

    $result = $this->serviceProvider->optimizeCss();
    $this->assertTrue($result);
  }

  /**
   * Tests error handling in processNewClasses.
   *
   * @covers ::processNewClasses
   */
  public function testProcessNewClassesErrorHandling(): void {
    $classes = ['uk-pd--20'];

    $this->classValidator->expects($this->once())
      ->method('isValid')
      ->willReturn(TRUE);

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn([]);

    $this->cssParser->expects($this->once())
      ->method('parseClass')
      ->willThrowException(new \Exception('Parse error'));

    $this->logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Error parsing class'));

    $result = $this->serviceProvider->processNewClasses($classes);

    $this->assertCount(1, $result['errors']);
  }

  /**
   * Tests generateReport method.
   *
   * @covers ::generateReport
   */
  public function testGenerateReport(): void {
    $existingClasses = [
      '.uk-pd--20' => 'padding: 20px;',
      '.uk-mg--10' => 'margin: 10px;',
    ];

    $this->stateManager->expects($this->once())
      ->method('getStoredClasses')
      ->willReturn($existingClasses);

    $this->stateManager->expects($this->once())
      ->method('getStatistics')
      ->willReturn([
        'total_classes' => 2,
        'last_updated' => time(),
      ]);

    $report = $this->serviceProvider->generateReport();

    $this->assertArrayHasKey('total_classes', $report);
    $this->assertArrayHasKey('statistics', $report);
    $this->assertArrayHasKey('config', $report);
    $this->assertEquals(2, $report['total_classes']);
  }

  /**
   * Tests validateConfiguration method.
   *
   * @covers ::validateConfiguration
   */
  public function testValidateConfiguration(): void {
    $this->fileManager->expects($this->once())
      ->method('ensureDirectoryExists')
      ->willReturn(TRUE);

    $result = $this->serviceProvider->validateConfiguration();

    $this->assertArrayHasKey('valid', $result);
    $this->assertArrayHasKey('errors', $result);
    $this->assertTrue($result['valid']);
  }

}
