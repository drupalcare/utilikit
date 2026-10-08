/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore utilikit utilikitmodel

import { Plugin } from 'ckeditor5/src/core';
import {
  ButtonView,
  View,
  createDropdown,
  addListToDropdown,
  ViewModel,
} from 'ckeditor5/src/ui';
import { Collection, uid } from 'ckeditor5/src/utils';
import { IconCancel } from 'ckeditor5/src/icons';
// ONE SVG, TWO CONSUMERS. The text format admin UI draws the same file as a
// background image (css/utilikit-ckeditor.admin.css), so the icon in
// "Available buttons" and the icon in the editor cannot drift apart.
import utilikitIcon from '../../../../icons/handyman.svg';
import model from './utilikitmodel';

/**
 * The toolbar control: see what is on the block, add or change one style.
 *
 * WHY THIS IS NOT A PLAIN DROPDOWN OF CLASSES. Core's Style plugin offers a
 * fixed list someone typed into the text format settings. UtiliKit composes its
 * classes — `uk-pd--16px`, `uk-md-bg--3b82f6` — so the set is unbounded and a
 * list cannot express it. This offers the UTILITY from a list (that part is
 * finite and comes from the server, labelled and grouped by the rules
 * themselves) and asks for the VALUE with the control that suits it: a list of
 * the accepted words, a colour picker, or a field with an example and a help
 * line.
 *
 * WHAT THE PANEL LOOKS LIKE, top to bottom:
 *   1. "On this block" — the UtiliKit styles already on it, one chip each,
 *      in words ("Padding, top: 16px · 768px+"). Select a chip to edit it;
 *      its × removes it.
 *   2. "Add or change a style" — Style, (Side), Value, Screen size, in the
 *      order a person decides them; the class it will write is shown below.
 *   3. Apply, and Remove all.
 *
 * It is built from CKEditor's own classes and custom properties
 * (css/utilikit-ckeditor.css) so it reads as part of the editor.
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
    this._widths = config.breakpointWidths || {};
    this._pattern = model.classPattern(config.classPattern);
    this._links = Array.isArray(config.links) ? config.links : [];

    editor.ui.componentFactory.add('utilikit', (locale) => {
      const dropdown = createDropdown(locale);
      const command = editor.commands.get('utilikit');

      dropdown.buttonView.set({
        label: Drupal.t('UtiliKit classes'),
        icon: utilikitIcon,
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

      const panel = this._panel(locale, command);
      dropdown.panelView.children.add(panel);
      // Re-read the element every time the panel opens, so what it shows is
      // the block the caret is in now — not the state of the form the last
      // time it was used, or what the browser restored into it.
      const fit = () => UtilikitUI._fit(dropdown, panel);
      dropdown.on('change:isOpen', (evt, name, isOpen) => {
        if (isOpen) {
          panel.refresh();
          UtilikitUI._chooseSide(dropdown);
          fit();
          window.addEventListener('resize', fit);
          // Capture: the page, Claro's layout container or a modal can be the
          // thing that scrolls, and a scroll event does not bubble.
          document.addEventListener('scroll', fit, true);
        } else {
          window.removeEventListener('resize', fit);
          document.removeEventListener('scroll', fit, true);
        }
      });
      editor.on('destroy', () => {
        window.removeEventListener('resize', fit);
        document.removeEventListener('scroll', fit, true);
      });

      return dropdown;
    });
  }

  /**
   * Opens the panel on the side of the button with more room.
   *
   * CKEditor's 'auto' position picks the first side where the WHOLE panel
   * fits, and when neither does it falls back to below. Measured 2026-10-08
   * on sts.local: a 522px panel under a button 387px down a 797px window
   * ended 112px off-screen, Apply included. Here the panel goes below unless
   * there is clearly more room above; _fit() then caps its height to the room
   * on that side. The east/west half of CKEditor's choice is kept.
   *
   * @param {module:ui/dropdown/dropdownview~DropdownView} dropdown
   *   The dropdown, just opened.
   */
  static _chooseSide(dropdown) {
    const button = dropdown.buttonView.element.getBoundingClientRect();
    const below = window.innerHeight - button.bottom;
    const above = button.top;
    const horizontal = (dropdown.panelView.position || 'se').slice(1) || 'e';
    const vertical = below >= 320 || below >= above ? 's' : 'n';
    dropdown.panelView.position = vertical + horizontal;
  }

  /**
   * Caps the panel's height to the room left in the window.
   *
   * The body scrolls inside that height (css/utilikit-ckeditor.css); the
   * actions and the footer do not, so they are never what gets cut off. Runs
   * on open and on every resize or scroll while open.
   *
   * @param {module:ui/dropdown/dropdownview~DropdownView} dropdown
   *   The open dropdown.
   * @param {module:ui/view~View} panel
   *   The UtiliKit panel inside it.
   */
  static _fit(dropdown, panel) {
    const margin = 8;
    const box = dropdown.panelView.element.getBoundingClientRect();
    const room = (dropdown.panelView.position || 'se').startsWith('n')
      ? box.bottom - margin
      : window.innerHeight - box.top - margin;
    // A floor, so a window too short for anything still shows a usable
    // panel the page can be scrolled to, rather than a sliver.
    panel.element.style.maxBlockSize = `${Math.max(200, Math.floor(room))}px`;
  }

  /**
   * A one-item list explaining that the site sent no utilities.
   *
   * @return {module:utils/collection~Collection}
   *   The list.
   */
  static _emptyList() {
    const items = new Collection();
    const item = new ViewModel({
      withText: true,
      label: Drupal.t('No UtiliKit utilities were sent to the editor'),
      isEnabled: false,
    });

    items.add({ type: 'button', model: item });

    return items;
  }

  /**
   * The panel view.
   *
   * @param {module:utils/locale~Locale} locale
   *   The editor locale.
   * @param {module:core/command~Command} command
   *   The utilikit command.
   *
   * @return {module:ui/view~View}
   *   The panel view, with refresh(), focus() and focusLast().
   */
  _panel(locale, command) {
    const panel = new View(locale);
    const utilities = this._utilities;
    const marker = this._marker;
    const breakpoints = this._breakpoints;
    const widths = this._widths;
    const pattern = this._pattern;
    const byPrefix = {};
    utilities.forEach((u) => {
      byPrefix[u.prefix] = u;
    });

    // Unique ids, so every label is tied to its control even with several
    // editors on one page.
    const id = (name) => `ck-utilikit-${name}-${uid()}`;
    const ids = {
      heading: id('heading'),
      formHeading: id('form-heading'),
      utility: id('utility'),
      side: id('side'),
      value: id('value'),
      color: id('color'),
      keyword: id('keyword'),
      hint: id('hint'),
      screen: id('screen'),
    };

    const field = (label, forId, control, extra = []) => ({
      tag: 'div',
      attributes: { class: ['ck', 'ck-utilikit-panel__field'].concat(extra) },
      children: [
        {
          tag: 'label',
          attributes: {
            class: ['ck', 'ck-utilikit-panel__label'],
            for: forId,
          },
          children: [label],
        },
        control,
      ],
    });

    const select = (selectId, cls, children = []) => ({
      tag: 'span',
      attributes: { class: ['ck', 'ck-utilikit-panel__select-wrap'] },
      children: [
        {
          tag: 'select',
          attributes: {
            id: selectId,
            class: ['ck', 'ck-input', 'ck-utilikit-panel__select', cls],
          },
          children,
        },
      ],
    });

    panel.setTemplate({
      tag: 'form',
      attributes: {
        class: ['ck', 'ck-utilikit-panel'],
        tabindex: '-1',
        autocomplete: 'off',
        novalidate: 'novalidate',
        'aria-label': Drupal.t('UtiliKit classes'),
      },
      children: [
        // The scrolling part. The actions and the footer sit outside it, so
        // Apply stays on screen however little room the panel is given.
        {
          tag: 'div',
          attributes: { class: ['ck', 'ck-utilikit-panel__body'] },
          children: [
            // 1. What is on the block now.
            {
              tag: 'div',
              attributes: {
                class: ['ck', 'ck-utilikit-panel__section'],
              },
              children: [
                {
                  tag: 'div',
                  attributes: {
                    id: ids.heading,
                    class: ['ck', 'ck-utilikit-panel__heading'],
                  },
                  children: [
                    Drupal.t('On this block'),
                    {
                      tag: 'span',
                      attributes: { class: ['ck', 'ck-utilikit-panel__count'] },
                    },
                  ],
                },
                {
                  tag: 'ul',
                  attributes: {
                    class: ['ck', 'ck-utilikit-panel__chips'],
                    'aria-labelledby': ids.heading,
                  },
                },
                {
                  tag: 'p',
                  attributes: { class: ['ck', 'ck-utilikit-panel__empty'] },
                  children: [Drupal.t('No UtiliKit styles yet.')],
                },
              ],
            },
            // 2. Add or change one.
            {
              tag: 'div',
              attributes: {
                class: [
                  'ck',
                  'ck-utilikit-panel__section',
                  'ck-utilikit-panel__section_form',
                ],
                role: 'group',
                'aria-labelledby': ids.formHeading,
              },
              children: [
                {
                  tag: 'div',
                  attributes: {
                    id: ids.formHeading,
                    class: ['ck', 'ck-utilikit-panel__heading'],
                  },
                  children: [Drupal.t('Add or change a style')],
                },
                field(
                  Drupal.t('Style'),
                  ids.utility,
                  select(
                    ids.utility,
                    'ck-utilikit-panel__utility',
                    model.groupUtilities(utilities).map((g) => ({
                      tag: 'optgroup',
                      attributes: { label: g.group },
                      children: g.items.map((u) => ({
                        tag: 'option',
                        attributes: { value: u.prefix },
                        children: [model.optionText(u)],
                      })),
                    })),
                  ),
                ),
                field(
                  Drupal.t('Side'),
                  ids.side,
                  select(
                    ids.side,
                    'ck-utilikit-panel__side',
                    [{ code: '', label: Drupal.t('All sides') }]
                      .concat(model.SIDES)
                      .map((s) => ({
                        tag: 'option',
                        attributes: { value: s.code },
                        children: [s.label],
                      })),
                  ),
                  ['ck-utilikit-panel__field_side'],
                ),
                {
                  tag: 'div',
                  attributes: {
                    class: [
                      'ck',
                      'ck-utilikit-panel__field',
                      'ck-utilikit-panel__field_value',
                    ],
                  },
                  children: [
                    {
                      tag: 'label',
                      attributes: {
                        class: [
                          'ck',
                          'ck-utilikit-panel__label',
                          'ck-utilikit-panel__value-label',
                        ],
                        for: ids.value,
                      },
                      children: [Drupal.t('Value')],
                    },
                    // Keyword utilities: the accepted words, nothing else.
                    select(ids.keyword, 'ck-utilikit-panel__keyword'),
                    // Everything else: a text field; colours add a picker.
                    {
                      tag: 'span',
                      attributes: {
                        class: ['ck', 'ck-utilikit-panel__value-row'],
                      },
                      children: [
                        {
                          tag: 'input',
                          attributes: {
                            id: ids.color,
                            type: 'color',
                            class: ['ck', 'ck-utilikit-panel__color'],
                            'aria-label': Drupal.t('Pick a colour'),
                          },
                        },
                        {
                          tag: 'input',
                          attributes: {
                            id: ids.value,
                            type: 'text',
                            class: [
                              'ck',
                              'ck-input',
                              'ck-input-text',
                              'ck-utilikit-panel__value',
                            ],
                            spellcheck: 'false',
                            'aria-describedby': ids.hint,
                          },
                        },
                      ],
                    },
                    {
                      tag: 'p',
                      attributes: {
                        id: ids.hint,
                        class: ['ck', 'ck-utilikit-panel__hint'],
                      },
                    },
                  ],
                },
                field(
                  Drupal.t('Screen size'),
                  ids.screen,
                  select(
                    ids.screen,
                    'ck-utilikit-panel__screen',
                    [''].concat(breakpoints).map((bp) => ({
                      tag: 'option',
                      attributes: { value: bp },
                      children: [model.screenLabel(bp, widths)],
                    })),
                  ),
                ),
                {
                  tag: 'p',
                  attributes: { class: ['ck', 'ck-utilikit-panel__preview'] },
                },
                {
                  tag: 'p',
                  attributes: {
                    class: ['ck', 'ck-utilikit-panel__message'],
                    role: 'status',
                    'aria-live': 'polite',
                  },
                },
              ],
            },
          ],
        },
        {
          tag: 'div',
          attributes: { class: ['ck', 'ck-utilikit-panel__actions'] },
        },
        {
          tag: 'div',
          attributes: { class: ['ck', 'ck-utilikit-panel__footer'] },
        },
      ],
    });

    panel.render();

    const el = panel.element;
    // CKEditor's template renderer drops an attribute whose value is an empty
    // string, and an <option> with no value attribute submits its TEXT. So
    // "All screen sizes" became the breakpoint "All screen sizes" — measured
    // 2026-10-08. The empty value is restored here, where it is explicit.
    el.querySelectorAll('option:not([value])').forEach((option) => {
      option.setAttribute('value', '');
    });
    const $ = (cls) => el.querySelector(`.ck-utilikit-panel__${cls}`);
    const chipsEl = $('chips');
    const emptyEl = $('empty');
    const utilityEl = $('utility');
    const sideField = $('field_side');
    const sideEl = $('side');
    const keywordEl = $('keyword');
    const keywordWrap = keywordEl.parentNode;
    const valueRow = $('value-row');
    const valueLabel = $('value-label');
    const colorEl = $('color');
    const valueEl = $('value');
    const hintEl = $('hint');
    const screenEl = $('screen');
    const previewEl = $('preview');
    const messageEl = $('message');
    const actionsEl = $('actions');
    const countEl = $('count');
    const footerEl = $('footer');

    // The footer: the help pages this user can open, as the server decided —
    // a link is only sent when its module is on and the user has access. A
    // new tab, always: following a link in this one would lose the edit.
    this._links.forEach((link, index) => {
      if (index > 0) {
        footerEl.appendChild(document.createTextNode(' · '));
      }
      const a = document.createElement('a');
      a.href = link.url;
      a.target = '_blank';
      a.rel = 'noopener';
      a.className = 'ck ck-utilikit-panel__link';
      a.textContent = link.label;
      a.title = Drupal.t('@page (opens in a new tab)', { '@page': link.label });
      footerEl.appendChild(a);
    });
    footerEl.hidden = this._links.length === 0;

    const selected = () => byPrefix[utilityEl.value] || utilities[0];
    const say = (text) => {
      messageEl.textContent = text;
    };

    // The value the controls currently describe, normalized for the class.
    const currentValue = () => {
      const u = selected();
      const raw =
        model.valueKind(u) === 'keyword' ? keywordEl.value : valueEl.value;
      return model.normalizeValue(u, raw);
    };

    const composed = () => {
      const u = selected();
      const value = currentValue();
      if (!u || value === '') {
        return '';
      }
      return model.compose({
        prefix: u.prefix,
        value,
        breakpoint: screenEl.value,
        side: u.sides ? sideEl.value : '',
      });
    };

    const updatePreview = () => {
      const cls = composed();
      previewEl.textContent = cls
        ? Drupal.t('Class: @class', { '@class': cls })
        : '';
    };

    // Show the control the selected utility needs, and only that one.
    const showControls = () => {
      const u = selected();
      const kind = model.valueKind(u);

      sideField.hidden = !u.sides;
      keywordWrap.hidden = kind !== 'keyword';
      valueRow.hidden = kind === 'keyword';
      colorEl.hidden = kind !== 'color';
      valueLabel.htmlFor = kind === 'keyword' ? ids.keyword : ids.value;

      if (kind === 'keyword') {
        const keep = keywordEl.value;
        keywordEl.textContent = '';
        u.keywords.forEach((word) => {
          const option = document.createElement('option');
          option.value = word;
          option.textContent = word;
          keywordEl.appendChild(option);
        });
        if (u.keywords.includes(keep)) {
          keywordEl.value = keep;
        }
        keywordEl.setAttribute('aria-describedby', ids.hint);
      }
      valueEl.placeholder = kind === 'color' ? '3b82f6' : model.placeholder(u);
      hintEl.textContent = model.hint(u);
      updatePreview();
    };

    // The chips: one per UtiliKit class on the block.
    const renderChips = () => {
      const classes = model.currentClasses(command.value, marker);
      chipsEl.textContent = '';
      emptyEl.hidden = classes.length > 0;
      chipsEl.hidden = classes.length === 0;
      countEl.textContent = classes.length ? ` (${classes.length})` : '';

      classes.forEach((cls) => {
        const parsed = model.parseClass(cls, byPrefix, breakpoints);
        const text = model.chipText(parsed, cls, widths);
        const li = document.createElement('li');
        li.className = 'ck ck-utilikit-panel__chip';

        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'ck ck-utilikit-panel__chip-label';
        edit.textContent = text;
        edit.title = cls;
        edit.setAttribute(
          'aria-label',
          Drupal.t('Edit @style', { '@style': text }),
        );
        edit.disabled = !parsed;
        edit.addEventListener('click', () => {
          if (!parsed) {
            return;
          }
          utilityEl.value = parsed.prefix;
          showControls();
          sideEl.value = parsed.side;
          screenEl.value = parsed.breakpoint;
          if (model.valueKind(parsed.utility) === 'keyword') {
            keywordEl.value = parsed.value;
          } else {
            valueEl.value = parsed.value;
          }
          syncColorFromText();
          updatePreview();
          say(Drupal.t('Editing @style.', { '@style': text }));
          (model.valueKind(parsed.utility) === 'keyword'
            ? keywordEl
            : valueEl
          ).focus();
        });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'ck ck-utilikit-panel__chip-remove';
        remove.title = Drupal.t('Remove');
        remove.setAttribute(
          'aria-label',
          Drupal.t('Remove @style', { '@style': text }),
        );
        // CKEditor's own "cancel" icon, a constant SVG string from core.
        remove.innerHTML = IconCancel;
        remove.addEventListener('click', () => {
          const rest = model
            .currentClasses(command.value, marker)
            .filter((c) => c !== cls);
          this.editor.execute('utilikit', { classes: rest.join(' '), marker });
          say(Drupal.t('Removed @style.', { '@style': text }));
          // Keep focus inside the panel; the chip that had it is gone.
          (chipsEl.querySelector('button') || utilityEl).focus();
        });

        li.appendChild(edit);
        li.appendChild(remove);
        chipsEl.appendChild(li);
      });
    };

    // Keep the colour picker and the hex field saying the same thing.
    function syncColorFromText() {
      const hex = valueEl.value.trim().replace(/^#/, '');
      if (/^[0-9a-f]{6}$/i.test(hex)) {
        colorEl.value = `#${hex.toLowerCase()}`;
      } else if (/^[0-9a-f]{3}$/i.test(hex)) {
        colorEl.value = `#${hex.replace(/(.)/g, '$1$1').toLowerCase()}`;
      }
    }

    colorEl.addEventListener('input', () => {
      valueEl.value = colorEl.value.replace(/^#/, '');
      updatePreview();
    });
    valueEl.addEventListener('input', () => {
      if (selected().isColor) {
        syncColorFromText();
      }
      updatePreview();
    });
    utilityEl.addEventListener('change', () => {
      valueEl.value = '';
      sideEl.value = '';
      say('');
      showControls();
    });
    [sideEl, keywordEl, screenEl].forEach((s) =>
      s.addEventListener('change', updatePreview),
    );

    const apply = () => {
      const u = selected();
      const cls = composed();

      if (!u || cls === '') {
        say(Drupal.t('Give it a value first.'));
        (model.valueKind(u) === 'keyword' ? keywordEl : valueEl).focus();
        return;
      }
      // The engine's own well-formedness test, from the server. A space would
      // otherwise put a second, unreviewed class on the element.
      if (pattern && !pattern.test(cls)) {
        say(
          Drupal.t('"@value" cannot be used in a class. @hint', {
            '@value': currentValue(),
            '@hint': model.hint(u),
          }),
        );
        valueEl.focus();
        return;
      }

      const next = model.setClass(
        model.currentClasses(command.value, marker),
        cls,
        byPrefix,
        breakpoints,
      );
      this.editor.execute('utilikit', { classes: next.join(' '), marker });
      say(Drupal.t('Applied @class.', { '@class': cls }));
    };

    el.addEventListener('submit', (evt) => {
      // Enter in the value field applies; it must never submit the node form
      // this panel happens to sit inside.
      evt.preventDefault();
      apply();
    });

    const applyButton = new ButtonView(locale);
    applyButton.set({
      label: Drupal.t('Apply'),
      withText: true,
      class: 'ck-button-action ck-utilikit-panel__apply',
    });
    applyButton.on('execute', apply);

    const clearButton = new ButtonView(locale);
    clearButton.set({
      label: Drupal.t('Remove all'),
      withText: true,
      class: 'ck-utilikit-panel__clear',
    });
    clearButton.on('execute', () => {
      this.editor.execute('utilikit', { classes: '', marker });
      say(Drupal.t('Removed all UtiliKit styles from this block.'));
    });

    applyButton.render();
    clearButton.render();
    actionsEl.appendChild(applyButton.element);
    actionsEl.appendChild(clearButton.element);

    const refreshChipsAndButtons = () => {
      renderChips();
      clearButton.isEnabled =
        model.currentClasses(command.value, marker).length > 0;
    };
    command.on('change:value', refreshChipsAndButtons);

    // Everything focusable that is currently shown, in document order.
    const focusables = () =>
      Array.from(el.querySelectorAll('button, select, input')).filter(
        (node) =>
          !node.disabled &&
          !node.closest('[hidden]') &&
          !node.hidden &&
          node.getClientRects().length > 0,
      );

    // Tab and Shift+Tab cycle inside the panel, as CKEditor's own forms do,
    // instead of walking out of the editor and closing it.
    el.addEventListener('keydown', (evt) => {
      if (evt.key !== 'Tab') {
        return;
      }
      const list = focusables();
      if (!list.length) {
        return;
      }
      const at = list.indexOf(document.activeElement);
      const next = evt.shiftKey
        ? list[(at <= 0 ? list.length : at) - 1]
        : list[(at + 1) % list.length];
      evt.preventDefault();
      evt.stopPropagation();
      next.focus();
    });

    // The dropdown calls these when it opens from the keyboard. Without them
    // CKEditor logs `ui-dropdown-panel-focus-child-missing-focus` on every
    // open — measured on the panel this replaces.
    panel.focus = () => {
      utilityEl.focus();
    };
    panel.focusLast = () => {
      const list = focusables();
      (list[list.length - 1] || utilityEl).focus();
    };
    panel.refresh = () => {
      say('');
      refreshChipsAndButtons();
      showControls();
    };

    panel.refresh();

    return panel;
  }
}
