/**
 * @file
 * Tests for the CKEditor panel's data model (utilikit_ckeditor).
 *
 * The model turns the server's rule list into what the panel shows and turns a
 * choice back into a class. Each test here pins a defect measured on the panel
 * this one replaced (sts.local, 2026-10-08) or a promise the new one makes:
 *
 *   - changing a value appended a second class instead of replacing it;
 *   - a value with a space in it composed two classes;
 *   - 50 options in one flat, unlabelled list.
 */

// The model translates its strings with Drupal.t(), as the editor does.
global.Drupal = global.Drupal || {};
global.Drupal.t =
  global.Drupal.t ||
  ((s, args) =>
    Object.keys(args || {}).reduce((out, k) => out.split(k).join(args[k]), s));

const model = require('../../../modules/utilikit_ckeditor/js/ckeditor5_plugins/utilikit/src/utilikitmodel');

// A fixture in the SHAPE the server sends, not a copy of the rules: the
// rules themselves are pinned in PHP by UtilikitRulesSingleSourceTest and
// UtilikitCkeditorPluginTest.
const utilities = [
  {
    prefix: 'pd',
    label: 'Padding',
    group: 'Box Model',
    sides: true,
    isNumericFlexible: true,
  },
  { prefix: 'bc', label: 'Border color', group: 'Colors', isColor: true },
  {
    prefix: 'xw',
    label: 'Max width',
    group: 'Sizing',
    isNumericFlexible: true,
  },
  {
    prefix: 'mg',
    label: 'Margin',
    group: 'Box Model',
    sides: true,
    allowAuto: true,
  },
  {
    prefix: 'ta',
    label: 'Text align',
    group: 'Typography',
    isKeyword: true,
    keywords: ['left', 'center'],
  },
  {
    prefix: 'or',
    label: 'Order',
    group: 'Flexbox',
    isInteger: true,
    isKeyword: false,
    keywords: ['0', '1'],
  },
  { prefix: 'fg', label: 'Flex grow', group: 'Flexbox', isDecimalFixed: true },
];
const byPrefix = Object.fromEntries(utilities.map((u) => [u.prefix, u]));
const breakpoints = ['sm', 'md', 'lg'];
const widths = { sm: 576, md: 768, lg: 992 };

describe('grouping and labels', () => {
  test('groups keep the order the rules declare them in', () => {
    const groups = model.groupUtilities(utilities);
    expect(groups.map((g) => g.group)).toEqual([
      'Box Model',
      'Colors',
      'Sizing',
      'Typography',
      'Flexbox',
    ]);
    expect(groups[0].items.map((u) => u.prefix)).toEqual(['pd', 'mg']);
  });

  test('every utility lands in exactly one group', () => {
    const total = model
      .groupUtilities(utilities)
      .reduce((n, g) => n + g.items.length, 0);
    expect(total).toBe(utilities.length);
  });

  test('option text is plain words first, code second', () => {
    expect(model.optionText(byPrefix.xw)).toBe('Max width (xw)');
  });
});

describe('which control a value needs', () => {
  test('keyword rules get a list, colours a picker, the rest a field', () => {
    expect(model.valueKind(byPrefix.ta)).toBe('keyword');
    expect(model.valueKind(byPrefix.bc)).toBe('color');
    expect(model.valueKind(byPrefix.pd)).toBe('text');
  });

  test('a keyword list alone does not make a keyword rule', () => {
    // `order` has a keyword list in getAllowedKeywords() and is an integer
    // rule; the old panel offered "One of: 0, 1, …" for it.
    expect(model.valueKind(byPrefix.or)).toBe('text');
  });

  test('every utility gets a help line', () => {
    utilities.forEach((u) => {
      expect(model.hint(u)).not.toBe('');
    });
  });
});

describe('composing and reading classes', () => {
  test('compose puts screen size and side where the engine reads them', () => {
    expect(
      model.compose({
        prefix: 'pd',
        value: '16px',
        breakpoint: 'md',
        side: 't',
      }),
    ).toBe('uk-md-pd--t-16px');
    expect(model.compose({ prefix: 'pd', value: '16px' })).toBe('uk-pd--16px');
  });

  test('parseClass reads what compose writes', () => {
    const parsed = model.parseClass('uk-md-pd--t-16px', byPrefix, breakpoints);
    expect(parsed).toMatchObject({
      prefix: 'pd',
      breakpoint: 'md',
      side: 't',
      value: '16px',
    });
  });

  test('parseClass refuses an unknown utility or breakpoint', () => {
    expect(model.parseClass('uk-zz--1', byPrefix, breakpoints)).toBeNull();
    expect(model.parseClass('uk-qq-pd--1px', byPrefix, breakpoints)).toBeNull();
    expect(model.parseClass('utilikit', byPrefix, breakpoints)).toBeNull();
  });

  test('typed decimals and percentages become UtiliKit notation', () => {
    expect(model.normalizeValue(byPrefix.fg, '1.5')).toBe('1d5');
    expect(model.normalizeValue(byPrefix.xw, '50%')).toBe('50pr');
    expect(model.normalizeValue(byPrefix.bc, '#3B82F6')).toBe('3b82f6');
    expect(model.normalizeValue(byPrefix.ta, 'center')).toBe('center');
  });
});

