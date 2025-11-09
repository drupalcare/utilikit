<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
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
  protected UtilikitContentScanner $contentScanner;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface&MockObject $entityTypeManager;

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
  public function testExtractClassesFromHtmlValid(string $html, array $expected): void {
    $result = $this->contentScanner->extractClassesFromHtml($html);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for valid HTML extraction tests.
   *
   * @return array<string, array>
   *   html: string, expected: array<string>
   */
  public static function providerValidHtml(): array {
    return [
      'single utility class' => [
        'html' => '<div class="utilikit uk-pd--20">Content</div>',
        'expected' => ['uk-pd--20'],
      ],
      'multiple utility classes' => [
        'html' => '<div class="utilikit uk-pd--20 uk-mg--10 uk-bg--ff0000">Content</div>',
        'expected' => ['uk-pd--20', 'uk-mg--10', 'uk-bg--ff0000'],
      ],
      'nested elements' => [
        'html' => '<div class="utilikit uk-pd--20"><span class="utilikit uk-tc--333333">Text</span></div>',
        'expected' => ['uk-pd--20', 'uk-tc--333333'],
      ],
      'responsive classes' => [
        'html' => '<div class="utilikit uk-pd--20 uk-md-pd--30 uk-lg-pd--40">Content</div>',
        'expected' => ['uk-pd--20', 'uk-md-pd--30', 'uk-lg-pd--40'],
      ],
      'mixed with non-utility classes' => [
        'html' => '<div class="utilikit uk-pd--20 other-class uk-mg--10 random">Content</div>',
        'expected' => ['uk-pd--20', 'uk-mg--10'],
      ],
      'no utilikit marker class' => [
        'html' => '<div class="uk-pd--20 uk-mg--10">Content</div>',
        'expected' => [],
      ],
      'empty class attribute' => [
        'html' => '<div class="">Content</div>',
        'expected' => [],
      ],
      'no classes' => [
        'html' => '<div>Content</div>',
        'expected' => [],
      ],
    ];
  }

  /**
   * Tests scanEntity method with fieldable entity.
   *
   * @covers ::scanEntity
   */
  public function testScanEntityFieldable(): void {
    $entity = $this->createMock(FieldableEntityInterface::class);

    // Create mock field with utility classes.
    $field = $this->createMock(FieldItemListInterface::class);
    $fieldItem = $this->createMock(FieldItemInterface::class);
    $property = $this->createMock(TypedDataInterface::class);

    $htmlContent = '<div class="utilikit uk-pd--20 uk-mg--10">Test content</div>';
    $property->method('getValue')->willReturn($htmlContent);

    $fieldItem->method('getProperties')->willReturn([$property]);
    $field->method('getIterator')->willReturn(new \ArrayIterator([$fieldItem]));

    $entity->method('getFields')->willReturn([$field]);

    $result = $this->contentScanner->scanEntity($entity);

    $this->assertCount(2, $result);
    $this->assertContains('uk-pd--20', $result);
    $this->assertContains('uk-mg--10', $result);
  }

  /**
   * Tests scanEntity method with non-fieldable entity.
   *
   * @covers ::scanEntity
   */
  public function testScanEntityNonFieldable(): void {
    $entity = $this->createMock('\Drupal\Core\Entity\EntityInterface');
    $result = $this->contentScanner->scanEntity($entity);
    $this->assertEquals([], $result);
  }

  /**
   * Tests scanEntityBatch method.
   *
   * @covers ::scanEntityBatch
   */
  public function testScanEntityBatch(): void {
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
  public function testExtractClassesEdgeCases(): void {
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
   *
   * @covers ::extractClassesFromHtml
   */
  public function testPerformanceWithLargeContent(): void {
    // Generate large content with many utility classes.
    $classCount = 500;
    $uniqueClasses = [];
    $htmlContent = '<div class="utilikit">';

    // Generate diverse utility classes.
    for ($i = 0; $i < $classCount; $i++) {
      $class = sprintf('uk-%s--%d', ['pd', 'mg', 'bg', 'tc', 'dp'][$i % 5], $i);
      $uniqueClasses[] = $class;
      $htmlContent .= sprintf('<span class="utilikit %s">Item %d</span>', $class, $i);
    }

    $htmlContent .= '</div>';

    $startTime = microtime(TRUE);
    $result = $this->contentScanner->extractClassesFromHtml($htmlContent);
    $endTime = microtime(TRUE);

    // Verify all classes were extracted.
    $this->assertCount($classCount, $result);
    foreach ($uniqueClasses as $class) {
      $this->assertContains($class, $result);
    }

    // Performance assertion - should process 500 classes in under 0.1 seconds.
    $this->assertLessThan(0.1, $endTime - $startTime, 'Large content scanning took too long');
  }

  /**
   * Tests scanAllContent method.
   *
   * @covers ::scanAllContent
   */
  public function testScanAllContent(): void {
    // Set up entity types.
    $entityTypes = ['node', 'block_content'];

    foreach ($entityTypes as $entityType) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $entityTypeDefinition = $this->createMock(EntityTypeInterface::class);

      // Mock entities for each type.
      $entities = [];
      for ($i = 0; $i < 2; $i++) {
        $entity = $this->createMock(FieldableEntityInterface::class);
        $field = $this->createMock(FieldItemListInterface::class);
        $fieldItem = $this->createMock(FieldItemInterface::class);
        $property = $this->createMock(TypedDataInterface::class);

        $class = sprintf('uk-%s--%d', $entityType, $i);
        $htmlContent = sprintf('<div class="utilikit %s">Content</div>', $class);

        $property->method('getValue')->willReturn($htmlContent);
        $fieldItem->method('getProperties')->willReturn([$property]);
        $field->method('getIterator')->willReturn(new \ArrayIterator([$fieldItem]));
        $entity->method('getFields')->willReturn([$field]);

        $entities[] = $entity;
      }

      $storage->method('loadMultiple')->willReturn($entities);

      $this->entityTypeManager
        ->method('getStorage')
        ->with($entityType)
        ->willReturn($storage);

      $this->entityTypeManager
        ->method('getDefinition')
        ->with($entityType)
        ->willReturn($entityTypeDefinition);
    }

    $this->entityTypeManager
      ->method('getDefinitions')
      ->willReturn(['node' => TRUE, 'block_content' => TRUE]);

    $result = $this->contentScanner->scanAllContent();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('classes', $result);
    $this->assertArrayHasKey('scanned_count', $result);
    // 2 entities * 2 types
    $this->assertCount(4, $result['classes']);
    $this->assertEquals(4, $result['scanned_count']);
  }

  /**
   * Tests duplicate class removal.
   *
   * @covers ::extractClassesFromHtml
   */
  public function testDuplicateClassRemoval(): void {
    $html = '<div class="utilikit uk-pd--20 uk-pd--20 uk-mg--10 uk-mg--10">Content</div>';
    $result = $this->contentScanner->extractClassesFromHtml($html);

    // Should have no duplicates.
    $this->assertCount(2, $result);
    $this->assertEquals(['uk-pd--20', 'uk-mg--10'], $result);
  }

  /**
   * Helper function to sort arrays for comparison.
   *
   * @param array<string> $array
   *   Array to sort.
   *
   * @return array<string>
   *   Sorted array.
   */
  protected function sorted(array $array): array {
    sort($array);
    return $array;
  }

}
