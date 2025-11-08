<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileExists;
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
   * Test CSS content.
   */
  protected const TEST_CSS = '.uk-pd--20 { padding: 20px; } .uk-mg--10 { margin: 10px; }';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stateManager = $this->createMock(UtilikitStateManager::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);

    // Create file manager with mocks.
    $this->fileManager = $this->getMockBuilder(UtilikitFileManager::class)
      ->setConstructorArgs([
        $this->stateManager,
        $this->fileUrlGenerator,
        $this->logger,
      ])
      ->onlyMethods(['getFileSystem'])
      ->getMock();

    // Make getFileSystem return our mock.
    $this->fileManager->method('getFileSystem')
      ->willReturn($this->fileSystem);

    // Set up global mock flags for file operations.
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
    $cssUri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $cssDir = UtilikitConstants::CSS_DIRECTORY;
    $parentDir = 'public://css';

    // Set up file system expectations.
    $this->fileSystem->expects($this->exactly(3))
      ->method('dirname')
      ->willReturnCallback(function ($path) use ($cssDir, $parentDir) {
        if ($path === $cssUri) {
          return $cssDir;
        }
        if ($path === $cssDir) {
          return $parentDir;
        }
        return '';
      });

    $this->fileSystem->expects($this->exactly(3))
      ->method('realpath')
      ->willReturnCallback(function ($uri) {
        $paths = [
          UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME => '/tmp/files/css/utilikit/generated.css',
          UtilikitConstants::CSS_DIRECTORY => '/tmp/files/css/utilikit',
          'public://css' => '/tmp/files/css',
        ];
        return $paths[$uri] ?? FALSE;
      });

    $this->fileSystem->expects($this->once())
      ->method('delete')
      ->with($cssUri);

    // Mock scandir to return empty directories.
    $GLOBALS['_utilikit_test_scandir'] = ['.', '..'];

    $this->fileManager->cleanupStaticFiles();
  }

  /**
   * Tests getting static CSS URL when file exists.
   *
   * @covers ::getStaticCssUrl
   */
  public function testGetStaticCssUrlExists(): void {
    $cssUri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $cssPath = '/tmp/files/css/utilikit/generated.css';

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($cssUri)
      ->willReturn($cssPath);

    // Mock file_exists to return true.
    $GLOBALS['_utilikit_test_file_exists'] = TRUE;

    $this->stateManager->expects($this->once())
      ->method('getGeneratedCss')
      ->willReturn(self::TEST_CSS);

    $this->stateManager->expects($this->once())
      ->method('getCssTimestamp')
      ->willReturn(1234567890);

    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with($cssUri)
      ->willReturn('https://example.com/files/css/utilikit/generated.css');

    $url = $this->fileManager->getStaticCssUrl();

    $this->assertNotNull($url);
    $this->assertStringContainsString('generated.css', $url);
    $this->assertStringContainsString('?v=', $url);
    $this->assertStringContainsString('&t=1234567890', $url);
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
   * @covers ::ensureStaticCssFile
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

    $this->stateManager->method('getGeneratedCss')
      ->willReturn($inputCss);

    $this->fileSystem->method('dirname')
      ->willReturn(UtilikitConstants::CSS_DIRECTORY);

    $this->fileSystem->method('prepareDirectory')
      ->willReturn(TRUE);

    $savedCss = '';
    $this->fileSystem->method('saveData')
      ->willReturnCallback(function ($data) use (&$savedCss) {
        $savedCss = $data;
        return UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
      });

    // Mock file_get_contents for optimization.
    $GLOBALS['_utilikit_test_file_get_contents'] = $inputCss;

    $this->fileManager->ensureStaticCssFile();

    // Verify minification removed comments and whitespace.
    $this->assertStringNotContainsString('/*', $savedCss);
    $this->assertStringNotContainsString("\n", $savedCss);
    $this->assertStringContainsString('.test-class{', $savedCss);
    $this->assertStringContainsString('@media (min-width:768px){', $savedCss);
  }

}
