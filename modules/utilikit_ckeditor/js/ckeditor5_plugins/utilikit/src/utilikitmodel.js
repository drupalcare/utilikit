/**
 * @file
 * The UtiliKit panel's data model: no CKEditor, no DOM, so it can be tested.
 *
 * WHAT THIS FILE OWNS, AND WHAT IT DOES NOT. The list of utilities, their
 * plain-words labels, their groups, their flags and their keyword lists all
 * come from the server — UtilikitRules::getRules() through the editor plugin's
 * dynamic configuration. Nothing here names a utility. What this file owns is
 * how that data is PRESENTED and how a choice becomes a class: grouping in
 * the rules' own order, the text of each option, the help line for a value,
 * and the rule that a new value for the same utility on the same screen size
 * REPLACES the old one instead of piling up beside it.
 *
 * CommonJS on purpose: webpack bundles it for the editor, and Jest can
 * require() it without a Babel module transform.
 */

// cspell:ignore utilikit

/**
 * Side codes the sides grammar accepts.
 *
 * Defined by the regular expression in UtilikitCssGenerator::applySidesRule()
 * — `(t|r|b|l)-value` — not by a rule flag, so there is no server list to read.
 */
const SIDES = [
  { code: 't', label: Drupal.t('Top') },
  { code: 'r', label: Drupal.t('Right') },
  { code: 'b', label: Drupal.t('Bottom') },
  { code: 'l', label: Drupal.t('Left') },
];

/**
 * The states a colour class can carry, by suffix.
 *
 * Defined by UtilikitConstants::PSEUDO_STATE_MAP (h, f, a) and read by
 * UtilikitCssGenerator::parseColorWithAlpha(). Only COLOUR rules take one: on
 * any other rule a trailing "-h" is part of the value, not a state.
 */
const STATES = {
  h: Drupal.t('hover'),
  f: Drupal.t('focus'),
  a: Drupal.t('active'),
};

/**
 * A colour value split into colour, opacity and state.
 *
 * The same grammar as UtilikitCssGenerator::parseColorWithAlpha(): a 6- or
 * 3-digit hex, an optional `-NN` opacity, an optional `-h|-f|-a` state.
 *
 * @param {string} value
 *   The value part of a colour class, side already removed.
 *
 * @return {{color: string, opacity: string, state: string}|null}
 *   The parts, or null when the value is not in the colour grammar.
 */
function parseColorValue(value) {
  const match = /^([0-9a-f]{6}|[0-9a-f]{3})(?:-(\d{1,3}))?(?:-([hfa]))?$/i.exec(
    String(value || ''),
  );
  if (!match) {
    return null;
  }
  return { color: match[1], opacity: match[2] || '', state: match[3] || '' };
}

/**
 * Groups utilities in the order the rules declare them.
 *
 * @param {Array<Object>} utilities
 *   The utilities the server sent.
 *
 * @return {Array<{group: string, items: Array<Object>}>}
 *   One entry per group, groups in first-appearance order, items in rule order.
 */
function groupUtilities(utilities) {
  const groups = [];
  const index = new Map();

  (utilities || []).forEach((u) => {
    const name = u.group || 'Other';
    if (!index.has(name)) {
      index.set(name, groups.length);
      groups.push({ group: name, items: [] });
    }
    groups[index.get(name)].items.push(u);
  });

  return groups;
}

/**
 * The option text: plain words first, the code second.
 *
 * @param {Object} u
 *   A utility.
 *
 * @return {string}
 *   For example "Max width (xw)".
 */
function optionText(u) {
  return `${u.label || u.prefix} (${u.prefix})`;
}

/**
 * Which control the value needs.
 *
 * @param {Object} u
 *   A utility.
 *
 * @return {string}
 *   'keyword' (pick from a list), 'color' (pick a colour) or 'text'.
 */
function valueKind(u) {
  if (u.isKeyword && Array.isArray(u.keywords) && u.keywords.length) {
    return 'keyword';
  }
  return u.isColor ? 'color' : 'text';
}

