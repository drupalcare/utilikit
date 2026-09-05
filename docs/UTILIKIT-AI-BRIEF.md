# UtiliKit — CKEditor plugin (U1) and the AI module

Written before any code, per the standing rule that nothing starts unbriefed.
Everything numeric here was measured on 2026-09-05, not estimated.

## 0 · What UtiliKit is, in the terms that matter here

A utility-first CSS framework. `UtilikitRules::getRules()` is the single source
of truth: **50 rules in 12 groups**, each a two-letter prefix mapped to a CSS
property plus flags describing what values it accepts.

| Group | n | Prefixes |
|---|---|---|
| Flexbox | 9 | `fg fk fb or fd jc ai ac` … |
| Layout | 7 | `ar dp ov cu fl cl us` |
| Sizing | 6 | `wd ht xw nw xh nh` |
| Box Model | 5 | `pd mg bw br bs` |
| Positioning | 5 | `tp lt ri bt ps` |
| Typography | 5 | `fs lh fw ls ta` |
| Grid | 4 | `gc gr gl gw` |
| Colors | 3 | `bc bg tc` |
| Effects | 2 | `op zi` |
| Transform | 2 | `rt sc` |
| Background | 1 | `bz` |
| Spacing | 1 | `gp` |

The value flags are the interesting part, because they are what a person or a
model gets wrong: `isNumericFlexible` (19 rules), `isKeyword` (15), `sides` (5),
`isInteger` (4), `isColor` (3), `isRange` (3), `isTransform` (2),
`isGridTrackList` (2), `isDecimalFixed` (2), `isOpacity` (1), `allowAuto` (4 —
was 1 before the drift fix of 2026-09-05).

**The class space is unbounded by design.** `uk-pd--247px`, `uk-mg--3d75rem`,
`uk-wd--87vw` are all generated on demand. So no finite list of classes can be
correct, and anything that offers one is offering a subset.

## 1a · The data contract the AI works against

**Settled 2026-09-05.** `UtilikitRules::getRules()` is now the ONE declaration
of the rules, and `utilikit_js_settings_alter()` publishes it to JavaScript as
`drupalSettings.utilikit.rules`. Before that it existed four times and had
already drifted. Every tool here reads that at runtime, never a list typed into
a prompt.

```
'pd' => ['css' => 'padding', 'sides' => TRUE, 'isNumericFlexible' => TRUE, 'group' => 'Box Model']
```

**The flags are the value grammar**, and they are what a model gets wrong:

| Flag | n | Means |
|---|---|---|
| `isNumericFlexible` | 19 | any number + any unit — `16px`, `3d75rem`, `87vw` |
| `isKeyword` | 15 | a fixed keyword — `uk-ta--center`, and only from the site's list |
| `sides` | 5 | directional forms — `uk-pd--t-16` |
| `isInteger` | 4 | whole numbers only — `fw` is one of these, so `uk-fw--700` is right and `uk-fw--bold` is not |
| `allowAuto` | 4 | `auto` permitted |
| `isColor` | 3 | a hex colour, no `#` — `uk-bg--3b82f6` |
| `isRange` | 3 | grid line span — `uk-gl--1-3` |
| `isTransform` | 2 | `rotate` / `scale` |
| `isGridTrackList` | 2 | track lists — `uk-gc--repeat-3-1fr` |
| `isDecimalFixed` | 2 | fixed-precision decimals |
| `isOpacity` | 1 | 0–100, converted to 0–1 |

`allowAuto` carries its own lesson. It is on `wd`, `ht`, `nw`, `mg` and NOT on
`xw`, `xh` — because `max-inline-size: auto` is not valid CSS, verified against
the browser's own `CSS.supports()`. **A flag is a statement about CSS, not a
preference.** A tool offering `uk-xw--auto` offers a declaration the browser
discards in silence.

## 1b · THE MARKER CLASS. Nothing works without it

**Measured in a browser, 2026-09-05, and it governs everything the AI emits.**
The engine processes only elements carrying a `utilikit` marker class:

```js
document.querySelectorAll('.utilikit')
```

| Markup | Result |
|---|---|
| `class="utilikit uk-pd--37px"` | `padding-top: 37px` |
| `class="uk-pd--37px"` | **`0px` — nothing at all** |

The assistant must always produce `utilikit uk-…`, never the utility class
alone. Emitting the bare class produces markup that looks right, validates, and
does nothing. I nearly missed this: the first audit reported the marker
convention as a *bug*, because it was tested against invented markup rather than
markup UtiliKit produces.

