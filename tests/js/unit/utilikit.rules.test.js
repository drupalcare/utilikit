/**
 * @file
 * Tests for utilikit.rules.js — the registry shim.
 *
 * WHAT THIS FILE USED TO DO, and why it was replaced. It built its own copy of
 * the rules in `beforeEach`, assigned it to `Drupal.utilikit.rules`, and then
 * asserted things about that copy. It never loaded utilikit.rules.js at all, so
 * every assertion was the mock agreeing with itself. It could not have failed
 * for any change to the module.
 *
 * It was also a FOURTH copy of the rule definitions, and the most drifted of
 * the lot — it claimed `wd: 'width'` where the real property is `inlineSize`,
 * and `rt: 'transform'` where it is `rotate`.
 *
 * The file under test no longer declares rules. It reads them from
 * `drupalSettings.utilikit.rules`, published by utilikit_js_settings_alter()
 * from UtilikitRules::getRules(). So what is worth testing here is the shim's
 * contract, not the content of the rules:
 *
 *   - it passes through whatever it is given;
 *   - it reports a missing payload loudly, because an empty registry silently
 *     stops every utility class on the page from working;
 *   - it never leaves the registry undefined.
 *
 * The rules' actual correctness is asserted in PHP, against the one source, by
 * UtilikitRulesSingleSourceTest.
 */

const fs = require('fs');
const path = require('path');

const SHIM = path.join(__dirname, '..', '..', '..', 'js', 'utilikit.rules.js');

/**
 * Loads utilikit.rules.js against a given drupalSettings.
 *
 * The file is an IIFE taking (Drupal, once, drupalSettings), so it is invoked
 * directly rather than imported — that is exactly how Drupal delivers it.
 *
 * @param {Object|undefined} utilikitSettings
 *   The value for drupalSettings.utilikit, or undefined to omit it.
 *
 * @return {Object}
 *   The Drupal object the shim populated, plus the captured console errors.
 */
function loadShim(utilikitSettings) {
  const Drupal = {};
  const drupalSettings = utilikitSettings === undefined ? {} : { utilikit: utilikitSettings };
  const errors = [];
  const fakeConsole = { error: (msg) => errors.push(String(msg)) };

  const source = fs.readFileSync(SHIM, 'utf8');

  // The shim's own closure receives the doubles; nothing global is touched.
  // eslint-disable-next-line no-new-func
  new Function('Drupal', 'once', 'drupalSettings', 'console', source)(
    Drupal, () => [], drupalSettings, fakeConsole,
  );

  return { Drupal, errors };
}

describe('utilikit.rules.js — the registry shim', () => {

  test('publishes exactly the rules it is given', () => {
    const payload = {
      pd: { css: 'padding', sides: true, isNumericFlexible: true, group: 'Box Model' },
      wd: { css: 'inlineSize', isNumericFlexible: true, allowAuto: true, group: 'Sizing' },
    };

    const { Drupal, errors } = loadShim({ rules: payload });

    expect(Drupal.utilikit.rules).toEqual(payload);
    expect(errors).toEqual([]);
  });

  test('does not invent, drop or reshape a rule', () => {
    // The shim's whole job is to be transparent. If it ever starts massaging
    // the payload it becomes a second opinion about the rules, which is the
    // thing this architecture exists to prevent.
    const payload = { zz: { css: 'somethingUnknown', madeUpFlag: true } };

    const { Drupal } = loadShim({ rules: payload });

    expect(Object.keys(Drupal.utilikit.rules)).toEqual(['zz']);
    expect(Drupal.utilikit.rules.zz).toEqual({ css: 'somethingUnknown', madeUpFlag: true });
  });

  test('reports a missing payload loudly rather than failing silently', () => {
    // This is the case that matters. With no rules, every uk- class on the page
    // quietly stops working and nothing appears in the interface to say why.
    const { errors } = loadShim({ renderingMode: 'inline' });

    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatch(/drupalSettings\.utilikit\.rules/);
    expect(errors[0]).toMatch(/utilikit_js_settings_alter/);
  });

  test('reports it when the utilikit settings are absent entirely', () => {
    const { errors } = loadShim(undefined);

    expect(errors).toHaveLength(1);
    expect(errors[0]).toMatch(/no rule definitions/i);
  });

  test('always leaves a usable registry, never undefined', () => {
    // Consumers do `prefix in Drupal.utilikit.rules`. An undefined registry
    // would throw inside the engine rather than degrade.
    for (const settings of [undefined, {}, { rules: null }, { rules: {} }]) {
      const { Drupal } = loadShim(settings);

      expect(Drupal.utilikit).toBeDefined();
      expect(typeof Drupal.utilikit.rules).toBe('object');
      expect(Drupal.utilikit.rules).not.toBeNull();
    }
  });

  test('declares no rules of its own', () => {
    // The drift guard, from the JavaScript side. Its PHP counterpart scans
    // every file; this one pins the file that used to hold the map.
    const source = fs.readFileSync(SHIM, 'utf8');

    expect(source).not.toMatch(/\b[a-z]{2,4}\s*:\s*\{\s*css\s*:\s*['"]/);
  });

});
