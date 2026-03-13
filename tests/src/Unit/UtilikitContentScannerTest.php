<?php

declare(strict_types=1);

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Service\UtilikitContentScanner;
use Drupal\utilikit\Service\UtilikitRules;
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
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface&MockObject $configFactory;

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
   * Rules service (real instance so getRules() works).
   *
   * @var \Drupal\utilikit\Service\UtilikitRules
   */
  protected UtilikitRules $rulesService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('scanning_entity_types')->willReturn(['node', 'block_content', 'paragraph']);
    $this->configFactory->method('get')->with('utilikit.settings')->willReturn($config);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->rulesService = new UtilikitRules();

    $this->contentScanner = new UtilikitContentScanner(
      $this->configFactory,
      $this->entityTypeManager,
      $this->logger,
      $this->rulesService
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

    $fieldItem = $this->createMock(FieldItemInterface::class);
    $property = $this->createMock(TypedDataInterface::class);

    $htmlContent = '<div class="utilikit uk-pd--20 uk-mg--10">Test content</div>';
    $property->method('getValue')->willReturn($htmlContent);
    $fieldItem->method('getProperties')->willReturn([$property]);

    $field = $this->createFieldItemListMock([$fieldItem]);
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

    for ($i = 0; $i < 3; $i++) {
      $entity = $this->createMock(FieldableEntityInterface::class);
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
      $field = $this->createFieldItemListMock([$fieldItem]);
      $entity->method('getFields')->willReturn([$field]);

      $entities[] = $entity;
    }

    $result = $this->contentScanner->scanEntityBatch($entities);

    $expected = [
      'uk-pd--20', 'uk-mg--10',
      'uk-pd--30', 'uk-mg--15',
      'uk-pd--40', 'uk-mg--20',
    ];

    $this->assertEquals($this->sorted($expected), $this->sorted($result));
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
      $class = sprintf('uk-%s--%d', ['pd', 'mg', 'wd', 'ht', 'tp'][$i % 5], $i + 1);
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

    // Performance assertion - allow up to 2s to avoid flakiness on slow CI.
    $this->assertLessThan(2.0, $endTime - $startTime, 'Large content scanning took too long');
  }

  /**
   * Tests scanAllContent method.
   *
   * @covers ::scanAllContent
   */
  public function testScanAllContent(): void {
    // Set up entity types.
    $entityTypes = ['node', 'block_content'];
    $definitionsMap = [];
    $storageMap = [];
    $definitionMap = [];
    $classIndex = 0;

    foreach ($entityTypes as $entityType) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $entityTypeDefinition = $this->createMock(EntityTypeInterface::class);
      $entityTypeDefinition->method('entityClassImplements')
        ->with(FieldableEntityInterface::class)
        ->willReturn(TRUE);
      $entityTypeDefinition->method('id')
        ->willReturn($entityType);
      $entityTypeDefinition->method('getKey')
        ->with('id')
        ->willReturn('id');

      $definitionsMap[$entityType] = $entityTypeDefinition;

      // Mock entities for each type with unique classes.
      $entities = [];
      for ($i = 0; $i < 2; $i++) {
        $entity = $this->createMock(FieldableEntityInterface::class);
        $fieldItem = $this->createMock(FieldItemInterface::class);
        $property = $this->createMock(TypedDataInterface::class);

        $classIndex++;
        $class = sprintf('uk-pd--%d', $classIndex * 10);
        $htmlContent = sprintf('<div class="utilikit %s">Content</div>', $class);

        $property->method('getValue')->willReturn($htmlContent);
        $fieldItem->method('getProperties')->willReturn([$property]);
        $field = $this->createFieldItemListMock([$fieldItem]);
        $entity->method('getFields')->willReturn([$field]);

        $entities[] = $entity;
      }

      $storage->method('loadMultiple')->willReturn($entities);

      // Mock getQuery to return entity IDs then empty on second call.
      $query = $this->createMock(QueryInterface::class);
      $query->method('accessCheck')->willReturnSelf();
      $query->method('condition')->willReturnSelf();
      $query->method('sort')->willReturnSelf();
      $query->method('range')->willReturnSelf();
      $query->method('execute')->willReturnOnConsecutiveCalls(
        [1 => 1, 2 => 2],
        []
      );
      $storage->method('getQuery')->willReturn($query);

      $storageMap[] = [$entityType, $storage];
      $definitionMap[] = [$entityType, $entityTypeDefinition];
    }

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturnMap($storageMap);

    $this->entityTypeManager
      ->method('getDefinition')
      ->willReturnMap($definitionMap);

    $this->entityTypeManager
      ->method('getDefinitions')
      ->willReturn($definitionsMap);

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
    $result = array_values($result);
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

  /**
   * Creates a mock FieldItemListInterface that iterates over given items.
   *
   * @param array $items
   *   The field items to iterate over.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface&\PHPUnit\Framework\MockObject\MockObject
   *   A mock field item list.
   */
  protected function createFieldItemListMock(array $items): FieldItemListInterface&MockObject {
    $field = $this->createMock(FieldItemListInterface::class);
    $iterator = new \ArrayIterator($items);
    $field->method('current')->willReturnCallback(function () use ($iterator) {
      return $iterator->current();
    });
    $field->method('key')->willReturnCallback(function () use ($iterator) {
      return $iterator->key();
    });
    $field->method('next')->willReturnCallback(function () use ($iterator): void {
      $iterator->next();
    });
    $field->method('rewind')->willReturnCallback(function () use ($iterator): void {
      $iterator->rewind();
    });
    $field->method('valid')->willReturnCallback(function () use ($iterator) {
      return $iterator->valid();
    });
    return $field;
  }

}