## 1c · Three ways a correct class still does nothing

The assistant must be able to say which is happening, because none is visible
from the page:

1. **The mode needs discovery.** In `inline` a class works the moment it is on
   the page. In `static` and `head` the server generates CSS only for classes it
   already knows, so a brand-new class does nothing until content is scanned and
   the CSS rebuilt.
2. **Auto-update is off for that entity type.** Discovery runs in
   `hook_entity_presave` for `node`, `block_content` and `paragraph`, each gated
   by its own setting.
3. **A text filter stripped it.** The WYSIWYG case — it vanishes on save with no
   error anywhere.

## 1d · The tools, and where each writes

WHAT SHIPPED, not what was planned. This table was a plan and three of its rows
named tools that were never built; it now records the four that exist, verified
live on sts.local 2026-09-05 (28 checks, every command and every refusal path).

| Tool | Writes? | For |
|---|---|---|
| `utilikit_explain_utility` | no | what `pd` does and what values it takes, read from the flags in §1a |
| `utilikit_check_classes` | no | verdict per class — valid, invalid with the reason, or explicitly unverified |
| `utilikit_diagnose_setup` | no | why a *correct* class still does nothing here — the three causes in §1c |
| `utilikit_apply_classes` | **yes** | adds classes to every element of one tag in a page's formatted text |

`apply_classes` is the only writer, and it only ever adds a class attribute —
never text, never structure, never a whole field. Five rules it cannot be
configured out of: a new revision every time; refusal where the text format
would strip the class; one bad class refuses the whole request; the §1b marker
added automatically; and every precondition re-checked by the writer itself
rather than trusted from an earlier tool call, because a model can skip those.

THE SURFACES, corrected. The **playground was considered and is not a write
surface** — it is entirely client-side, so there is nothing there for a
server-side tool to write into. The two real surfaces are **stored content**,
which `apply_classes` covers, and the **WYSIWYG**, which still needs U1: without
the plugin there is nowhere for an assistant to put anything while someone is
editing.

## 1 · The one thing that must not be repeated

Every module in this project has hit the same defect: **a control you can set
and watch do nothing.** In UtiliKit's WYSIWYG context it has a specific and
very likely form:

> A text format's filter strips `class` attributes it does not allow. An editor
> applies `uk-pd--16px` in CKEditor, saves, and the class is gone — with no
> error anywhere.

Anything built here must **detect that and say so**, in words, on the screen
where the person is working. A plugin that adds classes a filter will silently
remove is worse than no plugin.

## 2 · U1 — the CKEditor plugin. An open decision, not a detail

There are two shapes, and they are not variations on one design. **This has to
be settled before code, because they share almost nothing.**

### (a) A real CKEditor 5 plugin

Arbitrary values, live preview, a proper UI in the editor.

Cost: CKEditor 5 plugins for Drupal are authored as ES modules against the
`ckeditor5` packages and loaded by Drupal as a **built bundle**. That means
webpack, `ckeditor5` dev dependencies, and a **committed build artifact** under
`js/build/`. UtiliKit today has **no bundler and no build step** — twelve plain
JS files loaded as Drupal libraries. This adds a node toolchain to a module that
has none, and puts generated code in the repository.

CORRECTION: an earlier version of this brief said the `eslint` job on utilikit
was already failing. That was wrong — utilikit had no eslint job at all; the
project was on a hand-rolled pipeline with only build/phpcs/unit/kernel/
functional. It gained eslint, stylelint, cspell and phpstan on 2026-09-05 when
CI moved to the Drupal Association templates, and what those report is not yet
known.

### (b) Generated configuration for core's Style plugin

Drupal core's CKEditor 5 **Style** plugin applies classes to elements from a
list declared in the text format. UtiliKit would generate that list from
`getRules()`.

Cost: the list is finite, so it contradicts the "unlimited values" premise —
it can offer `uk-pd--8px`, `uk-pd--16px`, `uk-pd--24px`, not `uk-pd--247px`.

Gain: **no JavaScript at all**, no build step, no committed artifacts, works on
every Drupal 11/12 site today, and the allowed-classes problem in §1 is solved
in the same place the list is declared.

