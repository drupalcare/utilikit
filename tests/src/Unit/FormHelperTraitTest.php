<?php

namespace Drupal\Tests\utilikit\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utilikit\Traits\FormHelperTrait;

/**
 * Tests FormHelperTrait functionality.
 *
 * @group utilikit
 * @coversDefaultClass \Drupal\utilikit\Traits\FormHelperTrait
 */
class FormHelperTraitTest extends UnitTestCase {

  /**
   * Test class that uses the FormHelperTrait.
   *
   * @var \Drupal\Tests\utilikit\Unit\Traits\FormHelperTraitTestClass
   */
  protected $traitObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->traitObject = new FormHelperTraitTestClass();
  }

  /**
   * Tests createFormField with basic configuration.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldBasic() {
    $config = [
      'title' => 'Test Field',
      'description' => 'Test description',
      'default_value' => 'test_value',
    ];

    $field = $this->traitObject->createFormFieldPublic('textfield', $config);

    $this->assertEquals('textfield', $field['#type']);
    $this->assertEquals('Test Field', $field['#title']);
    $this->assertEquals('Test description', $field['#description']);
    $this->assertEquals('test_value', $field['#default_value']);
  }

  /**
   * Tests createFormField with select options.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldWithOptions() {
    $config = [
      'title' => 'Select Field',
      'options' => ['option1' => 'Option 1', 'option2' => 'Option 2'],
      'default_value' => 'option1',
    ];

    $field = $this->traitObject->createFormFieldPublic('select', $config);

    $this->assertEquals('select', $field['#type']);
    $this->assertEquals('Select Field', $field['#title']);
    $this->assertArrayHasKey('#options', $field);
    $this->assertEquals(['option1' => 'Option 1', 'option2' => 'Option 2'], $field['#options']);
    $this->assertEquals('option1', $field['#default_value']);
  }

  /**
   * Tests createFormField with states.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldWithStates() {
    $states = [
      'visible' => [
        ':input[name="test_field"]' => ['checked' => TRUE],
      ],
    ];

    $config = [
      'title' => 'Conditional Field',
      'states' => $states,
    ];

    $field = $this->traitObject->createFormFieldPublic('textfield', $config);

    $this->assertArrayHasKey('#states', $field);
    $this->assertEquals($states, $field['#states']);
  }

  /**
   * Tests createFormField with attributes.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldWithAttributes() {
    $attributes = [
      'class' => ['custom-class'],
      'data-test' => 'test-value',
    ];

    $config = [
      'title' => 'Field with Attributes',
      'attributes' => $attributes,
    ];

    $field = $this->traitObject->createFormFieldPublic('textfield', $config);

    $this->assertArrayHasKey('#attributes', $field);
    $this->assertEquals($attributes, $field['#attributes']);
  }

  /**
   * Tests createFormField with number field properties.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldNumberProperties() {
    $config = [
      'title' => 'Number Field',
      'min' => 0,
      'max' => 100,
      'step' => 5,
      'field_suffix' => 'px',
    ];

    $field = $this->traitObject->createFormFieldPublic('number', $config);

    $this->assertEquals(0, $field['#min']);
    $this->assertEquals(100, $field['#max']);
    $this->assertEquals(5, $field['#step']);
    $this->assertEquals('px', $field['#field_suffix']);
  }

  /**
   * Tests createFormField with prefix and suffix.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldPrefixSuffix() {
    $config = [
      'title' => 'Field with Prefix/Suffix',
      'prefix' => '<div class="prefix">',
      'suffix' => '</div>',
    ];

    $field = $this->traitObject->createFormFieldPublic('textfield', $config);

    $this->assertEquals('<div class="prefix">', $field['#prefix']);
    $this->assertEquals('</div>', $field['#suffix']);
  }

  /**
   * Tests createFormField with empty configuration.
   *
   * @covers ::createFormField
   */
  public function testCreateFormFieldEmptyConfig() {
    $field = $this->traitObject->createFormFieldPublic('textfield', []);

    $this->assertEquals('textfield', $field['#type']);
    $this->assertEquals('', $field['#title']);
    $this->assertEquals('', $field['#description']);
    $this->assertNull($field['#default_value']);
    $this->assertArrayNotHasKey('#options', $field);
    $this->assertArrayNotHasKey('#states', $field);
    $this->assertArrayNotHasKey('#attributes', $field);
  }

  /**
   * Tests createCheckbox helper method.
   *
   * @covers ::createCheckbox
   */
  public function testCreateCheckbox() {
    $checkbox = $this->traitObject->createCheckboxPublic(
      'Test Checkbox',
      'This is a test checkbox',
      TRUE,
      ['class' => ['test-class']]
    );

    $this->assertEquals('checkbox', $checkbox['#type']);
    $this->assertEquals('Test Checkbox', $checkbox['#title']);
    $this->assertEquals('This is a test checkbox', $checkbox['#description']);
    $this->assertTrue($checkbox['#default_value']);
    $this->assertEquals(['class' => ['test-class']], $checkbox['#attributes']);
  }

  /**
   * Tests createCheckbox with minimal parameters.
   *
   * @covers ::createCheckbox
   */
  public function testCreateCheckboxMinimal() {
    $checkbox = $this->traitObject->createCheckboxPublic('Simple Checkbox');

    $this->assertEquals('checkbox', $checkbox['#type']);
    $this->assertEquals('Simple Checkbox', $checkbox['#title']);
    $this->assertEquals('', $checkbox['#description']);
    $this->assertFalse($checkbox['#default_value']);
    $this->assertArrayNotHasKey('#attributes', $checkbox);
  }

  /**
   * Tests createTextfield helper method.
   *
   * @covers ::createTextfield
   */
  public function testCreateTextfield() {
    $textfield = $this->traitObject->createTextfieldPublic(
      'Test Textfield',
      'Enter some text',
      'default text',
      ['placeholder' => 'Enter text here...']
    );

    $this->assertEquals('textfield', $textfield['#type']);
    $this->assertEquals('Test Textfield', $textfield['#title']);
    $this->assertEquals('Enter some text', $textfield['#description']);
    $this->assertEquals('default text', $textfield['#default_value']);
    $this->assertEquals(['placeholder' => 'Enter text here...'], $textfield['#attributes']);
  }

  /**
   * Tests createSelect helper method.
   *
   * @covers ::createSelect
   */
  public function testCreateSelect() {
    $options = ['opt1' => 'Option 1', 'opt2' => 'Option 2'];

    $select = $this->traitObject->createSelectPublic(
      'Test Select',
      'Choose an option',
      $options,
      'opt2'
    );

    $this->assertEquals('select', $select['#type']);
    $this->assertEquals('Test Select', $select['#title']);
    $this->assertEquals('Choose an option', $select['#description']);
    $this->assertEquals($options, $select['#options']);
    $this->assertEquals('opt2', $select['#default_value']);
  }

  /**
   * Tests createNumber helper method.
   *
   * @covers ::createNumber
   */
  public function testCreateNumber() {
    $number = $this->traitObject->createNumberPublic(
      'Test Number',
      'Enter a number',
      50,
      0,
      100,
      5,
      'px'
    );

    $this->assertEquals('number', $number['#type']);
    $this->assertEquals('Test Number', $number['#title']);
    $this->assertEquals('Enter a number', $number['#description']);
    $this->assertEquals(50, $number['#default_value']);
    $this->assertEquals(0, $number['#min']);
    $this->assertEquals(100, $number['#max']);
    $this->assertEquals(5, $number['#step']);
    $this->assertEquals('px', $number['#field_suffix']);
  }

  /**
   * Tests createSubmitButton helper method.
   *
   * @covers ::createSubmitButton
   */
  public function testCreateSubmitButton() {
    $button = $this->traitObject->createSubmitButtonPublic(
      'Save Settings',
      ['::submitForm'],
      ['button--primary'],
      ['onclick' => 'return confirm("Save?");']
    );

    $this->assertEquals('submit', $button['#type']);
    $this->assertEquals('Save Settings', $button['#value']);
    $this->assertEquals(['::submitForm'], $button['#submit']);
    $this->assertEquals(['button--primary'], $button['#attributes']['class']);
    $this->assertEquals('return confirm("Save?");', $button['#attributes']['onclick']);
  }

  /**
   * Tests createSubmitButton with minimal parameters.
   *
   * @covers ::createSubmitButton
   */
  public function testCreateSubmitButtonMinimal() {
    $button = $this->traitObject->createSubmitButtonPublic(
      'Submit',
      ['::submitHandler']
    );

    $this->assertEquals('submit', $button['#type']);
    $this->assertEquals('Submit', $button['#value']);
    $this->assertEquals(['::submitHandler'], $button['#submit']);
    $this->assertArrayNotHasKey('class', $button['#attributes'] ?? []);
  }

}

