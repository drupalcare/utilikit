# UtiliKit AI

Optional. Helps compose and diagnose UtiliKit utility classes — which prefix to
use, what values it accepts, and why a class that looks right is doing nothing.

It is a separate module on purpose. `utilikit` and every other submodule stay
installable without `drupal/ai`; nothing here is referenced from them.

## What it can do

| Tool | Writes? | What it is for |
|---|---|---|
| `utilikit_explain_utility` | no | What a prefix does and exactly which values it accepts **on this site** |
| `utilikit_check_classes` | no | Checks a class string before it goes anywhere near a page |
| `utilikit_diagnose_setup` | no | Why a valid class still does nothing here |
| `utilikit_apply_classes` | **yes** | Adds classes to elements in a page's formatted text |

### The five rules the writer cannot be configured out of

1. **A new revision every time.** Anything a machine changes in someone's
   content must be reversible by someone who did not watch it happen.
2. **It refuses where the text format would strip the class.** Measured on a
   real site: Basic HTML and Restricted HTML both do. Writing something that is
   silently discarded is worse than refusing, because it looks like it worked.
3. **One bad class refuses the whole request.** A partial application leaves
   content in a state nobody asked for and nobody can see.
4. **The `utilikit` marker is added automatically**, because without it the
   element is ignored entirely.
5. **Every precondition is re-checked by the writer itself**, not trusted from
   an earlier tool call, because a model can skip those.

It only ever adds a class attribute — never text, never structure, never a
whole field.

## Why tools rather than a good prompt

UtiliKit has fifty two-letter prefixes and eleven different value grammars, and
which grammar applies is not predictable from the CSS property:

- `fw` is font-weight and takes a **number**, so `uk-fw--700` works and
  `uk-fw--bold` does not.
- `ta` is text-align and takes a **keyword**, and only from this site's list.
- `bg` takes hex digits with **no leading hash** — `uk-bg--3b82f6`.

A model writes all three the CSS way and gets two of them wrong. Neither
mistake raises anything: the class sits in the markup looking correct and no
style appears. Every answer here is read from `UtilikitRules` at runtime, so it
describes the site in front of you rather than a remembered version of UtiliKit.

*An earlier draft of this file said `fw` was a keyword rule. It is not. The
error survived until the tool was run against a real site — which is the
argument for the tool, made at the documentation's expense.*

## The marker class

UtiliKit styles **only** elements carrying a `utilikit` class:

```html
<div class="utilikit uk-pd--16px">   <!-- padding applies -->
<div class="uk-pd--16px">            <!-- nothing at all -->
```

Measured in a browser, not assumed. Every tool here mentions it, and
`utilikit_check_classes` reports whether it is present, because it is the most
common way to produce markup that looks right and does nothing.

## Three ways a correct class still does nothing

`utilikit_diagnose_setup` checks all three, because they are indistinguishable
from the page and each has a different fix:

1. **The mode has not discovered it.** In `inline` a class works the moment it
   is on the page. In `static` and `head` the server generates CSS only for
   classes it already knows.
2. **Auto-update is off for that entity type.** Discovery runs on save for
   `node`, `block_content` and `paragraph`, each gated separately.
3. **A text format strips the class.** The class never reaches the page, and
   nothing reports it.

## Setting it up

1. Enable `utilikit_ai`.
2. Enable `ai_assistant_api`, `ai_agents` and `ai_chatbot` if you want a chat
   surface. They are deliberately **not** dependencies.
3. **Point your assistant at an agent, and this is the step people miss.** An
   assistant built with `ai_assistant_api` alone builds its tool list from
   `actions_enabled`, which is a *different plugin type*. The tools here are
   `FunctionCall` plugins, and an assistant reaches those **only through an AI
   Agent**.

   This module ships one — `utilikit_agent`, in `config/optional`, with the
   four tools already enabled on it — so there is usually nothing to create.
   Optional config installs only when its dependencies are present **at the
   time the module is installed**, so if `ai_agents` was enabled *after*
   `utilikit_ai`, the agent is simply absent. Re-installing `utilikit_ai`
   brings it in; so does creating one by hand and enabling the `utilikit:*`
   tools on it.

## What is not here, and why

**The WYSIWYG — no longer absent, and it is a separate module.** Applying
classes *interactively while editing* is `utilikit_ckeditor`, a real CKEditor 5
plugin with its own toolbar button. It is deliberately not part of this module:
it needs no AI and no `drupal/ai`, so tying it to this one would have made a
plain editing feature depend on an LLM. `apply_classes` here still writes into
stored content, which is where the classes actually live; the two are
complementary rather than alternatives.

**Not the playground.** It was considered as a write surface and is not one:
the playground is entirely client-side, so there is nothing for a server-side
tool to write into.

## Testing

```bash
vendor/bin/phpunit web/modules/contrib/utilikit/modules/utilikit_ai
```

The kernel tests cover each tool, each rejection with its reason, and a probe
asserting the validator judges against the shared catalogue rather than a list
of its own — because a second copy of the rules is the defect this module was
built after fixing.

## Requirements

Drupal ^11.2 and `drupal/ai`. Not `^12`: `drupal/ai` declares
`^10.5 || ^11.2`, so claiming `^12` would offer the module on a site where it
cannot install. A Drupal 12 site installs `utilikit` and every other submodule
and simply does not get this one.

## License

GPL-2.0-or-later
