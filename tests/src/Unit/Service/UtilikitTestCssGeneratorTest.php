<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitTestCssGenerator;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit_test\Service\TestGenerator;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Tests UtilikitTestCssGenerator service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitTestCssGenerator
 */
class UtilikitTestCssGeneratorTest extends UnitTestCase {

  /**
   * The test CSS generator under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitTestCssGenerator
   */
  protected $testCssGenerator;

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
   * Mock file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileSystem;

  /**
   * Mock file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileUrlGenerator;

  /**
   * Mock test generator service.
   *
   * @var \Drupal\utilikit_test\Service\TestGenerator|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $testGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cssGenerator = $this->createMock(UtilikitCssGenerator::class);
    $this->fileManager = $this->createMock(UtilikitFileManager::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $this->testGenerator = $this->createMock(TestGenerator::class);

    $this->testCssGenerator = new UtilikitTestCssGenerator(
      $this->cssGenerator,
      $this->fileManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      $this->testGenerator
    );
  }

  /**
   * Tests service construction.
   *
   * @covers ::__construct
   */
  public function testConstruct() {
    $generator = new UtilikitTestCssGenerator(
      $this->cssGenerator,
      $this->fileManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      $this->testGenerator
    );
    $this->assertInstanceOf(UtilikitTestCssGenerator::class, $generator);
  }

  /**
   * Tests construction without test generator (module not enabled).
   *
   * @covers ::__construct
   */
  public function testConstructWithoutTestGenerator() {
    $generator = new UtilikitTestCssGenerator(
      $this->cssGenerator,
      $this->fileManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      NULL
    );
    $this->assertInstanceOf(UtilikitTestCssGenerator::class, $generator);
  }

  /**
   * Tests generateAllUtilityClasses when test generator is available.
   *
   * @covers ::generateAllUtilityClasses
   */
  public function testGenerateAllUtilityClassesWithTestGenerator() {
    $testClasses = [
      'uk-pd--20',
      'uk-mg--auto',
      'uk-bg--ff0000',
      'uk-sm-pd--30',
      'uk-lg-mg--40',
    ];

    $this->testGenerator->expects($this->once())
      ->method('generateAllUtilityClasses')
      ->willReturn($testClasses);

    $result = $this->testCssGenerator->generateAllUtilityClasses();

    $this->assertEquals($testClasses, $result);
  }

  /**
   * Tests generateAllUtilityClasses when test generator is not available.
   *
   * @covers ::generateAllUtilityClasses
   */
  public function testGenerateAllUtilityClassesWithoutTestGenerator() {
    $generator = new UtilikitTestCssGenerator(
      $this->cssGenerator,
      $this->fileManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      NULL
    );

    $result = $generator->generateAllUtilityClasses();

    $this->assertEquals([], $result);
  }