describe('changing a value replaces it', () => {
  test('a new padding replaces the old one — measured: both used to stay', () => {
    const next = model.setClass(
      ['uk-pd--16px', 'uk-bg--fff'],
      'uk-pd--24px',
      byPrefix,
      breakpoints,
    );
    expect(next).toEqual(['uk-bg--fff', 'uk-pd--24px']);
  });

  test('a side, or a screen size, is its own slot', () => {
    let list = ['uk-pd--16px'];
    list = model.setClass(list, 'uk-pd--t-8px', byPrefix, breakpoints);
    list = model.setClass(list, 'uk-md-pd--32px', byPrefix, breakpoints);
    expect(list).toEqual(['uk-pd--16px', 'uk-pd--t-8px', 'uk-md-pd--32px']);
  });

  test('re-applying the same class does not duplicate it', () => {
    expect(
      model.setClass(['uk-pd--16px'], 'uk-pd--16px', byPrefix, breakpoints),
    ).toEqual(['uk-pd--16px']);
  });

  test('the marker is never shown as a style of its own', () => {
    expect(model.currentClasses('utilikit uk-pd--16px', 'utilikit')).toEqual([
      'uk-pd--16px',
    ]);
  });
});

describe('words for people', () => {
  test('screen sizes are described by width', () => {
    expect(model.screenLabel('', widths)).toBe('All screen sizes');
    expect(model.screenLabel('md', widths)).toBe('768px and wider (md)');
  });

  test('a chip says what the class does', () => {
    const parsed = model.parseClass(
      'uk-md-pd--t-1d5rem',
      byPrefix,
      breakpoints,
    );
    expect(model.chipText(parsed, 'uk-md-pd--t-1d5rem', widths)).toBe(
      'Padding, top: 1.5rem · 768px+',
    );
    expect(model.chipText(null, 'uk-zz--1', widths)).toBe('uk-zz--1');
  });
});

describe('colour opacity and state suffixes', () => {
  // The grammar is UtilikitCssGenerator::parseColorWithAlpha():
  // hex, then an optional -NN opacity, then an optional -h, -f or -a state.
  // Measured 2026-10-08 on sts.local: the chip for uk-tc--3b82f6-h read
  // "Text color: 3b82f6-h".
  const tc = {
    prefix: 'tc',
    label: 'Text color',
    group: 'Colors',
    isColor: true,
  };
  const withTc = { ...byPrefix, tc };
  const parse = (cls) => model.parseClass(cls, withTc, breakpoints);

  test.each([
    ['uk-tc--3b82f6-h', '3b82f6', '', 'h', 'Text color: #3b82f6 · hover'],
    ['uk-tc--3b82f6-f', '3b82f6', '', 'f', 'Text color: #3b82f6 · focus'],
    ['uk-tc--3b82f6-a', '3b82f6', '', 'a', 'Text color: #3b82f6 · active'],
    [
      'uk-tc--3b82f6-50',
      '3b82f6',
      '50',
      '',
      'Text color: #3b82f6 · 50% opacity',
    ],
    [
      'uk-md-tc--f00-50-h',
      'f00',
      '50',
      'h',
      'Text color: #f00 · 50% opacity · hover · 768px+',
    ],
    ['uk-tc--3b82f6', '3b82f6', '', '', 'Text color: #3b82f6'],
  ])('%s', (cls, color, opacity, state, text) => {
    const parsed = parse(cls);
    expect(parsed).toMatchObject({ color, opacity, state });
    expect(model.chipText(parsed, cls, widths)).toBe(text);
  });

  test('the state is not part of the value the field is given back', () => {
    // Editing from a chip puts `value` in the field and keeps the state
    // aside; compose() then writes the same class again.
    const parsed = parse('uk-md-tc--3b82f6-50-h');
    expect(parsed.value).toBe('3b82f6-50');
    expect(
      model.compose({
        prefix: parsed.prefix,
        value: parsed.value,
        breakpoint: parsed.breakpoint,
        side: parsed.side,
        state: parsed.state,
      }),
    ).toBe('uk-md-tc--3b82f6-50-h');
  });

  test('a hover colour and a plain colour are different slots', () => {
    expect(
      model.setClass(['uk-tc--333'], 'uk-tc--3b82f6-h', withTc, breakpoints),
    ).toEqual(['uk-tc--333', 'uk-tc--3b82f6-h']);
    expect(
      model.setClass(
        ['uk-tc--333', 'uk-tc--3b82f6-h'],
        'uk-tc--000-h',
        withTc,
        breakpoints,
      ),
    ).toEqual(['uk-tc--333', 'uk-tc--000-h']);
  });

  test('a trailing -h on a non-colour rule is NOT a state', () => {
    // Only colour rules take a state; on padding "-h" is part of the value.
    const parsed = parse('uk-pd--16px-h');
    expect(parsed).toMatchObject({ value: '16px-h', state: '' });
    expect(model.chipText(parsed, 'uk-pd--16px-h', widths)).toBe(
      'Padding: 16px-h',
    );
  });

  test('a colour value outside the grammar is shown as typed', () => {
    const parsed = parse('uk-tc--blue-h');
    expect(parsed).toMatchObject({ value: 'blue-h', state: '' });
  });
});

describe('the server pattern guards the class', () => {
  // The literal from UtilikitConstants::CLASS_VALIDATION_PATTERN, as the
  // editor plugin sends it.
  const pattern = model.classPattern(
    '/^uk-(?:(?:sm|md|lg|xl|xxl)-)?[a-z]{2,4}--[a-zA-Z0-9\\-_.%]+$/',
  );

  test('a well-formed class passes', () => {
    expect(pattern.test('uk-md-pd--t-16px')).toBe(true);
  });

  test('a value with a space cannot smuggle in a second class', () => {
    expect(pattern.test('uk-pd--16px uk-evil')).toBe(false);
  });

  test('no pattern sent means no pattern, not a crash', () => {
    expect(model.classPattern('')).toBeNull();
  });
});
