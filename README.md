# UtiliKit - Utility-First CSS Framework for Drupal

UtiliKit is a comprehensive Drupal 10/11 contrib module that provides a powerful
utility-first CSS system directly integrated into your Drupal project. It offers
a dynamic class generation engine for rapidly applying box model, spacing,
flexbox, grid, color, effects, positioning, typography, transforms, and layout
utilities using a clean 2-letter prefix system with unlimited value flexibility.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Rendering Modes](#rendering-modes)
- [Utility Class Reference](#utility-class-reference)
- [Special Value Notations](#special-value-notations)
- [Responsive Design](#responsive-design)
- [CSS Specificity Control](#css-specificity-control)
- [Developer Tools](#developer-tools)
- [Drush Commands](#drush-commands)
- [Configuration](#configuration)
- [Sub-Modules](#sub-modules)
- [Performance](#performance)
- [Security](#security)
- [Browser Support](#browser-support)
- [Contributing](#contributing)
- [License](#license)
- [Maintainers](#maintainers)

## Features

### Unlimited Value Flexibility
Unlike traditional CSS frameworks that restrict you to predefined classes,
UtiliKit accepts **any numerical value with any unit**. Want `uk-pd--247px`,
`uk-mg--3d75rem`, or `uk-wd--87vw`? UtiliKit generates these on-demand.

### Triple Rendering Modes
- **Inline Mode**: JavaScript applies styles dynamically (development-friendly)
- **Static Mode**: Pre-generated CSS files served from disk (production-optimized)
- **Head Mode**: CSS injected into the page `<head>` (cloud and container-friendly)

### 47 Utility Prefixes
Complete coverage of CSS properties across box model, sizing, positioning,
typography, colors, effects, layout, flexbox, grid, and transforms — all using
a consistent 2-letter prefix system.

### CSS Specificity Control
Optional `!important` flag on all utility classes (static/head modes) to ensure
utility classes always override other styles. Matches Tailwind CSS behavior.

### Logical CSS Properties
UtiliKit uses modern logical CSS properties (`inlineSize`, `blockSize`,
`insetBlockStart`, `insetInlineEnd`, etc.) for better internationalization
and RTL support.

### Comprehensive Developer Experience
- **Playground**: Interactive testing environment with live preview
- **Reference Documentation**: Complete class documentation with examples
- **Update Button**: Instant CSS regeneration during development
- **Debug Tools**: Comprehensive logging and performance monitoring

### Drupal Integration
- Permission-based access control (3 granular permissions)
- Entity save triggers for automatic CSS updates (nodes, blocks, paragraphs)
- Queue worker for asynchronous CSS processing during cron
- 8 Drush commands for CLI management
- Compatible with Layout Builder, Paragraphs, Views, and all themes
- No external dependencies required

## Installation

### Via Composer (Recommended)

```bash
composer require drupal/utilikit
drush en utilikit
```

### Manual Installation

1. Download the module from Drupal.org
2. Extract to `modules/contrib/utilikit`
3. Enable via admin interface or Drush: `drush en utilikit`

### Post-Installation Setup

1. Navigate to **Configuration > UtiliKit Settings**
   (`/admin/config/utilikit/settings`)
2. Choose your rendering mode (Inline for development, Static for production,
   Head for cloud/containers)
3. Configure scope settings to define where UtiliKit operates
4. Set up permissions for development tools and reference access
5. Test the installation using the interactive Playground

## Quick Start

### Basic Usage

Add the `utilikit` base class to any element, then apply utility classes:

```html
<!-- Padded container with background color -->
<div class="utilikit uk-pd--16 uk-bg--007bff uk-tc--ffffff">
  Content with padding, background, and text color
</div>

<!-- Responsive layout -->
<div class="utilikit uk-dp--block uk-md-dp--flex uk-jc--between">
  <div class="utilikit uk-fg--1">Flexible item</div>
  <div class="utilikit uk-wd--200">Fixed width item</div>
</div>

<!-- CSS Grid layout -->
<div class="utilikit uk-dp--grid uk-gc--repeat-3-1fr uk-gp--16">
  <div class="utilikit uk-pd--12 uk-bg--f8f9fa">Grid item 1</div>
  <div class="utilikit uk-pd--12 uk-bg--f8f9fa">Grid item 2</div>
  <div class="utilikit uk-pd--12 uk-bg--f8f9fa">Grid item 3</div>
</div>
```

**Important**: Every element must have the `utilikit` base class for UtiliKit to
process its utility classes.

### Class Naming Convention

`uk-{breakpoint}-{prefix}--{value}`

- `uk-`: UtiliKit namespace prefix
- `{breakpoint}`: Optional responsive breakpoint (`sm`, `md`, `lg`, `xl`, `xxl`)
- `{prefix}`: 2-letter property identifier (47 available)
- `--`: Separator between prefix and value
- `{value}`: CSS value (supports units, keywords, decimals, and special notation)

## Rendering Modes

### Inline Mode (Development)

- JavaScript processes classes dynamically in the browser
- Immediate visual feedback — no page refreshes needed
- Ideal for development, prototyping, and content editing
- Zero build process — no CSS file generation required
- Real-time class validation and error reporting

### Static Mode (Production)

- Pre-generates CSS files on the server for optimal performance
- Minimal JavaScript footprint at runtime
- Supports CSS minification via the optimize_css setting
- Automatic updates when content is saved (configurable per entity type)
- File-based caching with cache-busting URLs
- Requires file system write permissions (`public://utilikit/`)

### Head Mode (Cloud & Containers)

- CSS injected directly into the page `<head>` on each request
- No file system write permissions required
- Perfect for read-only environments: Platform.sh, Pantheon, Acquia Cloud,
  Kubernetes, and containerized deployments
- Stores CSS in Drupal's State API for persistence
- Same automatic content scanning as static mode
- Supports CSS minification

## Utility Class Reference

### Box Model (4 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `pd` | padding | `uk-pd--16`, `uk-pd--t-15`, `uk-pd--x-30` | Directional: t/r/b/l, shorthand: x/y |
| `mg` | margin | `uk-mg--16`, `uk-mg--b-auto`, `uk-mg--x-3d25em` | Supports `auto` keyword |
| `bw` | border-width | `uk-bw--2`, `uk-bw--t-1` | Directional sides |
| `br` | border-radius | `uk-br--8`, `uk-br--t-12` | Directional corners |

### Border Style & Color (2 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `bs` | border-style | `uk-bs--solid`, `uk-bs--b-dashed` | Keywords: solid, dashed, dotted, double, groove, ridge, inset, outset |
| `bc` | border-color | `uk-bc--dee2e6`, `uk-bc--f00-50` | Hex colors with optional alpha |

### Sizing (6 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `wd` | inline-size (width) | `uk-wd--200`, `uk-wd--100pr` | Supports `auto`, px, %, rem, em, vw |
| `ht` | block-size (height) | `uk-ht--100`, `uk-ht--50vh` | Supports `auto` |
| `xw` | max-inline-size | `uk-xw--1200` | Supports `auto` |
| `nw` | min-inline-size | `uk-nw--100` | Minimum width constraint |
| `xh` | max-block-size | `uk-xh--300` | Height constraint |
| `nh` | min-block-size | `uk-nh--50` | Minimum height |

### Typography (5 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `fs` | font-size | `uk-fs--18`, `uk-fs--2d5rem` | Any unit supported |
| `lh` | line-height | `uk-lh--140`, `uk-lh--1d5` | Unitless or with units |
| `fw` | font-weight | `uk-fw--400`, `uk-fw--700` | Integer values |
| `ls` | letter-spacing | `uk-ls--2`, `uk-ls--0d1em` | Numeric flexible |
| `ta` | text-align | `uk-ta--center`, `uk-ta--justify` | Keywords: left, center, right, justify |

### Colors (3 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `bg` | background-color | `uk-bg--007bff`, `uk-bg--ff0000-75` | 3/6-digit hex, optional alpha (0-100) |
| `tc` | color | `uk-tc--ffffff`, `uk-tc--333` | Text color with alpha support |
| `bc` | border-color | `uk-bc--dee2e6`, `uk-bc--f00-50` | Border color with alpha support |

**Pseudo-State Suffixes (Static/Head modes):**

All color prefixes (`bg`, `tc`, `bc`) support hover, focus, and active states
by appending `-h`, `-f`, or `-a` to the color value:

- `uk-bg--007bff-h` → `background-color: #007bff` on `:hover`
- `uk-bg--ff0000-75-f` → `background-color: rgba(255,0,0,0.75)` on `:focus`
- `uk-tc--ffffff-a` → `color: #ffffff` on `:active`
- `uk-bc--dee2e6-h` → `border-color: #dee2e6` on `:hover`

Combine with alpha: `uk-bg--007bff-50-h` = 50% opacity blue on hover.

### Layout & Display (4 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `dp` | display | `uk-dp--flex`, `uk-dp--grid`, `uk-dp--none` | All CSS display values |
| `ps` | position | `uk-ps--relative`, `uk-ps--sticky` | absolute, relative, fixed, sticky |
| `ov` | overflow | `uk-ov--hidden`, `uk-ov--auto` | visible, hidden, scroll, auto |
| `bz` | background-size | `uk-bz--cover`, `uk-bz--contain` | Keywords: cover, contain, auto |

### Positioning (4 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `tp` | inset-block-start (top) | `uk-tp--10`, `uk-tp--0` | Numeric flexible |
| `ri` | inset-inline-end (right) | `uk-ri--15` | Numeric flexible |
| `bt` | inset-block-end (bottom) | `uk-bt--5` | Numeric flexible |
| `lt` | inset-inline-start (left) | `uk-lt--12`, `uk-lt--auto` | Numeric flexible |

### Spacing & Layout (2 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `gp` | gap | `uk-gp--16`, `uk-gp--16-32` | Flex/Grid gap, row-column control |
| `ar` | aspect-ratio | `uk-ar--16-9`, `uk-ar--1-1` | Format: width-height |

### Flexbox (9 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `fd` | flex-direction | `uk-fd--column`, `uk-fd--row-reverse` | row, column, row-reverse, column-reverse |
| `jc` | justify-content | `uk-jc--center`, `uk-jc--between` | flex-start, flex-end, center, space-between, space-around, space-evenly |
| `ai` | align-items | `uk-ai--center`, `uk-ai--stretch` | flex-start, flex-end, center, stretch, baseline |
| `ac` | align-content | `uk-ac--center`, `uk-ac--between` | Same keywords as justify-content |
| `fx` | flex-wrap | `uk-fx--wrap`, `uk-fx--nowrap` | wrap, nowrap, wrap-reverse |
| `fg` | flex-grow | `uk-fg--1`, `uk-fg--0` | Decimal values |
| `fk` | flex-shrink | `uk-fk--0`, `uk-fk--1` | Decimal values |
| `fb` | flex-basis | `uk-fb--200`, `uk-fb--auto` | Numeric flexible |
| `or` | order | `uk-or--1`, `uk-or---1` | Integer values |

### CSS Grid (4 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `gc` | grid-template-columns | `uk-gc--repeat-3-1fr`, `uk-gc--200px-1fr` | Complex patterns (see below) |
| `gr` | grid-template-rows | `uk-gr--auto-1fr-auto` | Same syntax as gc |
| `gl` | grid-column | `uk-gl--1-3`, `uk-gl--2-4` | Format: start-end → start / end |
| `gw` | grid-row | `uk-gw--1-2` | Format: start-end → start / end |

**Supported Grid Patterns:**
- Simple values: `uk-gc--1fr-2fr-1fr`
- Repeat: `uk-gc--repeat-3-1fr`, `uk-gc--repeat-auto-fit-250px`
- Minmax: `uk-gc--minmax-100px-1fr`
- Repeat + Minmax: `uk-gc--repeat-auto-fit-minmax-250px-1fr`
- Fit-content: `uk-gc--fitc-200px` (alias for fit-content)
- Mixed: `uk-gc--200px-1fr-fitc-300px`
- Keywords: `min-content` (minc), `max-content` (maxc), `auto`

### Transform (2 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `rt` | rotate | `uk-rt--45`, `uk-rt--180`, `uk-rt--1d5` | Degrees with decimal support |
| `sc` | scale | `uk-sc--120`, `uk-sc--75` | Percentage-based (120 = 1.2x) |

### Effects (2 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `op` | opacity | `uk-op--75`, `uk-op--25` | 0-100 scale → 0-1 decimal |
| `zi` | z-index | `uk-zi--10`, `uk-zi--999` | Integer values |

### Additional Utilities (4 prefixes)

| Prefix | CSS Property | Example | Notes |
|--------|-------------|---------|-------|
| `cu` | cursor | `uk-cu--pointer`, `uk-cu--grab` | All CSS cursor keywords |
| `fl` | float | `uk-fl--left`, `uk-fl--right` | left, right, none |
| `cl` | clear | `uk-cl--both`, `uk-cl--left` | left, right, both, none |
| `us` | user-select | `uk-us--none`, `uk-us--text` | none, text, all, auto |

## Special Value Notations

### Decimal Notation (`d`)

Use `d` as a decimal point since `.` is not valid in CSS class names:

- `uk-lh--1d5` → `line-height: 1.5`
- `uk-mg--3d75rem` → `margin: 3.75rem`
- `uk-rt--45d5` → `rotate: 45.5deg`

### Percentage Notation (`pr`)

Use `pr` suffix for percentage values since `%` is not valid in class names:

- `uk-wd--100pr` → `width: 100%`
- `uk-ht--50pr` → `height: 50%`
- `uk-gc--minmax-50pr-2fr` → `minmax(50%, 2fr)`

### Color Formats

- **3-digit hex**: `uk-bg--f00` → `background-color: #f00`
- **6-digit hex**: `uk-bg--ff0000` → `background-color: #ff0000`
- **With alpha (0-100)**: `uk-bg--ff0000-50` → `background-color: rgba(255, 0, 0, 0.5)`
- **Short hex with alpha**: `uk-bg--f00-25` → `background-color: rgba(255, 0, 0, 0.25)`
- **Pseudo-state suffix** (static/head modes): append `-h` (hover), `-f` (focus),
  or `-a` (active) — e.g., `uk-bg--007bff-h`, `uk-tc--ff0000-50-f`

### Directional Sides

Box model properties support directional suffixes:

- `-t` (top), `-r` (right), `-b` (bottom), `-l` (left)
- `-x` (horizontal: left + right), `-y` (vertical: top + bottom)
- Example: `uk-pd--t-20` → `padding-top: 20px`
- Example: `uk-mg--x-auto` → `margin-left: auto; margin-right: auto`

## Responsive Design

### Breakpoint System

UtiliKit uses a mobile-first responsive approach:

| Breakpoint | Min Width | Example |
|------------|-----------|---------|
| (none) | 0px | `uk-pd--16` (all screens) |
| `sm` | ≥ 576px | `uk-sm-pd--20` |
| `md` | ≥ 768px | `uk-md-dp--flex` |
| `lg` | ≥ 992px | `uk-lg-gc--repeat-3-1fr` |
| `xl` | ≥ 1200px | `uk-xl-fs--24` |
| `xxl` | ≥ 1400px | `uk-xxl-pd--40` |

All 47 utility prefixes support responsive variants. Breakpoints can be
individually enabled/disabled in settings to reduce CSS size.

### Responsive Usage Examples

```html
<!-- Responsive padding that grows with screen size -->
<div class="utilikit uk-pd--12 uk-md-pd--24 uk-lg-pd--40">
  Adaptive spacing for all devices
</div>

<!-- Hide/show at different breakpoints -->
<div class="utilikit uk-dp--none uk-md-dp--block">
  Hidden on mobile, visible from medium screens up
</div>

<!-- Complex responsive layout -->
<div class="utilikit uk-dp--block uk-sm-dp--flex uk-lg-dp--grid uk-lg-gc--repeat-3-1fr">
  <!-- Mobile: stacked, Tablet: flex row, Desktop: 3-column grid -->
</div>
```

## CSS Specificity Control

### Optional !important Flag

In static and head modes, you can toggle `!important` on all utility classes:

- **Enabled (default)**: All utility classes include `!important` to override
  theme styles and inline styles
- **Disabled**: Utility classes use normal specificity, allowing custom CSS to
  override them
- Changing this setting automatically regenerates all CSS
- Matches Tailwind CSS recommended behavior

Configure at **Configuration > UtiliKit Settings > Performance**.

## Developer Tools

### Playground

Interactive testing environment at `/admin/config/utilikit/playground`:

- Live preview environment with instant visual feedback
- Preset examples for cards, heroes, forms, grids, and more
- Visual class builder with categorized utility chips
- Responsive testing with built-in viewport controls
- Color palette with copy-to-clipboard hex values
- Code export for clean HTML generation

### Reference Documentation

Complete utility class reference at `/admin/config/utilikit/reference`:

- Searchable class documentation organized by category
- Interactive examples with copy-to-clipboard
- Live preview of utility classes
- All 47 prefixes documented with examples

### Update Button

Floating action button for CSS regeneration (static/head modes):

- Appears automatically when UtiliKit elements are detected on the page
- One-click CSS file regeneration via AJAX
- Real-time progress feedback
- Automatic cache invalidation after update
- Requires `use utilikit update button` permission

### Debug Mode

Enable via developer settings (requires `administer utilikit` permission):

- Comprehensive browser console logging (off/warnings/detailed)
- Visual debugging with element outlines (admin_preview)
- Validation error display on pages (show_page_errors)
- Performance timing and operation profiling

## Drush Commands

```bash
# Generate or regenerate static CSS file
drush utilikit:generate           # aliases: uk-gen, utilikit-generate

# Scan content for utility classes
drush utilikit:scan               # aliases: uk-scan, utilikit-scan
drush utilikit:scan --entity-types=node,paragraph --batch-size=100

# Clear all CSS data and reset known classes
drush utilikit:clear              # aliases: uk-clear, utilikit-clear

# Switch rendering mode
drush utilikit:mode static        # aliases: uk-mode, utilikit-mode
drush utilikit:mode head
drush utilikit:mode inline

# Show status and configuration
drush utilikit:status             # aliases: uk-status, utilikit-status

# List all tracked utility classes
drush utilikit:classes             # aliases: uk-classes, utilikit-classes
drush utilikit:classes --limit=0 --filter=pd

# Validate CSS generation
drush utilikit:validate           # aliases: uk-validate, utilikit-validate

# Clear UtiliKit-specific caches
drush utilikit:cache-clear        # aliases: uk-cc, utilikit-cache-clear
```

## Configuration

### Admin Settings

Access configuration at **Configuration > UtiliKit Settings**
(`/admin/config/utilikit/settings`)

#### Rendering Mode
- **Inline Mode**: Dynamic JavaScript processing (development)
- **Static Mode**: Pre-generated CSS files (production)
- **Head Mode**: CSS in page head (cloud/containers)

#### Scope Settings
- **Global**: Enable on all pages
- **Content Types**: Limit to specific content types
- **Admin Pages**: Exclude admin routes for performance
- **Entity Types**: Configure which entity types to scan

#### Performance Settings
- **Active Breakpoints**: Enable/disable specific responsive breakpoints
- **CSS Optimization**: Minify generated CSS (static/head modes)
- **!important Flag**: Toggle CSS specificity control
- **Transitions**: Enable smooth CSS transitions
- **Debounce**: Resize event delay (0-500ms)
- **Cache TTL**: CSS cache duration

#### Auto-Update Triggers (Static/Head modes)
- **Node Save**: Regenerate CSS when nodes are saved
- **Block Save**: Regenerate CSS when custom blocks are saved
- **Paragraph Save**: Regenerate CSS when paragraphs are saved

#### Developer Settings (requires `administer utilikit` permission)
- **Development Mode**: Enable detailed logging and debugging
- **Admin Preview**: Visual debugging with element outlines
- **Error Display**: Show validation errors on page
- **Console Logging**: off, warnings, or detailed

#### Advanced Settings
- **Rate Limiting**: Requests per minute per IP (default: 60)
- **Max Classes Per Request**: Limit for AJAX updates (default: 5000)
- **Batch Size**: Content scanning batch size (default: 50)

#### Experimental Features
- Grid auto generation
- Advanced CSS selectors
- CSS custom properties
- Media query optimization

### Permissions

| Permission | Description | Restrict Access |
|-----------|-------------|-----------------|
| `administer utilikit` | Full access to settings, developer tools, cache management | Yes |
| `access utilikit reference` | View utility class reference and documentation | No |
| `use utilikit update button` | Manual CSS regeneration button and AJAX updates | No |

## Sub-Modules

Enable sub-modules as needed for additional functionality:

```bash
drush en utilikit_examples utilikit_playground utilikit_help utilikit_test
```

### UtiliKit Examples (`utilikit_examples`)

Real-world component examples at `/admin/config/utilikit/examples`:
- Pre-built components: cards, headers, forms, navigation
- Layout templates using UtiliKit classes
- Copy-paste ready HTML markup
- Best practice patterns

### UtiliKit Playground (`utilikit_playground`)

Interactive testing environment at `/admin/config/utilikit/playground`:
- Safe sandbox for experimenting with utility combinations
- Visual class builder interface
- Responsive preview across all breakpoints
- Preset templates for common UI patterns
- Code generation and export

### UtiliKit Help (`utilikit_help`)

Documentation system at `/admin/config/utilikit/help`:
- Contextual in-admin help with step-by-step guides
- Getting started tutorials
- Troubleshooting tools
- Quick reference for utility class syntax

### UtiliKit Test (`utilikit_test`)

Automated test suite at `/admin/config/utilikit/test`:
- Comprehensive validation of all 47 utility prefixes
- CSS output verification
- Performance benchmarks
- Regression testing

## Performance

### Optimization Features

- **Selective Loading**: Only includes utilities actually used on the page
- **Automatic Detection**: Scans content entities for utility classes
- **Smart Caching**: Multi-tier cache invalidation (render, page, CSS/JS assets)
- **Queue Processing**: Background CSS processing via cron queue worker
- **CSS Minification**: Optimized output in static/head modes
- **Minimal Runtime**: Lightweight JavaScript in static/head modes

### Best Practices

1. Use **Static** or **Head Mode** in production
2. Disable unused breakpoints to reduce CSS size
3. Enable CSS optimization (minification)
4. Enable `!important` for reliable specificity
5. Configure auto-update triggers only for entity types that use UtiliKit
6. Use `drush utilikit:generate` after bulk content changes

## Security

- **Input Validation**: Multi-layer validation prevents CSS injection
- **CSRF Protection**: All AJAX endpoints protected with cookie-based CSRF tokens
- **Rate Limiting**: Built-in rate limiting (60 req/min per IP, 5000 classes/request)
- **Permission-Based Access**: Granular role-based access control
- **Sanitized Output**: All generated CSS is validated before output
- **Content Security Policy**: Full CSP compliance

## Browser Support

UtiliKit supports all modern browsers:

- **Chrome**: 88+
- **Firefox**: 85+
- **Safari**: 14+
- **Edge**: 88+
- **Mobile**: iOS Safari 14+, Chrome Mobile 88+

### Required JavaScript Features

- ES6 Modules
- CSS.escape() API (with fallback)
- Performance API (graceful degradation)
- Fetch API for AJAX requests

## Drupal Integration

### Hooks Implemented

- `hook_page_attachments()` — Attaches CSS/JS based on rendering mode
- `hook_entity_presave()` — Triggers auto-update on content save
- `hook_library_info_alter()` — Injects dynamic CSS file URL for static mode
- `hook_css_alter()` — Adjusts CSS weight/group for proper cascade
- `hook_cache_flush()` — Regenerates static CSS during cache rebuilds
- `hook_theme()` — Defines update button template

### Module Compatibility

- Layout Builder
- Paragraphs module
- Views
- Media module
- CKEditor / WYSIWYG editors
- Any Drupal theme
- Multisite deployments

## Contributing

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Set up local Drupal site with UtiliKit enabled
4. Enable development mode in UtiliKit settings

### Coding Standards

- Follow Drupal coding standards for PHP
- Use ESLint configuration for JavaScript
- Maintain comprehensive JSDoc documentation
- Include tests for new features

### Submitting Issues

- Use the official Drupal.org issue queue
- Provide detailed reproduction steps
- Include browser and Drupal version information
- Attach relevant error logs or screenshots

## License

UtiliKit is distributed under the GPL-2.0-or-later license.

## Maintainers

This module is actively maintained by:

- **Primary Maintainer**: Alaa Haddad
- **Homepage**: https://alaahaddad.com
- **Documentation**: https://unitedstarsofamerica.com/utilikit/help
- **Reference**: https://unitedstarsofamerica.com/utilikit/reference
- **Demo**: https://unitedstarsofamerica.com/utilikit/examples

### Support

- **Issues**: https://www.drupal.org/project/issues/utilikit
- **Documentation**: Module help pages and admin interface
