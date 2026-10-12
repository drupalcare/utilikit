# Changelog

All notable changes to UtiliKit are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project follows [Semantic
Versioning](https://semver.org/spec/v2.0.0.html).

**This file starts at 1.0.5.** Releases 1.0.0 through 1.0.4 shipped without a
changelog, and their contents are not reconstructed here. The git history is
the record for those.

---

## [Unreleased]

### Fixed

- **`utilikit_ckeditor`: Drupal 12 raised "CKEditor5.dll() is deprecated" on
  every editor page.** Drupal 12 ships CKEditor 5 as a UMD bundle and reaches
  DLL-built plugins only through a deprecated shim; Drupal 11 can load only the
  DLL build. `npm run build` now writes two files from the one source —
  `js/build/utilikit.js` (DLL, Drupal 11) and `js/build/utilikit.externals.js`
  (externals to `CKEDITOR`, Drupal 12), and `UtilikitCkeditorHooks` serves the
  one matching the runtime core's own `core/ckeditor5` library loads. The editor
  button behaves the same on both. `UtilikitCkeditorBuildSelectionTest` pins
  the choice; `UtilikitCkeditorToolbarIconTest` now fails if either build is
  stale against the plugin source or its build config. Run a cache rebuild
  after updating.

## [1.0.7] (2026-10-09)

### Fixed

- **`utilikit_ckeditor`: the toolbar button had no icon.** In *Text formats →
  Available buttons* it showed as an empty gray box, because the admin library
  set no background image for `.ckeditor5-toolbar-button-utilikit`. Core draws
  every button there as an empty span and leaves the icon to that CSS. The
  button now uses the Material Symbols `handyman` icon (Apache-2.0, see
  `modules/utilikit_ckeditor/icons/`), and the editor toolbar shows the same
  SVG next to the label. `UtilikitCkeditorToolbarIconTest` fails if any toolbar
  item lacks an admin icon, or if the built editor plugin stops embedding the
  same SVG.
- **`utilikit_ckeditor`: a block styled with the button vanished from the
  editor.** In inline rendering mode `utilikit.engine.css` hides every
  `.utilikit` element until the engine processes it, and the engine never
  processes the editing view, so applying a class made the paragraph
  invisible while editing. The editor stylesheet now keeps editable content
  visible; the front end is unchanged.
- **`utilikit_ckeditor`: changing a value added a second class.** Setting
  padding to 24px on a block that had 16px left both `uk-pd--16px` and
  `uk-pd--24px`. A new value now replaces the old one for the same utility,
  side and screen size.
- **`utilikit_ckeditor`: any typed value was written as a class.**
  `uk-ta--banana` was accepted, and a value with a space put a second class on
  the element. Keyword utilities now offer only their accepted words, colors a
  picker, and every class is checked against
  `UtilikitConstants::CLASS_VALIDATION_PATTERN` before it is written.
- **`utilikit_ckeditor`: an endless blue shimmer ran inside the editor.** The
  engine's inline-mode loading indicator never stopped on styled blocks in the
  editing view, and its sliding layer kept widening the editor's scrollbar. It
  is now hidden inside the editor; the front end is unchanged.
- **`utilikit_ckeditor`: the panel ran off the bottom of the screen**, hiding
  Apply. It now opens on the side of the button with more room and caps its
  height to the space left in the window, on open and on resize or scroll;
  its body scrolls while Apply and the footer stay in view.
- **`utilikit_ckeditor`: state and opacity suffixes were read as values.**
  `uk-tc--3b82f6-h` showed as "Text color: 3b82f6-h"; it now reads
  "Text color: #3b82f6 · hover" (likewise focus, active and `-50` opacity).

### Changed

- **`utilikit_ckeditor`: the toolbar panel is reorganized.** It shows the
  styles already on the block as removable, editable chips in plain words
  ("Padding, top: 16px · 768px+"), then *Style* (grouped, "Max width (xw)"
  rather than "xw — maxInlineSize"), *Side*, *Value* (with a help line for
  what to type) and *Screen size* ("768px and wider (md)"), with Apply and
  Remove all. It is drawn with CKEditor's own field and button styles, every
  label is tied to its control, and Tab cycles inside the panel. The styles on
  the block are compact chips with a count, in a list that scrolls when a
  block carries many. A small footer links to Playground, Help and Reference,
  each shown only when its module is on and the user may open it, and each
  opening in a new tab so the edit is not lost.
- **`UtilikitRules::getRules()`: every rule now carries a `label`.** The plain
  words for the utility live on the rule, so no consumer keeps its own list of
  names. `utilikit_ai` excludes it from the flags it reasons about.

## [1.0.6] (2026-10-07)

### Fixed

- **CSS: `all: unset` now comes first in its rule.** It resets every
  property, so placed after the rule's other declarations it erased them
  (size, border, radius, background).

## [1.0.5] (2026-10-05)

### Added

- **`utilikit_ckeditor`**: optional CKEditor 5 plugin. Adds a toolbar control
  that applies UtiliKit classes to the block you are editing.

  Core's Style plugin can already put a class on an element, so the reason this
  exists is worth stating: Style works from a list typed into the text format
  settings, and UtiliKit's classes are *composed* (`uk-pd--16px`,
  `uk-md-bg--3b82f6`), so the useful set is unbounded and cannot be enumerated.
  The plugin is handed the **rules** instead and composes against them.

  It adds the required `utilikit` marker for you, claims only its own classes
  (a theme's class on the same element survives editing and *Clear*), and
  declares `<$any-html5-element class>` so the text format does not strip what
  the editor just applied. `js/build/` is committed, following Drupal core.

- **`utilikit_ai` gained a chat surface**, which its two sibling AI modules
  already had and it did not. A bar on node add and edit forms, a permission
  (*Use the UtiliKit assistant*), and a settings page at Configuration ›
  Development › UtiliKit › AI.

  The bar is the small half. **The settings page is the point:** six things
  must be true before the bar appears and not one of them is visible from the
  node form, so the page prints which one is missing as a sentence. Without it
  the module had nowhere on the site to say the thing that costs people an
  afternoon, that a FunctionCall tool reaches an assistant only through an AI
  Agent, so an assistant without one answers questions and silently changes
  nothing. That page also names any text format that would strip the classes,
  which is the exact failure this module exists to stop being silent.

  The list of field types the assistant can write into now has **one owner**,
  `ClassApplierInterface::RICH_TEXT_FIELD_TYPES`, read by both the writer and
  the surface. Two copies would have drifted into a bar offered where nothing
  can be written, or hidden where something can, and neither reports anything.
  A test drives itself from the constant, so it fails if they part.

- **`utilikit_ai`** gained `utilikit_apply_classes`, its first tool that
  writes. It adds classes to a page's formatted text under five rules it cannot
  be configured out of: a new revision every time, refusal where the text format
  would strip the class, one bad class refusing the whole request, the marker
  added automatically, and every precondition re-checked by the writer rather
  than trusted from an earlier call.

- **`ai_agents.ai_agent.utilikit_agent`** in `config/optional/`. A FunctionCall
  plugin reaches an assistant only through `ai_agents`, so without an agent the
  tools were unreachable by anything on the site.

- `UtilikitConstants::MARKER_CLASS`: the `utilikit` marker string now has one
  owner. It had begun to spread: the AI writer kept a private copy and a
  constant encodes it in a regular expression.

### Fixed

- `@media (prefers-contrast: high)`: `high` is not a value the specification
  defines, so that accessibility rule had never applied in any current browser.
  It is `more`.
- A keyframe name, a file header naming the wrong filename, and a typo in a
  shipped config comment.
- A class used before its definition in the test-suite runner. Classes are not
  hoisted the way functions are, so it worked only because Drupal runs
  `attach()` long after the file executes.
- **`utilikit_ckeditor` left its toolbar item behind on uninstall.** The text
  format kept a `utilikit` button pointing at a plugin that no longer existed.
  This is core behavior rather than an oversight: `Editor::calculateDependencies()`
  records only the *editor* plugin's provider, so no config dependency on a
  CKEditor 5 plugin's module ever exists for the uninstall machinery to act on
  (core carries a `@todo` about it). The module now removes its own item, and
  a kernel test fails if that stops happening.

### Changed

- Lint backlog cleared: **stylelint 718 → 0, eslint 1,505 problems → 0 errors,
  cspell 292 → 0**. These jobs had never passed; they began running when the
  pipeline moved to the Drupal Association templates.
- **PHPStan raised from level 5 to 6** with array shapes throughout, matching
  the standard used across this maintainer's other projects.
- `composer.json` suggests `drupal/ai`, naming the sub-module that needs it.
