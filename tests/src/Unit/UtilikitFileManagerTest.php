<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Asset\AssetOptimizerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitStateManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitFileManager service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitFileManager
 */
class UtilikitFileManagerTest extends UnitTestCase {

  /**
   * The file manager service under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitFileManager
   */
  protected UtilikitFileManager $fileManager;

  /**
   * Mock state manager.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager&\PHPUnit\Framework\MockObject\MockObject
   */
  protected UtilikitStateManager&MockObject $stateManager;

  /**
   * Mock file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected FileUrlGeneratorInterface&MockObject $fileUrlGenerator;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface&MockObject $logger;

  /**
   * Mock file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected FileSystemInterface&MockObject $fileSystem;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface&MockObject $configFactory;

  /**
   * Mock CSS optimizer.
   *
   * @var \Drupal\Core\Asset\AssetOptimizerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected AssetOptimizerInterface&MockObject $cssOptimizer;

  /**
   * Test CSS content.
   */
  protected const TEST_CSS = '.uk-pd--20 { padding: 20px; } .uk-mg--10 { margin: 10px; }';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->cssOptimizer = $this->createMock(AssetOptimizerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static function (string $key) {
      return match ($key) {
        'rendering_mode' => 'static',
        'optimize_css' => FALSE,
        default => NULL,
      };
    });
    $this->configFactory->method('get')->with('utilikit.settings')->willReturn($config);

    $this->fileManager = new UtilikitFileManager(
      $this->stateManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      $this->configFactory,
      $this->cssOptimizer,
      $this->logger
    );

    $GLOBALS['_utilikit_test_file_exists'] = TRUE;
    $GLOBALS['_utilikit_test_file_get_contents'] = '';
    $GLOBALS['_utilikit_test_scandir'] = ['.', '..'];
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up global test flags.
    unset($GLOBALS['_utilikit_test_file_exists']);
    unset($GLOBALS['_utilikit_test_file_get_contents']);
    unset($GLOBALS['_utilikit_test_scandir']);

    parent::tearDown();
  }

  /**
   * Tests successful CSS file creation.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testEnsureStaticCssFileSuccess(): void {
    // Set up mocks for successful file creation.
    $this->stateManager->expects($this->once())
      ->method('getGeneratedCss')
      ->willReturn(self::TEST_CSS);

    $cssUri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $cssDir = UtilikitConstants::CSS_DIRECTORY;

    $this->fileSystem->expects($this->once())
      ->method('dirname')
      ->with($cssUri)
      ->willReturn($cssDir);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->with($cssDir, FileSystemInterface::CREATE_DIRECTORY)
      ->willReturn(TRUE);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->with(self::TEST_CSS, $cssUri, FileExists::Replace)
      ->willReturn($cssUri);

    $this->logger->expects($this->once())
      ->method('info')
      ->with(
        $this->stringContains('Successfully saved static CSS file'),
        $this->arrayHasKey('@uri')
      );

    $result = $this->fileManager->ensureStaticCssFile();
    $this->assertTrue($result);
  }

  /**
   * Tests CSS file creation with empty CSS.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testEnsureStaticCssFileEmptyCss(): void {
    $this->stateManager->expects($this->once())
      ->method('getGeneratedCss')
      ->willReturn('');

    $this->logger->expects($this->once())
      ->method('warning')
      ->with('No CSS to write to static file.');

    $this->fileSystem->expects($this->never())
      ->method('saveData');

    $result = $this->fileManager->ensureStaticCssFile();
    $this->assertFalse($result);
  }

  /**
   * Tests CSS file creation with directory preparation failure.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testEnsureStaticCssFileDirectoryFailure(): void {
    $this->stateManager->method('getGeneratedCss')
      ->willReturn(self::TEST_CSS);

    $this->fileSystem->method('dirname')
      ->willReturn(UtilikitConstants::CSS_DIRECTORY);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->willReturn(FALSE);

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Failed to prepare directory'),
        $this->arrayHasKey('@dir')
      );

    $this->fileSystem->expects($this->never())
      ->method('saveData');

    $result = $this->fileManager->ensureStaticCssFile();
    $this->assertFalse($result);
  }

  /**
   * Tests CSS file creation with save failure.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testEnsureStaticCssFileSaveFailure(): void {
    $this->stateManager->method('getGeneratedCss')
      ->willReturn(self::TEST_CSS);

    $this->fileSystem->method('dirname')
      ->willReturn(UtilikitConstants::CSS_DIRECTORY);

    $this->fileSystem->method('prepareDirectory')
      ->willReturn(TRUE);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->willReturn(FALSE);

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Failed to save static CSS file'),
        $this->arrayHasKey('@uri')
      );

    $result = $this->fileManager->ensureStaticCssFile();
    $this->assertFalse($result);
  }

  /**
   * Tests cleanupStaticFiles method.
   *
   * @covers ::cleanupStaticFiles
   */
  public function testCleanupStaticFiles(): void {
    // When realpath returns FALSE (file doesn't exist on disk), the method
    // skips delete and directory cleanup. This tests the "no file" path.
    $this->fileSystem->method('dirname')
      ->willReturn(UtilikitConstants::CSS_DIRECTORY);

    $this->fileSystem->method('realpath')
      ->willReturn(FALSE);

    // Delete should NOT be called when file doesn't exist.
    $this->fileSystem->expects($this->never())
      ->method('delete');

    $this->fileManager->cleanupStaticFiles();
  }

  /**
   * Tests getting static CSS URL when file exists.
   *
   * @covers ::getStaticCssUrl
   */
  public function testGetStaticCssUrlReturnsNullWhenRealPathFails(): void {
    $cssUri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;

    // When realpath returns a path but file_exists returns false (file was
    // deleted between calls), getStaticCssUrl should return null.
    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($cssUri)
      ->willReturn('/nonexistent/path/utilikit-static-style.css');

    $url = $this->fileManager->getStaticCssUrl();
    $this->assertNull($url);
  }

  /**
   * Tests getting static CSS URL when file doesn't exist.
   *
   * @covers ::getStaticCssUrl
   */
  public function testGetStaticCssUrlNotExists(): void {
    $cssUri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($cssUri)
      ->willReturn(FALSE);

    $url = $this->fileManager->getStaticCssUrl();
    $this->assertNull($url);
  }

  /**
   * Tests exception handling during file save.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testExceptionHandling(): void {
    $this->stateManager->method('getGeneratedCss')
      ->willThrowException(new \Exception('Test exception'));

    $this->logger->expects($this->once())
      ->method('error')
      ->with(
        $this->stringContains('Exception while saving static CSS'),
        $this->arrayHasKey('@message')
      );

    $result = $this->fileManager->ensureStaticCssFile();
    $this->assertFalse($result);
  }

  /**
   * Tests CSS minification logic.
   *
   * @covers ::minifyCss
   */
  public function testCssMinification(): void {
    $inputCss = '/* Comment to remove */
    .test-class {
      padding: 20px;
      margin: 10px;
    }

    @media (min-width: 768px) {
      .test-class {
        padding: 30px;
      }
    }';

    $result = $this->fileManager->minifyCss($inputCss);

    // Verify minification removed comments and whitespace.
    $this->assertStringNotContainsString('/*', $result);
    $this->assertStringNotContainsString("\n", $result);
    $this->assertStringContainsString('.test-class{', $result);
    $this->assertStringContainsString('@media (min-width:768px){', $result);
  }

}