  /**
   * Tests generateCompleteTestCss creates comprehensive CSS.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCss() {
    $allClasses = [
      'uk-pd--20',
      'uk-mg--auto',
      'uk-bg--ff0000',
      'uk-sm-pd--30',
    ];

    $expectedCss = '.uk-pd--20 { padding: 20px; }' . "\n" .
                   '.uk-mg--auto { margin: auto; }' . "\n" .
                   '.uk-bg--ff0000 { background-color: #ff0000; }' . "\n" .
                   '@media (min-width: 576px) { .uk-sm-pd--30 { padding: 30px; } }';

    $this->testGenerator->expects($this->once())
      ->method('generateAllUtilityClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with($allClasses)
      ->willReturn($expectedCss);

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertEquals($expectedCss, $result);
    $this->assertStringContains('.uk-pd--20', $result);
    $this->assertStringContains('.uk-mg--auto', $result);
    $this->assertStringContains('@media', $result);
  }

  /**
   * Tests generateCompleteTestCss handles empty class list.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssEmpty() {
    $this->testGenerator->expects($this->once())
      ->method('generateAllUtilityClasses')
      ->willReturn([]);

    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with([])
      ->willReturn('');

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertEquals('', $result);
  }

  /**
   * Tests createTestCssFile generates and saves CSS file.
   *
   * @covers ::createTestCssFile
   */
  public function testCreateTestCssFile() {
    $testCss = '.uk-pd--20 { padding: 20px; }';
    $testFilename = 'utilikit-test-complete.css';
    $expectedPath = 'public://css/utilikit/' . $testFilename;
    $expectedUrl = 'https://example.com/sites/default/files/css/utilikit/' . $testFilename;

    $allClasses = ['uk-pd--20', 'uk-mg--10'];

    $this->testGenerator->expects($this->once())
      ->method('generateAllUtilityClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with($allClasses)
      ->willReturn($testCss);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->with($testCss, $expectedPath)
      ->willReturn($expectedPath);

    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with($expectedPath)
      ->willReturn($expectedUrl);

    $result = $this->testCssGenerator->createTestCssFile();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertArrayHasKey('file_path', $result);
    $this->assertArrayHasKey('file_url', $result);
    $this->assertArrayHasKey('classes_count', $result);
    $this->assertTrue($result['success']);
    $this->assertEquals($expectedPath, $result['file_path']);
    $this->assertEquals($expectedUrl, $result['file_url']);
    $this->assertEquals(count($allClasses), $result['classes_count']);
  }

  /**
   * Tests createTestCssFile handles file save failure.
   *
   * @covers ::createTestCssFile
   */
  public function testCreateTestCssFileFailure() {
    $allClasses = ['uk-pd--20'];
    $testCss = '.uk-pd--20 { padding: 20px; }';

    $this->testGenerator->expects($this->once())
      ->method('generateAllUtilityClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCss')
      ->with($allClasses)
      ->willReturn($testCss);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->willReturn(FALSE);

    $result = $this->testCssGenerator->createTestCssFile();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertArrayHasKey('error', $result);
    $this->assertFalse($result['success']);
    $this->assertStringContains('Failed to save', $result['error']);
  }

  /**
   * Tests deleteTestCssFile removes test CSS file.
   *
   * @covers ::deleteTestCssFile
   */
  public function testDeleteTestCssFile() {
    $testFilename = 'utilikit-test-complete.css';
    $expectedPath = 'public://css/utilikit/' . $testFilename;

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($expectedPath)
      ->willReturn('/real/path/to/test.css');

    $this->fileSystem->expects($this->once())
      ->method('delete')
      ->with($expectedPath)
      ->willReturn(TRUE);

    $result = $this->testCssGenerator->deleteTestCssFile();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertArrayHasKey('message', $result);
    $this->assertTrue($result['success']);
    $this->assertStringContains('deleted successfully', $result['message']);
  }

  /**
   * Tests deleteTestCssFile when file doesn't exist.
   *
   * @covers ::deleteTestCssFile
   */
  public function testDeleteTestCssFileNotExists() {
    $testFilename = 'utilikit-test-complete.css';
    $expectedPath = 'public://css/utilikit/' . $testFilename;

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($expectedPath)
      ->willReturn(FALSE);

    $result = $this->testCssGenerator->deleteTestCssFile();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertArrayHasKey('message', $result);
    $this->assertFalse($result['success']);
    $this->assertStringContains('does not exist', $result['message']);
  }

  /**
   * Tests deleteTestCssFile handles deletion failure.
   *
   * @covers ::deleteTestCssFile
   */
  public function testDeleteTestCssFileFailure() {
    $testFilename = 'utilikit-test-complete.css';
    $expectedPath = 'public://css/utilikit/' . $testFilename;

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($expectedPath)
      ->willReturn('/real/path/to/test.css');

    $this->fileSystem->expects($this->once())
      ->method('delete')
      ->with($expectedPath)
      ->willReturn(FALSE);

    $result = $this->testCssGenerator->deleteTestCssFile();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertArrayHasKey('error', $result);
    $this->assertFalse($result['success']);
    $this->assertStringContains('Failed to delete', $result['error']);
  }

  /**
   * Tests getTestCssFileInfo returns file information.
   *
   * @covers ::getTestCssFileInfo
   */
  public function testGetTestCssFileInfo() {
    $testFilename = 'utilikit-test-complete.css';
    $expectedPath = 'public://css/utilikit/' . $testFilename;
    $realPath = '/real/path/to/test.css';
    $expectedUrl = 'https://example.com/sites/default/files/css/utilikit/' . $testFilename;

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($expectedPath)
      ->willReturn($realPath);

    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with($expectedPath)
      ->willReturn($expectedUrl);

    // Mock file_exists and filesize functions would be needed in real scenario
    // For this test, we'll assume the file exists and has a size.
    $result = $this->testCssGenerator->getTestCssFileInfo();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('exists', $result);
    $this->assertArrayHasKey('path', $result);
    $this->assertArrayHasKey('url', $result);
    $this->assertTrue($result['exists']);
    $this->assertEquals($expectedPath, $result['path']);
    $this->assertEquals($expectedUrl, $result['url']);
  }

  /**
   * Tests getTestCssFileInfo when file doesn't exist.
   *
   * @covers ::getTestCssFileInfo
   */
  public function testGetTestCssFileInfoNotExists() {
    $testFilename = 'utilikit-test-complete.css';
    $expectedPath = 'public://css/utilikit/' . $testFilename;

    $this->fileSystem->expects($this->once())
      ->method('realpath')
      ->with($expectedPath)
      ->willReturn(FALSE);

    $result = $this->testCssGenerator->getTestCssFileInfo();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('exists', $result);
    $this->assertArrayHasKey('path', $result);
    $this->assertFalse($result['exists']);
    $this->assertEquals($expectedPath, $result['path']);
    $this->assertArrayNotHasKey('url', $result);
    $this->assertArrayNotHasKey('size', $result);
  }

}