**CORRECTED 2026-09-05.** My first recommendation was (b), argued partly on
speed — a cost argument dressed as a design one, and the owner said so. Having
then checked what contrib actually does: `ai_ckeditor` ships
`webpack.config.js`, `package-lock.json`, sources under `js/ckeditor5_plugins/`
and a **committed** `js/build/*.js`. The build artifact I called an unusual cost
is simply how CKEditor 5 plugins are shipped on drupal.org.

So **(a) is the professional path**, and the toolchain is normal rather than
exotic. Two things make it more than "write a plugin":

- **The UI should be generated from the flags**, not a flat list of fifty
  prefixes. `isKeyword` becomes a select of allowed keywords;
  `isNumericFlexible` a number field plus a unit picker; `isColor` a colour
  control; `sides` a top/right/bottom/left selector. That data already exists.
- **It must keep the text format's allowed HTML in sync** with what it can
  insert, and say so on screen when it cannot.

Still the owner's call, and (b) remains a legitimate first step if shipping
sooner matters more than arbitrary values.

## 3 · The AI module — what it is FOR

Stated plainly, because the sibling module was built read-only once and had to
be rewritten: **the assistant must produce utility classes the person can use,
against the rules this site actually has.**

Three things a model cannot do reliably without tools, all measured against
`getRules()` rather than guessed:

1. **Name the right prefix.** "make the padding bigger" → `pd`, not `padding`,
   `p`, or `pad`. Fifty two-letter prefixes is exactly the kind of vocabulary a
   model half-remembers.
2. **Respect the value flags.** `fw` takes a keyword, `op` takes an opacity,
   `gc` takes a grid track list. A syntactically plausible class with the wrong
   value class renders nothing.
3. **Say when the format will strip it** — §1 again, this time answered in the
   conversation rather than on a form.

## 4 · Scope

**May touch:** a new `utilikit_ai` submodule; a new CKEditor-related submodule
or config for U1; `docs/`. Read `UtilikitRules` and the playground.

**Must NOT touch:** the CSS generation engine, the rendering modes, the
existing rules, the settings form, or any other module in this project.
UtiliKit must stay installable without `drupal/ai`, exactly as `vvj_core` and
`paragraphs_bundles` do.

**Must NOT change a person's configured settings.** Same rule as the two
siblings: the AI may create things and explain things; it does not rewrite
someone's configuration.

## 5 · Evidence standard

Measure, do not assume. Specifically:

- Class lists come from `getRules()` at runtime, never from a list typed into
  a prompt or a docblock — a hardcoded copy is a second source of truth and
  will drift.
- Any claim about what a text format allows is read from the filter
  configuration, not inferred from the format's name.
- Write *"needs checking"* rather than asserting. A number that was not
  measured says so.

## 6 · Refusal conditions — stop and ask rather than guess

- The U1 shape in §2 is undecided.
- A rule's value semantics are ambiguous from its flags.
- Anything that would require changing the CSS engine.
- Anything that would put a generated artifact in the repository without the
  owner having agreed to it.

## 7 · How this will be verified, before anything is called done

Learned the hard way across the two sibling modules, in this order:

1. Kernel tests, including a **mutation probe** on any guard that is the whole
   safety model — remove the guard, the probe must fail.
2. **A live model conversation**, not just direct tool calls. Every defect worth
   finding in `paragraphs_bundles_ai` and `vvj_core_ai` lived in the gap between
   "the tool works" and "a model uses it correctly".
3. **A browser check with the console read** — and the console proven live with
   a marker first, because an empty reader and a dead reader look identical.
4. **The drupal.org job logs read directly** at `/-/jobs/<id>/raw`, per job.
   A green pipeline here hides red jobs: on these projects only `composer`,
   `composer-lint`, `phpunit` and `phpunit (previous minor)` are blocking.
   `eslint`, `stylelint` and `cspell` are already failing on utilikit and must
   not be confused with anything new.
5. **What CI installs is not what this machine has.** `vvj_core` has no
   `require-dev`; a test that needs a module the project does not declare will
   pass here and fail there. Check `composer.json` before relying on a module.

## 8 · The priors this work is held to

- **Better safe than sorry.** Aggressive on architecture, conservative on
  process. Verify before commit; validate at trust boundaries.
- **One source of truth.** `getRules()` is it. Two copies of the rule list IS
  the bug.
- **Build once and done.** No "we will fix it in v2".
- **Understand before you change.** A plausible fix to code you do not
  understand turns design into a regression.
- **Measure, do not assume.** Report what was measured and how; say plainly
  when a number is a guess.
- **No leftovers.** A decision that lives only in a conversation is a leftover.
