/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore utilikit utilikitcommand

import { Plugin } from 'ckeditor5/src/core';
import UtilikitCommand from './utilikitcommand';

/**
 * Teaches the model that a block can carry UtiliKit classes.
 *
 * WHAT THIS HAS TO GET RIGHT. A class attribute that CKEditor does not know
 * about is discarded the moment the content round-trips through the editor —
 * the author sees their styling vanish on the next save with nothing to explain
 * it. So the attribute is declared on every block the schema allows, and
 * converted in both directions.
 *
 * The upcast is deliberately narrow: it takes only the `utilikit` marker and
 * `uk-` classes and leaves every other class on the element alone, so this
 * plugin cannot swallow classes some other module or the theme put there.
 *
 * @private
 */
export default class UtilikitEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'UtilikitEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    const editor = this.editor;
    const marker = editor.config.get('utilikit.marker') || 'utilikit';

    editor.model.schema.extend('$block', {
      allowAttributes: 'utilikitClasses',
    });

    // Model -> view. One attribute holding the whole class string.
    editor.conversion.for('downcast').attributeToAttribute({
      model: 'utilikitClasses',
      view: (value) => ({ key: 'class', value: String(value || '') }),
    });

    // View -> model. Only UtiliKit's own classes are claimed; anything else on
    // the element stays where it is.
    editor.conversion.for('upcast').elementToAttribute({
      view: {
        // `classes`, NOT `attributes: { class: ... }`. CKEditor 5 deprecated
        // the class key inside attributes and warns about it in the console —
        // measured on 47.6.2. The classes matcher also tests one class NAME at
        // a time, which is what is wanted here: match the marker exactly, or
        // anything in UtiliKit's `uk-` namespace.
        classes: /^(utilikit|uk-.+)$/,
      },
      model: {
        key: 'utilikitClasses',
        value: (viewElement) => {
          const classes = Array.from(viewElement.getClassNames()).filter(
            (name) => name === marker || name.startsWith('uk-'),
          );

          return classes.length ? classes.join(' ') : null;
        },
      },
      converterPriority: 'low',
    });

    editor.commands.add('utilikit', new UtilikitCommand(editor));
  }
}