/**
 * An example value for the text field's placeholder.
 *
 * @param {Object} u
 *   A utility.
 *
 * @return {string}
 *   A value the engine accepts for this utility.
 */
function placeholder(u) {
  if (u.isOpacity) {
    return '50';
  }
  if (u.isTransform === 'rotate') {
    return '45';
  }
  if (u.isTransform === 'scale') {
    return '150';
  }
  if (u.isRange) {
    return u.css === 'aspectRatio' ? '16-9' : '1-3';
  }
  if (u.isGridTrackList) {
    return 'repeat-3-1fr';
  }
  if (u.isDecimalFixed) {
    return '1';
  }
  if (u.isInteger) {
    return u.css === 'fontWeight' ? '700' : '1';
  }
  if (u.prefix === 'gp') {
    return '16';
  }
  return '16px';
}

/**
 * One line telling an editor what to type.
 *
 * The wording follows the value grammar in UtilikitCssGenerator; it describes
 * it, it does not enforce it.
 *
 * @param {Object} u
 *   A utility.
 *
 * @return {string}
 *   The help line.
 */
function hint(u) {
  if (!u) {
    return '';
  }
  const kind = valueKind(u);
  if (kind === 'keyword') {
    return Drupal.t('Pick one of the values this utility accepts.');
  }
  if (kind === 'color') {
    return Drupal.t(
      'Pick a colour, or type a hex code such as 3b82f6. Add -50 for 50% opacity.',
    );
  }
  if (u.isOpacity) {
    return Drupal.t('A whole number from 0 (invisible) to 100 (solid).');
  }
  if (u.isTransform === 'rotate') {
    return Drupal.t('Degrees, as a whole number: 45 turns it 45°.');
  }
  if (u.isTransform === 'scale') {
    return Drupal.t(
      'A percentage as a whole number: 150 makes it one and a half times bigger.',
    );
  }
  if (u.isRange) {
    return u.css === 'aspectRatio'
      ? Drupal.t('Width and height joined by a dash, such as 16-9.')
      : Drupal.t('Start and end grid lines joined by a dash, such as 1-3.');
  }
  if (u.isGridTrackList) {
    return Drupal.t(
      'A column pattern such as repeat-3-1fr (three equal columns) or 200px-1fr.',
    );
  }
  if (u.isDecimalFixed) {
    return Drupal.t('A number such as 1 or 1.5.');
  }
  if (u.isInteger) {
    return u.css === 'fontWeight'
      ? Drupal.t(
          'A weight from 100 to 900, such as 400 (normal) or 700 (bold).',
        )
      : Drupal.t('A whole number, such as 1 or 10.');
  }
  if (u.prefix === 'gp') {
    return Drupal.t(
      'Pixels as a number, such as 16; or row-column such as 16-32.',
    );
  }
  return u.allowAuto
    ? Drupal.t(
        'A size with a unit: px, rem, em, vh, vw or % (for example 16px, 1.5rem, 50%), or auto.',
      )
    : Drupal.t(
        'A size with a unit: px, rem, em, vh, vw or % (for example 16px, 1.5rem, 50%).',
      );
}

/**
 * Turns what an editor typed into the notation the engine reads.
 *
 * Class names cannot contain "." safely in every consumer, so UtiliKit writes
 * 1.5 as 1d5 and a percentage as pr. People type 1.5 and 50%; this converts
 * those two forms and nothing else.
 *
 * @param {Object} u
 *   A utility.
 * @param {string} raw
 *   What was typed or picked.
 *
 * @return {string}
 *   The value as it goes into the class.
 */
