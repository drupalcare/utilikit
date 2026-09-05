# UtiliKit CKEditor

Adds a **UtiliKit classes** button to the CKEditor 5 toolbar. Select a block —
a paragraph, heading, list item, blockquote — pick a utility, give it a value,
and the class is applied to that element.

Optional. UtiliKit works without it, and this module needs no AI.

## Why this is not core's Style plugin

Core's Style plugin can already put a class on an element, so the fair question
is why UtiliKit ships its own.

Style works from a list someone types into the text format settings: every
class it can ever apply has to be written out in advance. UtiliKit's classes are
*composed* — `uk-pd--16px`, `uk-pd--t-40px`, `uk-md-bg--3b82f6` — so the useful
set is effectively unbounded and cannot be enumerated.

So this plugin is given the **rules** rather than a list of classes, and
composes against them: which prefixes exist on this site, what each one accepts,
and the keyword values where a rule takes keywords.

## Where the rules come from

`UtilikitRules` — the same single declaration the CSS generator and the
JavaScript engine read. The editor is a third consumer of one source of truth,
not a fourth copy of the facts. `UtilikitCkeditorPluginTest` asserts the editor
sees exactly the rules that class declares, so a rule added there reaches the
toolbar without anyone remembering to update it here.

## What it guarantees

- **The `utilikit` marker is added for you.** Without it the engine ignores the
  element and every class on it is inert. You cannot forget it.
- **It only claims its own classes.** A `some-theme-class` on the same element
  survives editing, and *Clear* removes UtiliKit's classes while leaving it
  alone. The upcast matches the marker and the `uk-` namespace, nothing else.
- **Classes survive the round trip.** A class attribute CKEditor does not know
  about is discarded when content passes through the editor — the author sees
  their styling vanish on the next save with nothing to explain it. The
  attribute is declared on every block and converted both ways.
- **The text format is told.** The plugin declares
  `<$any-html5-element class>`, the same declaration core's Style plugin makes,
  so the filter does not strip what the editor just applied.

## Building the JavaScript

`js/build/utilikit.js` **is committed**, following Drupal core, which commits
`core/modules/ckeditor5/js/build/*.js`. A module that gitignores its build
output ships a toolbar button that silently fails to load for anyone installing
from the dev branch, because the file the library points at is not there.

To rebuild after changing anything under `js/ckeditor5_plugins/`:

```
npm install
npm run build
```

The webpack configuration is core's own, reduced to this module's single
plugin. It shares core's CKEditor 5 runtime through `DllReferencePlugin` rather
than bundling a second copy — two CKEditor instances do not recognise each
other's classes, and that fails at runtime rather than at build time.

## With utilikit_ai

Nothing here depends on it. If `utilikit_ai` is installed you also get the
assistant tools, including one that writes classes into stored content; this
module is the same job done by hand, while editing.
