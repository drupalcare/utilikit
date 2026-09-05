/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore utilikit dropdownview

import { Plugin } from 'ckeditor5/src/core';
import {
  ButtonView,
  View,
  createDropdown,
  addListToDropdown,
  ViewModel,
} from 'ckeditor5/src/ui';
import { Collection } from 'ckeditor5/src/utils';

/**
 * The toolbar control: pick a utility, give it a value, apply it.
 *
 * WHY THIS IS NOT A PLAIN DROPDOWN OF CLASSES. Core's Style plugin offers a
 * fixed list someone typed into the text format settings. UtiliKit composes its
 * classes — `uk-pd--16px`, `uk-md-bg--3b82f6` — so the set is unbounded and a
 * list cannot express it. This offers the PREFIX from a list (that part is
 * finite and comes from the server) and takes the VALUE as input, then
 * validates the pair against the same rules the CSS generator uses.
 *
 * @private
 */
export default class UtilikitUI extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'UtilikitUI';
  }

  /**
   * @inheritdoc
   */
  init() {
    const editor = this.editor;
    const config = editor.config.get('utilikit') || {};

    this._utilities = Array.isArray(config.utilities) ? config.utilities : [];
    this._marker = config.marker || 'utilikit';
    this._breakpoints = Array.isArray(config.breakpoints)
      ? config.breakpoints
      : [];

    editor.ui.componentFactory.add('utilikit', (locale) => {
      const dropdown = createDropdown(locale);
      const command = editor.commands.get('utilikit');

      dropdown.buttonView.set({
        label: 'UtiliKit classes',
        tooltip: true,
        withText: true,
      });

      dropdown.bind('isEnabled').to(command);

      // THE HONEST EMPTY STATE. If the server sent no utilities the control
      // says so rather than presenting an empty menu that looks broken.
      if (this._utilities.length === 0) {
        addListToDropdown(dropdown, UtilikitUI._emptyList());

        return dropdown;
      }

      dropdown.panelView.children.add(this._panel(locale, command));

      return dropdown;
    });
  }

  /**
   * A one-item list explaining that the site sent no utilities.
   *
   * @return {module:utils/collection~Collection}
   *   The list.
   */
  static _emptyList() {
    const items = new Collection();
    const model = new ViewModel({
      withText: true,
      label: 'No UtiliKit utilities were sent to the editor',
      isEnabled: false,
    });

    items.add({ type: 'button', model });

    return items;
  }

  /**
   * The panel: a prefix select, a value field, and Apply / Clear.
   *
   * @param {module:utils/locale~Locale} locale
   *   The editor locale.
   * @param {module:core/command~Command} command
   *   The utilikit command.
   *
   * @return {module:ui/view~View}
   *   The panel view.
   */
  _panel(locale, command) {
    const panel = new View(locale);
    const utilities = this._utilities;
    const marker = this._marker;
    const breakpoints = this._breakpoints;

    panel.setTemplate({
      tag: 'form',
      attributes: {
        class: ['ck', 'ck-utilikit-panel'],
        tabindex: '-1',
      },
      children: [
        {
          tag: 'label',
          attributes: { class: ['ck', 'ck-utilikit-panel__label'] },
          children: ['Breakpoint'],
        },
        {
          tag: 'select',
          attributes: { class: ['ck', 'ck-utilikit-panel__breakpoint'] },
          children: [
            {
              tag: 'option',
              attributes: { value: '' },
              children: ['All sizes'],
            },
          ].concat(
            breakpoints.map((bp) => ({
              tag: 'option',
              attributes: { value: bp },
              children: [`${bp} and up`],
            })),
          ),
        },
        {
          tag: 'label',
          attributes: { class: ['ck', 'ck-utilikit-panel__label'] },
          children: ['Utility'],
        },
        {
          tag: 'select',
          attributes: { class: ['ck', 'ck-utilikit-panel__prefix'] },
          children: utilities.map((u) => ({
            tag: 'option',
            attributes: { value: u.prefix },
            children: [`${u.prefix} — ${u.css}`],
          })),
        },
        {
          tag: 'label',
          attributes: { class: ['ck', 'ck-utilikit-panel__label'] },
          children: ['Value'],
        },
        {
          tag: 'input',
          attributes: {
            class: ['ck', 'ck-utilikit-panel__value'],
            type: 'text',
            placeholder: '16px',
          },
        },
        {
          tag: 'p',
          attributes: { class: ['ck', 'ck-utilikit-panel__hint'] },
          children: [''],
        },
        {
          tag: 'div',
          attributes: { class: ['ck', 'ck-utilikit-panel__current'] },
          children: [''],
        },
      ],
    });

    panel.render();

    const el = panel.element;
    const prefixEl = el.querySelector('.ck-utilikit-panel__prefix');
    const valueEl = el.querySelector('.ck-utilikit-panel__value');
    const bpEl = el.querySelector('.ck-utilikit-panel__breakpoint');
    const hintEl = el.querySelector('.ck-utilikit-panel__hint');
    const currentEl = el.querySelector('.ck-utilikit-panel__current');

    const selected = () =>
      utilities.find((u) => u.prefix === prefixEl.value) || utilities[0];

    // The hint is read from the rule, so it always describes what this site
    // accepts rather than a sentence written once and left to drift.
    const describe = () => {
      const u = selected();

      if (!u) {
        return '';
      }

      if (u.keywords && u.keywords.length) {
        return `One of: ${u.keywords.join(', ')}`;
      }

      if (u.isColor) {
        return 'A hex colour without the #, such as 3b82f6';
      }

      if (u.isInteger) {
        return 'A whole number';
      }

      return u.sides
        ? 'A length such as 16px, or a side first: t-16px'
        : 'A length such as 16px, 2rem or 50pr for a percentage';
    };

    const refreshHint = () => {
      hintEl.textContent = describe();
      currentEl.textContent = command.value
        ? `On this element: ${command.value}`
        : 'No UtiliKit classes on this element yet.';
    };

    prefixEl.addEventListener('change', refreshHint);
    command.on('change:value', refreshHint);
    refreshHint();

    const apply = new ButtonView(locale);
    apply.set({
      label: 'Apply',
      withText: true,
      class: 'ck-utilikit-panel__apply',
    });
    apply.on('execute', () => {
      const u = selected();
      const value = String(valueEl.value || '').trim();

      if (!u || value === '') {
        hintEl.textContent = 'Give the utility a value first.';
        return;
      }

      const bp = bpEl.value ? `${bpEl.value}-` : '';
      const composed = `uk-${bp}${u.prefix}--${value}`;
      const existing = String(command.value || '')
        .split(/\s+/)
        .filter(Boolean)
        .filter((c) => c !== composed);

      this.editor.execute('utilikit', {
        classes: existing.concat(composed).join(' '),
        marker,
      });
      this.editor.editing.view.focus();
    });

    const clear = new ButtonView(locale);
    clear.set({
      label: 'Clear',
      withText: true,
      class: 'ck-utilikit-panel__clear',
    });
    clear.on('execute', () => {
      this.editor.execute('utilikit', { classes: '', marker });
      this.editor.editing.view.focus();
    });

    apply.render();
    clear.render();
    el.appendChild(apply.element);
    el.appendChild(clear.element);

    return panel;
  }
}
