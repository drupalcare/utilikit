/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore utilikit

import { Command } from 'ckeditor5/src/core';

/**
 * Adds or removes UtiliKit classes on the nearest block element.
 *
 * WHY THE BLOCK AND NOT THE SELECTION. UtiliKit's utilities are element-level:
 * padding, background, display, border. Applying them to a text range would
 * mean wrapping it in a span, which changes the document structure to satisfy
 * the tool rather than the author. So this walks up to the block the caret is
 * in — the paragraph, heading or list item — and puts the classes there.
 *
 * @private
 */
export default class UtilikitCommand extends Command {
  /**
   * @inheritdoc
   */
  refresh() {
    const block = this._closestBlock();

    this.isEnabled = block !== null;
    this.value = block ? block.getAttribute('utilikitClasses') || '' : '';
  }

  /**
   * Applies a class string to the current block.
   *
   * @param {Object} options
   *   Options for the command.
   * @param {string} options.classes
   *   The class string to apply. Empty removes UtiliKit's classes entirely.
   * @param {string} options.marker
   *   The marker class the engine requires.
   */
  execute({ classes, marker }) {
    const model = this.editor.model;

    model.change((writer) => {
      const block = this._closestBlock();

      if (!block) {
        return;
      }

      const wanted = String(classes || '')
        .split(/\s+/)
        .filter(Boolean);

      if (wanted.length === 0) {
        writer.removeAttribute('utilikitClasses', block);
        return;
      }

      // The marker goes on with them, always. Without it the engine never
      // looks at the element and every class applied here is inert — the same
      // rule the AI writer and the reference documentation state.
      if (marker && !wanted.includes(marker)) {
        wanted.unshift(marker);
      }

      writer.setAttribute('utilikitClasses', wanted.join(' '), block);
    });
  }

  /**
   * The nearest block element to the selection.
   *
   * @return {module:engine/model/element~Element|null}
   *   The block, or null when there is not one.
   */
  _closestBlock() {
    const selection = this.editor.model.document.selection;
    const block = selection.getFirstPosition().parent;

    if (!block || !this.editor.model.schema.isBlock(block)) {
      return null;
    }

    return this.editor.model.schema.checkAttribute(block, 'utilikitClasses')
      ? block
      : null;
  }
}
