<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitStateManager;
use Drupal\utilikit\Service\UtilikitConstants;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\File\FileExists;

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
  protected $fileManager;

  /**
   * Mock state manager.
   *
   * @var \Drupal\utilikit\Service\UtilikitStateManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stateManager;

  /**
   * Mock file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileUrlGenerator;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * Mock file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

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

    // Mock Drupal static calls.
    $this->mockDrupalStatic();
  }

  /**
   * Tests successful CSS file creation.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testEnsureStaticCssFileSuccess() {
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
  public function testEnsureStaticCssFileEmptyCss() {
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
  public function testEnsureStaticCssFileDirectoryFailure() {
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
  public function testEnsureStaticCssFileSaveFailure() {
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
   * Tests CSS optimization.
   *
   * @covers ::ensureStaticCssFile
   */
  public function testCssOptimization() {
    // CSS with whitespace and comments.
    $unoptimizedCss = '/* Comment */
    .uk-pd--20 {
      padding: 20px;
    }

    .uk-mg--10 {
      margin: 10px;
    }';

    $this->stateManager->method('getGeneratedCss')
      ->willReturn($unoptimizedCss);

    $this->fileSystem->method('dirname')
      ->willReturn(UtilikitConstants::CSS_DIRECTORY);

    $this->fileSystem->method('prepareDirectory')
      ->willReturn(TRUE);

    // First save unoptimized, then optimized.
    $this->fileSystem->expects($this->exactly(2))
      ->method('saveData')
      ->willReturnCallback(function ($data, $uri) use ($unoptimizedCss) {
        // First call saves unoptimized.
        if ($data === $unoptimizedCss) {
          return $uri;
        }
        // Second call saves optimized (minified)
        if (strpos($data, '/*') === FALSE && strpos($data, "\n") === FALSE) {
          return $uri;
        }
        return FALSE;
      });

    // Mock file_get_contents.
    global $_utilikit_fileGetContentsReturn;
    $_utilikit_fileGetContentsReturn = $unoptimizedCss;

    $this->logger->expects($this->exactly(2))
      ->method('info')
      ->withConsecutive(
        [
          $this->stringContains('CSS optimized'),
          $this->arrayHasKey('@original'),
        ],
        [
          $this->stringContains('Successfully saved static CSS file'),
          $this->arrayHasKey('@uri'),
        ]
      );

    $result = $this->fileManager->ensureStaticCssFile();
    $this->assertTrue($result);
  }

  /**
   * Tests cleanup of static files.
   *
   * @covers ::cleanupStaticFiles
   */
  public function testCleanupStaticFiles() {
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

    // Mock scandir and file_exists.
    global $_utilikit_scandirReturn;
    // Empty directories.
    $_utilikit_scandirReturn = ['.', '..'];

    $this->fileManager->cleanupStaticFiles();
  }

  /**
   * Tests getting static CSS URL when file exists.
   *
   * @covers ::getStaticCssUrl
   */
  public function testGetStaticCssUrlExists() {
    $cssUri = UtilikitConstants::CSS_DIRECTORY . '/' . UtilikitConstants::CSS_FILENAME;
    $cssPath = '/tmp/files/css/utilikit/generated.css';

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($cssUri)
      ->willReturn($cssPath);

    // Mock file_exists.
    global $_utilikit_fileExistsReturn;
    $_utilikit_fileExistsReturn = TRUE;

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
  public function testGetStaticCssUrlNotExists() {
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
  public function testExceptionHandling() {
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
  public function testCssMinification() {
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
    global $_utilikit_fileGetContentsReturn;
    $_utilikit_fileGetContentsReturn = $inputCss;

    $this->fileManager->ensureStaticCssFile();

    // Verify minification removed comments and whitespace.
    $this->assertStringNotContainsString('/*', $savedCss);
    $this->assertStringNotContainsString("\n", $savedCss);
    $this->assertStringContainsString('.test-class{', $savedCss);
    $this->assertStringContainsString('@media (min-width:768px){', $savedCss);
  }

  /**
   * Mock Drupal static calls.
   */
  protected function mockDrupalStatic() {
    // Mock \Drupal::config()
    $config = $this->createMock('\Drupal\Core\Config\ImmutableConfig');
    $config->method('get')
      ->willReturnCallback(function ($key) {
        if ($key === 'rendering_mode') {
          return 'static';
        }
        if ($key === 'optimize_css') {
          return TRUE;
        }
        return NULL;
      });

    // This would need proper implementation in a real test environment.
  }

}

// Global function mocks for file operations.
namespace Drupal\utilikit\Service;

/**
 * Mock implementation of file_get_contents for testing.
 */
function file_get_contents($filename) {
  global $_utilikit_fileGetContentsReturn;
  return $_utilikit_fileGetContentsReturn ?? '';
}

/**
 * Mock implementation of file_exists for testing.
 */
function file_exists($filename) {
  global $_utilikit_fileExistsReturn;
  return $_utilikit_fileExistsReturn ?? TRUE;
}

/**
 * Mock implementation of scandir for testing.
 */
function scandir($directory) {
  global $_utilikit_scandirReturn;
  return $_utilikit_scandirReturn ?? ['.', '..'];
}

/**
 * Mock implementation of rmdir for testing.
 */
function rmdir($dirname) {
  return TRUE;
}
