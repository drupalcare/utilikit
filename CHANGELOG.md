# Changelog

All notable changes to UtiliKit are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project follows [Semantic
Versioning](https://semver.org/spec/v2.0.0.html).

**This file starts at 1.0.5.** Releases 1.0.0 through 1.0.4 shipped without a
changelog, and their contents are not reconstructed here — the git history is
the record for those.

---

## [1.0.5] — UNRELEASED

### Added

- **`utilikit_ckeditor`** — optional CKEditor 5 plugin. Adds a toolbar control
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
  afternoon — that a FunctionCall tool reaches an assistant only through an AI
  Agent, so an assistant without one answers questions and silently changes
  nothing. That page also names any text format that would strip the classes,
  which is the exact failure this module exists to stop being silent.

  The list of field types the assistant can write into now has **one owner**,
  `ClassApplierInterface::RICH_TEXT_FIELD_TYPES`, read by both the writer and
  the surface. Two copies would have drifted into a bar offered where nothing
  can be written, or hidden where something can — and neither reports anything.
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

- `UtilikitConstants::MARKER_CLASS` — the `utilikit` marker string now has one
  owner. It had begun to spread: the AI writer kept a private copy and a
  constant encodes it in a regular expression.

### Fixed

- `@media (prefers-contrast: high)` — `high` is not a value the specification
  defines, so that accessibility rule had never applied in any current browser.
  It is `more`.
- A keyframe name, a file header naming the wrong filename, and a typo in a
  shipped config comment.
- A class used before its definition in the test-suite runner. Classes are not
  hoisted the way functions are, so it worked only because Drupal runs
  `attach()` long after the file executes.
- **`utilikit_ckeditor` left its toolbar item behind on uninstall.** The text
  format kept a `utilikit` button pointing at a plugin that no longer existed.
  This is core behaviour rather than an oversight — `Editor::calculateDependencies()`
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
