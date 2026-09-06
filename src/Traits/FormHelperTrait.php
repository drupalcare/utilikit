<?php

declare(strict_types=1);

namespace Drupal\utilikit\Traits;

/**
 * Trait providing standardized form field creation methods.
 *
 * This trait offers a collection of helper methods for creating common form
 * elements with consistent structure and configuration. It simplifies form
 * building by providing a unified API for field creation with support for
 * states, attributes, and various field types.
 */
trait FormHelperTrait {

  /**
   * Creates a generic form field with common properties.
   *
   * This is the base method for creating form fields with consistent
   * structure. It handles common properties like title, description,
   * default value, and various field-specific attributes.
   *
   * @param string $type
   *   The form field type (e.g., 'textfield', 'checkbox', 'select').
   * @param array<string, mixed> $config
   *   Configuration array with the following optional keys:
   *   - title: Field title/label
   *   - description: Field description/help text
   *   - default_value: Default field value
   *   - options: Array of options for select/checkbox fields
   *   - states: Form API states for conditional visibility/behavior
   *   - attributes: HTML attributes for the field
   *   - min: Minimum value (for number fields)
   *   - max: Maximum value (for number fields)
   *   - step: Step value (for number fields)
   *   - field_suffix: Text to append after the field
   *   - prefix: HTML prefix for the field
   *   - suffix: HTML suffix for the field.
   *
   * @return array<string, mixed>
   *   Form field render array with configured properties.
   */
  protected function createFormField(string $type, array $config): array {
    $field = [
      '#type' => $type,
      '#title' => $config['title'] ?? '',
      '#description' => $config['description'] ?? '',
      '#default_value' => $config['default_value'] ?? NULL,
    ];

    if (isset($config['options'])) {
      $field['#options'] = $config['options'];
    }

    if (isset($config['states'])) {
      $field['#states'] = $config['states'];
    }

    if (isset($config['attributes'])) {
      $field['#attributes'] = $config['attributes'];
    }

    if (isset($config['min'])) {
      $field['#min'] = $config['min'];
    }

    if (isset($config['max'])) {
      $field['#max'] = $config['max'];
    }

    if (isset($config['step'])) {
      $field['#step'] = $config['step'];
    }

    if (isset($config['field_suffix'])) {
      $field['#field_suffix'] = $config['field_suffix'];
    }

    if (isset($config['prefix'])) {
      $field['#prefix'] = $config['prefix'];
    }

    if (isset($config['suffix'])) {
      $field['#suffix'] = $config['suffix'];
    }

    return $field;
  }

  /**
   * Creates a checkbox form field.
   *
   * Provides a simplified API for creating checkbox fields with consistent
   * structure and optional configuration.
   *
   * @param string|\Stringable $title
   *   The checkbox label/title.
   * @param string|\Stringable $description
   *   The checkbox description/help text.
   * @param bool $defaultValue
   *   The default checked state (TRUE for checked, FALSE for unchecked).
   * @param array<string, mixed> $options
   *   Optional additional configuration (states, attributes, etc.).
   *
   * @return array<string, mixed>
   *   Checkbox form field render array.
   */
  protected function createCheckboxField(string|\Stringable $title, string|\Stringable $description, bool $defaultValue, array $options = []): array {
    return $this->createFormField('checkbox', [
      'title' => $title,
      'description' => $description,
      'default_value' => $defaultValue,
    ] + $options);
  }

  /**
   * Creates a number input form field.
   *
   * Provides a simplified API for creating number input fields with
   * validation and formatting options.
   *
   * @param string $title
   *   The field label/title.
   * @param string $description
   *   The field description/help text.
   * @param int $defaultValue
   *   The default numeric value.
   * @param array<string, mixed> $options
   *   Optional additional configuration including:
   *   - min: Minimum allowed value
   *   - max: Maximum allowed value
   *   - step: Step increment for the field
   *   - field_suffix: Text to display after the input (e.g., "px", "ms")
   *   - attributes: HTML attributes for styling.
   *
   * @return array<string, mixed>
   *   Number input form field render array.
   */
  protected function createNumberField(string|\Stringable $title, string|\Stringable $description, int $defaultValue, array $options = []): array {
    return $this->createFormField('number', [
      'title' => $title,
      'description' => $description,
      'default_value' => $defaultValue,
    ] + $options);
  }

  /**
   * Creates a checkboxes (multiple checkbox) form field.
   *
   * Provides a simplified API for creating multi-select checkbox fields
   * where users can select multiple options from a list.
   *
   * @param string $title
   *   The field label/title.
   * @param string $description
   *   The field description/help text.
   * @param array<string, string|\Stringable> $optionsList
   *   Associative array of options where keys are values and values are
   *   labels (e.g., ['sm' => 'Small', 'md' => 'Medium']).
   * @param array<int|string, string> $defaultValue
   *   Array of default selected values (keys from $optionsList).
   * @param array<string, mixed> $options
   *   Optional additional configuration (states, attributes, etc.).
   *
   * @return array<string, mixed>
   *   Checkboxes form field render array.
   */
  protected function createCheckboxesField(string|\Stringable $title, string|\Stringable $description, array $optionsList, array $defaultValue, array $options = []): array {
    return $this->createFormField('checkboxes', [
      'title' => $title,
      'description' => $description,
      'options' => $optionsList,
      'default_value' => $defaultValue,
    ] + $options);
  }

  /**
   * Creates a select dropdown form field.
   *
   * Provides a simplified API for creating single-select dropdown fields
   * where users can choose one option from a list.
   *
   * @param string $title
   *   The field label/title.
   * @param string $description
   *   The field description/help text.
   * @param array<string, string|\Stringable> $optionsList
   *   Associative array of options where keys are values and values are
   *   labels (e.g., ['off' => 'Disabled', 'on' => 'Enabled']).
   * @param string $defaultValue
   *   The default selected value (key from $optionsList).
   * @param array<string, mixed> $options
   *   Optional additional configuration (states, attributes, etc.).
   *
   * @return array<string, mixed>
   *   Select form field render array.
   */
  protected function createSelectField(string|\Stringable $title, string|\Stringable $description, array $optionsList, string $defaultValue, array $options = []): array {
    return $this->createFormField('select', [
      'title' => $title,
      'description' => $description,
      'options' => $optionsList,
      'default_value' => $defaultValue,
    ] + $options);
  }

  /**
   * Creates a submit button form element.
   *
   * Provides a simplified API for creating submit buttons with custom
   * styling, submission handlers, and HTML attributes.
   *
   * @param string $value
   *   The button text/label.
   * @param list<string> $submit
   *   Array of submit handler methods (e.g., ['::methodName']).
   * @param list<string> $classes
   *   Optional array of CSS classes to apply to the button
   *   (e.g., ['button--primary', 'button--danger']).
   * @param array<string, mixed> $attributes
   *   Optional array of HTML attributes to apply to the button
   *   (e.g., ['onclick' => 'return confirm("Are you sure?")']').
   *
   * @return array<string, mixed>
   *   Submit button form element render array.
   */
  protected function createSubmitButton(string|\Stringable $value, array $submit, array $classes = [], array $attributes = []): array {
    $button = [
      '#type' => 'submit',
      '#value' => $value,
      '#submit' => $submit,
    ];

    if (!empty($classes)) {
      $button['#attributes']['class'] = $classes;
    }

    if (!empty($attributes)) {
      $button['#attributes'] = array_merge($button['#attributes'] ?? [], $attributes);
    }

    return $button;
  }

}