function normalizeValue(u, raw) {
  let value = String(raw || '').trim();
  if (u && u.isColor) {
    return value.replace(/^#/, '').toLowerCase();
  }
  if (u && valueKind(u) === 'keyword') {
    return value;
  }
  value = value.replace(/(\d)\.(\d)/g, '$1d$2');
  value = value.replace(/%$/, 'pr');
  return value;
}

/**
 * Builds the class.
 *
 * @param {Object} parts
 *   The choice.
 * @param {string} parts.prefix
 *   The utility prefix.
 * @param {string} parts.value
 *   The normalized value.
 * @param {string} [parts.breakpoint]
 *   A breakpoint code, or empty for all screen sizes.
 * @param {string} [parts.side]
 *   A side code, or empty for all sides.
 * @param {string} [parts.state]
 *   A colour state code (h, f, a), or empty. Only colour rules take one.
 *
 * @return {string}
 *   For example uk-md-pd--t-16px.
 */
function compose({ prefix, value, breakpoint = '', side = '', state = '' }) {
  const bp = breakpoint ? `${breakpoint}-` : '';
  const sd = side ? `${side}-` : '';
  const st = state ? `-${state}` : '';
  return `uk-${bp}${prefix}--${sd}${value}${st}`;
}

/**
 * Reads a class back into its parts.
 *
 * @param {string} cls
 *   A class name.
 * @param {Object<string, Object>} byPrefix
 *   Utilities keyed by prefix.
 * @param {Array<string>} breakpoints
 *   The breakpoint codes.
 *
 * @return {Object|null}
 *   {cls, prefix, breakpoint, side, value, state, color, opacity, utility}, or
 *   null when the class is not a UtiliKit utility this site knows. For a
 *   colour class `value` is the colour with its opacity ("3b82f6-50") and
 *   `state` is kept apart, so editing from a chip and applying again writes
 *   the same class; `color` and `opacity` are empty for anything else.
 */
function parseClass(cls, byPrefix, breakpoints) {
  const match = /^uk-(?:([a-z]+)-)?([a-z]{2,4})--(.+)$/.exec(cls);
  if (!match) {
    return null;
  }
  const prefix = match[2];
  let breakpoint = match[1];
  let value = match[3];
  // `uk-md-pd--x`: the first segment is a breakpoint only if it is one.
  if (breakpoint && !(breakpoints || []).includes(breakpoint)) {
    return null;
  }
  breakpoint = breakpoint || '';
  const utility = byPrefix[prefix];
  if (!utility) {
    return null;
  }
  let side = '';
  const sideMatch = /^([trbl])-(.+)$/.exec(value);
  if (utility.sides && sideMatch) {
    [, side, value] = sideMatch;
  }
  let state = '';
  let color = '';
  let opacity = '';
  const parts = utility.isColor ? parseColorValue(value) : null;
  if (parts) {
    ({ state, color, opacity } = parts);
    value = opacity ? `${color}-${opacity}` : color;
  }
  return {
    cls,
    prefix,
    breakpoint,
    side,
    value,
    state,
    color,
    opacity,
    utility,
  };
}

/**
 * The slot a class occupies: one utility, side, screen size and state.
 *
 * A hover colour and a plain colour are both meant to be there at once, so
 * the state is part of the slot.
 *
 * @param {Object} parsed
 *   A parsed class.
 *
 * @return {string}
 *   The slot key.
 */
function slotOf(parsed) {
  return `${parsed.breakpoint}|${parsed.prefix}|${parsed.side}|${parsed.state || ''}`;
}

/**
 * Splits the element's class string into UtiliKit classes, marker excluded.
 *
 * @param {string} classString
 *   The command value.
 * @param {string} marker
 *   The marker class.
 *
 * @return {Array<string>}
 *   The classes, in order.
 */
function currentClasses(classString, marker) {
  return String(classString || '')
    .split(/\s+/)
    .filter(Boolean)
    .filter((c) => c !== marker);
}

/**
 * Sets a class, replacing any class in the same slot.
 *
 * WHY. Changing padding from 16px to 24px used to leave BOTH classes on the
 * element — measured 2026-10-08 on sts.local: `uk-pd--16px uk-pd--24px` — so
 * which one won depended on the generated stylesheet's order.
 *
 * @param {Array<string>} classes
 *   The current classes, marker excluded.
 * @param {string} cls
 *   The class to set.
 * @param {Object<string, Object>} byPrefix
 *   Utilities keyed by prefix.
 * @param {Array<string>} breakpoints
 *   The breakpoint codes.
 *
 * @return {Array<string>}
 *   The new list.
 */
function setClass(classes, cls, byPrefix, breakpoints) {
  const incoming = parseClass(cls, byPrefix, breakpoints);
  const kept = classes.filter((c) => {
    if (c === cls) {
      return false;
    }
    const parsed = parseClass(c, byPrefix, breakpoints);
    return !(incoming && parsed && slotOf(parsed) === slotOf(incoming));
  });
  return kept.concat(cls);
}

/**
 * A value as a person reads it: 1d5rem as 1.5rem, 50pr as 50%.
 *
 * @param {string} value
 *   The class value.
 *
 * @return {string}
 *   The readable value.
 */
function readableValue(value) {
  return String(value)
    .replace(/(\d)d(\d)/g, '$1.$2')
    .replace(/(\d)pr$/, '$1%');
}

/**
 * The screen-size choice in words.
 *
 * @param {string} breakpoint
 *   A breakpoint code, or empty.
 * @param {Object<string, number>} widths
 *   Minimum width per breakpoint code.
 *
 * @return {string}
 *   For example "768px and wider (md)".
 */
function screenLabel(breakpoint, widths) {
  if (!breakpoint) {
    return Drupal.t('All screen sizes');
  }
  const px = widths && widths[breakpoint];
  return px
    ? Drupal.t('@width and wider (@code)', {
        '@width': `${px}px`,
        '@code': breakpoint,
      })
    : Drupal.t('@code and wider', { '@code': breakpoint });
}

/**
 * The text of a chip for a class on the element.
 *
 * @param {Object|null} parsed
 *   A parsed class, or null for one this site does not know.
 * @param {string} cls
 *   The raw class, shown when it cannot be parsed.
 * @param {Object<string, number>} widths
 *   Minimum width per breakpoint code.
 *
 * @return {string}
 *   For example "Padding, top: 16px · 768px+", or
 *   "Text color: #3b82f6 · 50% opacity · hover".
 */
function chipText(parsed, cls, widths) {
  if (!parsed) {
    return cls;
  }
  const side = SIDES.find((s) => s.code === parsed.side);
  let text = parsed.utility.label || parsed.prefix;
  if (side) {
    text += `, ${side.label.toLowerCase()}`;
  }
  if (parsed.color) {
    text += `: #${parsed.color}`;
    if (parsed.opacity) {
      text += ` · ${Drupal.t('@percent% opacity', { '@percent': parsed.opacity })}`;
    }
    if (parsed.state && STATES[parsed.state]) {
      text += ` · ${STATES[parsed.state]}`;
    }
  } else {
    text += `: ${readableValue(parsed.value)}`;
  }
  if (parsed.breakpoint) {
    const px = widths && widths[parsed.breakpoint];
    text += px ? ` · ${px}px+` : ` · ${parsed.breakpoint}+`;
  }
  return text;
}

/**
 * The server's class pattern as a JavaScript RegExp.
 *
 * @param {string} phpPattern
 *   UtilikitConstants::CLASS_VALIDATION_PATTERN, delimiters included.
 *
 * @return {RegExp|null}
 *   The pattern, or null when none was sent.
 */
function classPattern(phpPattern) {
  const match = /^\/(.*)\/([a-z]*)$/.exec(String(phpPattern || ''));
  if (!match) {
    return null;
  }
  return new RegExp(match[1], match[2].replace(/[^gimsuy]/g, ''));
}

module.exports = {
  SIDES,
  STATES,
  parseColorValue,
  groupUtilities,
  optionText,
  valueKind,
  placeholder,
  hint,
  normalizeValue,
  compose,
  parseClass,
  currentClasses,
  setClass,
  readableValue,
  screenLabel,
  chipText,
  classPattern,
};
