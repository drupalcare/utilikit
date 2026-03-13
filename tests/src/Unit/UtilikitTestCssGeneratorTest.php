<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitCssGenerator;
use Drupal\utilikit\Service\UtilikitFileManager;
use Drupal\utilikit\Service\UtilikitTestCssGenerator;
use Drupal\utilikit_test\Service\TestGenerator;

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
   * Mock config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

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
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->testGenerator = $this->createMock(TestGenerator::class);

    $this->testCssGenerator = new UtilikitTestCssGenerator(
      $this->cssGenerator,
      $this->fileManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      $this->configFactory,
      $this->testGenerator
    );
  }

  /**
   * Helper to set up config factory mock for optimize_css setting.
   *
   * @param bool $optimizeCss
   *   Whether CSS optimization is enabled.
   */
  protected function setUpConfigMock(bool $optimizeCss = FALSE): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('optimize_css')
      ->willReturn($optimizeCss);

    $this->configFactory->method('get')
      ->with('utilikit.settings')
      ->willReturn($config);
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
      $this->configFactory,
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
      $this->configFactory,
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
      ->method('getAllPossibleClasses')
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
      $this->configFactory,
      NULL
    );

    $result = $generator->generateAllUtilityClasses();

    $this->assertEquals([], $result);
  }

  /**
   * Tests generateCompleteTestCss creates comprehensive CSS and saves file.
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

    $expectedPath = 'public://utilikit/utilikit-test-complete.css';
    $expectedUrl = 'https://example.com/sites/default/files/utilikit/utilikit-test-complete.css';

    $this->testGenerator->expects($this->once())
      ->method('getAllPossibleClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($allClasses)
      ->willReturn($expectedCss);

    $this->setUpConfigMock(FALSE);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->with('public://utilikit', FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)
      ->willReturn(TRUE);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->with($expectedCss, $expectedPath, FileExists::Replace)
      ->willReturn($expectedPath);

    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with($expectedPath)
      ->willReturn($expectedUrl);

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertIsArray($result);
    $this->assertTrue($result['success']);
    $this->assertEquals(count($allClasses), $result['count']);
    $this->assertEquals(strlen($expectedCss), $result['size']);
    $this->assertEquals($expectedUrl, $result['file_url']);
  }

  /**
   * Tests generateCompleteTestCss with CSS optimization enabled.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssWithOptimization() {
    $allClasses = ['uk-pd--20'];
    $rawCss = '.uk-pd--20 { padding: 20px; }';
    $minifiedCss = '.uk-pd--20{padding:20px}';
    $expectedPath = 'public://utilikit/utilikit-test-complete.css';
    $expectedUrl = 'https://example.com/sites/default/files/utilikit/utilikit-test-complete.css';

    $this->testGenerator->expects($this->once())
      ->method('getAllPossibleClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($allClasses)
      ->willReturn($rawCss);

    $this->setUpConfigMock(TRUE);

    $this->fileManager->expects($this->once())
      ->method('minifyCss')
      ->with($rawCss)
      ->willReturn($minifiedCss);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->willReturn(TRUE);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->with($minifiedCss, $expectedPath, FileExists::Replace)
      ->willReturn($expectedPath);

    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with($expectedPath)
      ->willReturn($expectedUrl);

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertTrue($result['success']);
    $this->assertEquals(strlen($minifiedCss), $result['size']);
  }

  /**
   * Tests generateCompleteTestCss handles empty class list.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssEmpty() {
    $this->testGenerator->expects($this->once())
      ->method('getAllPossibleClasses')
      ->willReturn([]);

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertEquals('No test classes generated.', $result['error']);
  }

  /**
   * Tests generateCompleteTestCss without test generator module.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssWithoutTestGenerator() {
    $generator = new UtilikitTestCssGenerator(
      $this->cssGenerator,
      $this->fileManager,
      $this->fileSystem,
      $this->fileUrlGenerator,
      $this->configFactory,
      NULL
    );

    $result = $generator->generateCompleteTestCss();

    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertEquals('Enable utilikit_test module to generate test CSS.', $result['error']);
  }

  /**
   * Tests generateCompleteTestCss handles directory preparation failure.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssDirectoryFailure() {
    $allClasses = ['uk-pd--20'];
    $css = '.uk-pd--20 { padding: 20px; }';

    $this->testGenerator->expects($this->once())
      ->method('getAllPossibleClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($allClasses)
      ->willReturn($css);

    $this->setUpConfigMock(FALSE);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->willReturn(FALSE);

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertEquals('Failed to prepare directory.', $result['error']);
  }

  /**
   * Tests generateCompleteTestCss handles file save failure.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssSaveFailure() {
    $allClasses = ['uk-pd--20'];
    $css = '.uk-pd--20 { padding: 20px; }';

    $this->testGenerator->expects($this->once())
      ->method('getAllPossibleClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($allClasses)
      ->willReturn($css);

    $this->setUpConfigMock(FALSE);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->willReturn(TRUE);

    $this->fileSystem->expects($this->once())
      ->method('saveData')
      ->willReturn(FALSE);

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertEquals('Failed to save CSS file.', $result['error']);
  }

  /**
   * Tests generateCompleteTestCss handles exception during file operations.
   *
   * @covers ::generateCompleteTestCss
   */
  public function testGenerateCompleteTestCssException() {
    $allClasses = ['uk-pd--20'];
    $css = '.uk-pd--20 { padding: 20px; }';

    $this->testGenerator->expects($this->once())
      ->method('getAllPossibleClasses')
      ->willReturn($allClasses);

    $this->cssGenerator->expects($this->once())
      ->method('generateCssFromClasses')
      ->with($allClasses)
      ->willReturn($css);

    $this->setUpConfigMock(FALSE);

    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->willThrowException(new \Exception('Disk full'));

    $result = $this->testCssGenerator->generateCompleteTestCss();

    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertEquals('Disk full', $result['error']);
  }

  /**
   * Tests deleteTestCssFile when file does not exist.
   *
   * In a unit test environment without a registered stream wrapper,
   * file_exists() on 'public://...' returns FALSE, so deleteTestCssFile()
   * returns TRUE (file already absent).
   *
   * @covers ::deleteTestCssFile
   */
  public function testDeleteTestCssFileNotExists() {
    // In unit test context, file_exists('public://utilikit/...') returns
    // FALSE since no stream wrapper is registered. The method returns TRUE
    // when the file does not exist. Suppress the PHP warning about the
    // missing stream wrapper.
    $result = @$this->testCssGenerator->deleteTestCssFile();

    $this->assertIsBool($result);
    $this->assertTrue($result);
  }

  /**
   * Tests getTestCssFileInfo when file does not exist.
   *
   * In a unit test environment without a registered stream wrapper,
   * file_exists() returns FALSE, so getTestCssFileInfo() returns the
   * "not exists" result array.
   *
   * @covers ::getTestCssFileInfo
   */
  public function testGetTestCssFileInfoNotExists() {
    // In unit test context, file_exists('public://utilikit/...') returns
    // FALSE since no stream wrapper is registered. Suppress the PHP warning
    // about the missing stream wrapper.
    $result = @$this->testCssGenerator->getTestCssFileInfo();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('exists', $result);
    $this->assertFalse($result['exists']);
    $this->assertNull($result['size']);
    $this->assertNull($result['url']);
    $this->assertNull($result['modified']);
  }

}
