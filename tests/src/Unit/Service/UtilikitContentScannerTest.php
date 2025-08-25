<?php

namespace Drupal\Tests\utilikit\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests the UtilikitContentScanner service.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Service\UtilikitContentScanner
 */
class UtilikitContentScannerTest extends UnitTestCase {

  /**
   * The content scanner service under test.
   *
   * @var \Drupal\utilikit\Service\UtilikitContentScanner
   */
  protected $contentScanner;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LoggerInterface|MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->contentScanner = new UtilikitContentScanner(
      $this->entityTypeManager,
      $this->logger
    );
  }

  /**
   * Tests extractClassesFromHtml with valid HTML.
   *
   * @covers ::extractClassesFromHtml
   * @dataProvider providerValidHtml
   */
  public function testExtractClassesFromHtmlValid($html, $expected) {
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for valid HTML extraction tests.
   */
  public function providerValidHtml() {
    return [
      'single class' => [
        '<div class="utilikit uk-pd--20">Content</div>',
        ['uk-pd--20'],
      ],
      'multiple classes' => [
        '<div class="utilikit uk-pd--20 uk-mg--10">Content</div>',
        ['uk-pd--20', 'uk-mg--10'],
      ],
      'nested elements' => [
        '<div class="utilikit uk-pd--20">
          <span class="utilikit uk-tc--ff0000">Text</span>
        </div>',
        ['uk-pd--20', 'uk-tc--ff0000'],
      ],
      'duplicate classes removed' => [
        '<div class="utilikit uk-pd--20">
          <div class="utilikit uk-pd--20">Content</div>
        </div>',
        ['uk-pd--20'],
      ],
      'responsive classes' => [
        '<div class="utilikit uk-pd--20 uk-md-pd--30 uk-lg-pd--40">Content</div>',
        ['uk-pd--20', 'uk-md-pd--30', 'uk-lg-pd--40'],
      ],
      'mixed valid and invalid' => [
        '<div class="utilikit uk-pd--20 invalid-class uk-mg--10">Content</div>',
        ['uk-pd--20', 'uk-mg--10'],
      ],
      'no utilikit class' => [
        '<div class="uk-pd--20">Content</div>',
        [],
      ],
      'empty HTML' => [
        '',
        [],
      ],
    ];
  }

  /**
   * Tests isValidUtilityClass method.
   *
   * @covers ::isValidUtilityClass
   * @dataProvider providerValidityChecks
   */
  public function testIsValidUtilityClass($class, $expected) {
    $result = $this->contentScanner->isValidUtilityClass($class);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for validity checks.
   */
  public function providerValidityChecks() {
    return [
      // Valid classes.
      ['uk-pd--20', TRUE],
      ['uk-mg--t-10', TRUE],
      ['uk-bg--ff0000', TRUE],
      ['uk-tc--333333-50', TRUE],
      ['uk-md-pd--20', TRUE],
      ['uk-lg-dp--flex', TRUE],
      ['uk-br--10-20-30-40', TRUE],

      // Decimal notation tests.
      ['uk-fs--1d5rem', TRUE],
      ['uk-lh--1d4', TRUE],
      ['uk-fg--1d50', TRUE],
      ['uk-pd--2d5rem', TRUE],
      ['uk-ls--0d05em', TRUE],
      ['uk-md-fs--1d25rem', TRUE],

      // Invalid classes.
      ['not-uk-class', FALSE],
      ['uk-invalid', FALSE],
      ['uk-toolongprefix--20', FALSE],
      ['uk-pd--', FALSE],
      ['uk--20', FALSE],
      ['uk-pd-20', FALSE],
    // Too long.
      [str_repeat('uk-pd--', 50) . '20', FALSE],
    ];
  }

  /**
   * Tests validateUtilityClasses method.
   *
   * @covers ::validateUtilityClasses
   */
  public function testValidateUtilityClasses() {
    $classes = [
      'uk-pd--20',
      'invalid-class',
      'uk-mg--10',
      'uk-invalid--format',
      'uk-bg--ff0000',
    ];

    $result = $this->contentScanner->validateUtilityClasses($classes);

    $expected = ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'];
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getAbbreviationsFromClasses method.
   *
   * @covers ::getAbbreviationsFromClasses
   */
  public function testGetAbbreviationsFromClasses() {
    $classes = [
      'uk-pd--20',
      'uk-mg--10',
      'uk-pd--30',
      'uk-bg--ff0000',
      'uk-md-pd--40',
      'uk-lg-tc--333333',
      'invalid-class',
    ];

    $result = $this->contentScanner->getAbbreviationsFromClasses($classes);

    $expected = ['pd', 'mg', 'bg', 'tc'];
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests scanEntity method.
   *
   * @covers ::scanEntity
   */
  public function testScanEntity() {
    // Create a mock entity.
    $entity = $this->createMock(FieldableEntityInterface::class);

    // Create mock field with HTML content.
    $field = $this->createMock(FieldItemListInterface::class);
    $fieldItem = $this->createMock(FieldItemInterface::class);
    $property = $this->createMock(TypedDataInterface::class);

    $htmlContent = '<div class="utilikit uk-pd--20 uk-mg--10">
      <span class="utilikit uk-tc--ff0000">Text</span>
    </div>';

    $property->expects($this->once())
      ->method('getValue')
      ->willReturn($htmlContent);

    $fieldItem->expects($this->once())
      ->method('getProperties')
      ->willReturn([$property]);

    $field->expects($this->once())
      ->method('getIterator')
      ->willReturn(new \ArrayIterator([$fieldItem]));

    $entity->expects($this->once())
      ->method('getFields')
      ->willReturn([$field]);

    $result = $this->contentScanner->scanEntity($entity);

    $expected = ['uk-pd--20', 'uk-mg--10', 'uk-tc--ff0000'];
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests scanEntity with non-fieldable entity.
   *
   * @covers ::scanEntity
   */
  public function testScanEntityNonFieldable() {
    $entity = $this->createMock('\Drupal\Core\Entity\EntityInterface');
    $result = $this->contentScanner->scanEntity($entity);
    $this->assertEquals([], $result);
  }

  /**
   * Tests scanEntityBatch method.
   *
   * @covers ::scanEntityBatch
   */
  public function testScanEntityBatch() {
    $entities = [];

    // Create mock entities with different classes.
    for ($i = 0; $i < 3; $i++) {
      $entity = $this->createMock(FieldableEntityInterface::class);
      $field = $this->createMock(FieldItemListInterface::class);
      $fieldItem = $this->createMock(FieldItemInterface::class);
      $property = $this->createMock(TypedDataInterface::class);

      $classes = ['uk-pd--' . (20 + $i * 10), 'uk-mg--' . (10 + $i * 5)];
      $htmlContent = sprintf(
        '<div class="utilikit %s">Content %d</div>',
        implode(' ', $classes),
        $i
      );

      $property->method('getValue')->willReturn($htmlContent);
      $fieldItem->method('getProperties')->willReturn([$property]);
      $field->method('getIterator')->willReturn(new \ArrayIterator([$fieldItem]));
      $entity->method('getFields')->willReturn([$field]);

      $entities[] = $entity;
    }

    $result = $this->contentScanner->scanEntityBatch($entities);

    $expected = [
      'uk-pd--20', 'uk-mg--10',
      'uk-pd--30', 'uk-mg--15',
      'uk-pd--40', 'uk-mg--20',
    ];

    $this->assertEquals(sorted($expected), sorted($result));
  }

  /**
   * Tests edge cases in HTML extraction.
   *
   * @covers ::extractClassesFromHtml
   */
  public function testExtractClassesEdgeCases() {
    // Test malformed HTML.
    $html = '<div class="utilikit uk-pd--20" <span>Broken</div>';
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals(['uk-pd--20'], $result);

    // Test with special characters in classes.
    $html = '<div class="utilikit uk-pd--20 uk-tc--ff0000-50">Content</div>';
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals(['uk-pd--20', 'uk-tc--ff0000-50'], $result);

    // Test with extra whitespace.
    $html = '<div class="  utilikit   uk-pd--20   uk-mg--10  ">Content</div>';
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals(['uk-pd--20', 'uk-mg--10'], $result);
  }

  /**
   * Tests performance with large content containing many utility classes.
   */
  public function testPerformanceWithLargeContent() {
    // Enable auto-update for nodes.
    $this->config('utilikit.settings')
      ->set('rendering_mode', 'static')
      ->set('update_on_node_save', TRUE)
      ->save();

    // Generate large content with many utility classes.
    $classCount = 500;
    $uniqueClasses = [];
    $htmlContent = '<div class="utilikit">';

    // Generate diverse utility classes.
    $prefixes = ['pd', 'mg', 'bg', 'tc', 'wd', 'ht', 'fs', 'br', 'dp', 'ps'];
    $breakpoints = ['', 'sm-', 'md-', 'lg-', 'xl-'];

    for ($i = 0; $i < $classCount; $i++) {
      $breakpoint = $breakpoints[array_rand($breakpoints)];
      $prefix = $prefixes[array_rand($prefixes)];
      $value = rand(1, 100);

      // Generate different value types based on prefix.
      switch ($prefix) {
        case 'bg':
        case 'tc':
          // Color values.
          $value = sprintf('%06x', rand(0, 0xFFFFFF));
          break;

        case 'dp':
          $value = ['none', 'block', 'flex', 'grid'][array_rand(['none', 'block', 'flex', 'grid'])];
          break;

        case 'ps':
          $value = ['relative', 'absolute', 'fixed'][array_rand(['relative', 'absolute', 'fixed'])];
          break;

        default:
          // Numeric values.
          $value = rand(1, 100);
      }

      $class = "uk-{$breakpoint}{$prefix}--{$value}";
      $uniqueClasses[] = $class;
    }

    // Create nested HTML structure with repeated classes.
    for ($i = 0; $i < 100; $i++) {
      $classes = array_slice($uniqueClasses, $i * 5, 5);
      $htmlContent .= sprintf(
        '<div class="utilikit %s"><p class="utilikit %s">Content %d</p></div>',
        implode(' ', $classes),
        implode(' ', array_slice($uniqueClasses, $i * 2, 2)),
        $i
      );
    }
    $htmlContent .= '</div>';

    // Start performance timer.
    $startTime = microtime(TRUE);
    $startMemory = memory_get_usage();

    // Create node with large content.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Performance Test Node',
      'body' => [
        'value' => $htmlContent,
        'format' => 'full_html',
      ],
    ]);

    // Save the node (should trigger CSS generation)
    $node->save();

    // End performance timer.
    $endTime = microtime(TRUE);
    $endMemory = memory_get_usage();

    $executionTime = $endTime - $startTime;
    // Convert to MB.
    $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

    // Assert performance thresholds.
    $this->assertLessThan(5.0, $executionTime, 'Entity save with large content should complete within 5 seconds');
    $this->assertLessThan(50, $memoryUsed, 'Memory usage should not exceed 50MB');

    // Verify CSS was generated.
    $generatedCss = $this->stateManager->getGeneratedCss();
    $this->assertNotEmpty($generatedCss, 'CSS should be generated');

    // Verify all unique classes were processed.
    $knownClasses = $this->stateManager->getKnownClasses();
    $this->assertGreaterThanOrEqual(count(array_unique($uniqueClasses)), count($knownClasses), 'All unique classes should be tracked');

    // Test subsequent save performance (should be faster with cached data)
    $startTime2 = microtime(TRUE);

    $node->set('title', 'Updated Performance Test Node');
    $node->save();

    $endTime2 = microtime(TRUE);
    $executionTime2 = $endTime2 - $startTime2;

    $this->assertLessThan($executionTime, $executionTime2, 'Subsequent saves should be faster due to caching');

    // Test with multiple large entities saved concurrently.
    $nodes = [];
    for ($i = 0; $i < 5; $i++) {
      $nodes[] = Node::create([
        'type' => 'article',
        'title' => "Concurrent Test Node $i",
        'body' => [
          'value' => $htmlContent,
          'format' => 'full_html',
        ],
      ]);
    }

    $concurrentStartTime = microtime(TRUE);

    foreach ($nodes as $concurrentNode) {
      $concurrentNode->save();
    }

    $concurrentEndTime = microtime(TRUE);
    $concurrentExecutionTime = $concurrentEndTime - $concurrentStartTime;

    $this->assertLessThan(15.0, $concurrentExecutionTime, 'Multiple large entity saves should complete within 15 seconds');

    // Verify the CSS file size is reasonable.
    $cssSize = strlen($generatedCss);
    // 1MB limit
    $this->assertLessThan(1024 * 1024, $cssSize, 'Generated CSS should be less than 1MB');

    // Log performance metrics for debugging.
    $this->pass(sprintf(
      'Performance metrics: Single save: %.3fs, Memory: %.2fMB, Subsequent save: %.3fs, Concurrent saves: %.3fs, CSS size: %dKB',
      $executionTime,
      $memoryUsed,
      $executionTime2,
      $concurrentExecutionTime,
      $cssSize / 1024
    ));
  }

  /**
   * Tests HTML without utilikit marker class.
   *
   * @covers ::extractClassesFromHtml
   */
  public function testHtmlWithoutUtilikit() {
    $html = '<div class="uk-pd--20 uk-mg--10">No utilikit class</div>';
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals([], $result);

    // Test quick return for non-utilikit content.
    $html = '<p>Simple paragraph with no classes</p>';
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals([], $result);
  }

}
