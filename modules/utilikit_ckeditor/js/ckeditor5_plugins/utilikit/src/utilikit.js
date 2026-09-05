/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore utilikitediting utilikitui

import { Plugin } from 'ckeditor5/src/core';
import UtilikitEditing from './utilikitediting';
import UtilikitUI from './utilikitui';

/**
 * Applies UtiliKit utility classes to the element being edited.
 *
 * Split the way core splits its own plugins: an editing half that teaches the
 * model about the class attribute, and a UI half that puts a button on the
 * toolbar. Neither is useful alone.
 *
 * @private
 */
export default class Utilikit extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [UtilikitEditing, UtilikitUI];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'Utilikit';
  }
}