/**
 * Test class that exposes FormHelperTrait protected methods for testing.
 */
class FormHelperTraitTestClass {

  use FormHelperTrait;

  /**
   * Public wrapper for createFormField method.
   */
  public function createFormFieldPublic(string $type, array $config): array {
    return $this->createFormField($type, $config);
  }

  /**
   * Public wrapper for createCheckbox method.
   */
  public function createCheckboxPublic(string $title, string $description = '', bool $defaultValue = FALSE, array $attributes = []): array {
    return $this->createCheckbox($title, $description, $defaultValue, $attributes);
  }

  /**
   * Public wrapper for createTextfield method.
   */
  public function createTextfieldPublic(string $title, string $description = '', string $defaultValue = '', array $attributes = []): array {
    return $this->createTextfield($title, $description, $defaultValue, $attributes);
  }

  /**
   * Public wrapper for createSelect method.
   */
  public function createSelectPublic(string $title, string $description, array $options, $defaultValue = NULL): array {
    return $this->createSelect($title, $description, $options, $defaultValue);
  }

  /**
   * Public wrapper for createNumber method.
   */
  public function createNumberPublic(string $title, string $description, $defaultValue = NULL, ?int $min = NULL, ?int $max = NULL, ?int $step = NULL, string $suffix = ''): array {
    return $this->createNumber($title, $description, $defaultValue, $min, $max, $step, $suffix);
  }

  /**
   * Public wrapper for createSubmitButton method.
   */
  public function createSubmitButtonPublic(string $value, array $submit, array $classes = [], array $attributes = []): array {
    return $this->createSubmitButton($value, $submit, $classes, $attributes);
  }

}
