# UtiliKit - Utility-First CSS Framework for Drupal

UtiliKit is a comprehensive Drupal 11 contrib module that provides a powerful 
utility-first CSS system directly integrated into your Drupal project. It 
offers an efficient class generation engine for rapidly applying box model, 
spacing, flexbox, grid, color, effects, positioning, and layout utilities using 
a clean 2-letter prefix system.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Rendering Modes](#rendering-modes)
- [Utility Class Reference](#utility-class-reference)
- [Responsive Design](#responsive-design)
- [Developer Tools](#developer-tools)
- [Configuration](#configuration)
- [Performance](#performance)
- [Browser Support](#browser-support)
- [Contributing](#contributing)
- [License](#license)
- [Maintainers](#maintainers)

## Features

### Smart Rendering Modes
- **Inline Mode**: JavaScript applies styles dynamically (development-friendly)
- **Static Mode**: Pre-generated CSS files (production-optimized)

### Comprehensive Utility System
- Clean 2-letter prefix system for consistent rule expansion
- Full coverage of CSS properties: box model, typography, colors, layout
- Responsive breakpoint support with mobile-first approach
- Transform and positioning utilities with decimal precision
- CSS Grid and Flexbox utilities for modern layouts

### Developer Experience
- **Playground**: Interactive testing environment with live preview
- **Reference Documentation**: Complete class documentation with examples
- **Update Button**: Instant CSS regeneration during development
- **Debug Tools**: Comprehensive logging and performance monitoring

### Drupal Integration
- Permission-based feature access
- Entity save triggers for automatic CSS updates
- Admin UI for configuration management
- Compatible with Layout Builder and all themes
- No external dependencies required

### Performance Optimization
- Only loads utilities that are actually used
- Automatic class detection and scanning
- Smart caching system with cache invalidation
- Minimal runtime overhead in both modes

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

1. Navigate to **Configuration > UtiliKit Settings** (`/admin/config/utilikit`)
2. Configure rendering mode and scope settings
3. Set up permissions for development tools if needed
4. Test the installation using the Playground tool

## Quick Start

### Basic Usage

Add the `utilikit` base class to any element, then apply utility classes:

```html
<!-- Padded container with background color -->
<div class="utilikit uk-pd--16 uk-bg--primary uk-tc--white">
  Content with padding, background, and text color
</div>

<!-- Responsive layout -->
<div class="utilikit uk-dp--block uk-md-dp--flex uk-jc--between">
  <div class="utilikit uk-fg--1">Flexible item</div>
  <div class="utilikit uk-wd--200">Fixed width item</div>
</div>

<!-- CSS Grid layout -->
<div class="utilikit uk-dp--grid uk-gc--repeat-3-1fr uk-gp--16">
  <div class="utilikit uk-pd--12 uk-bg--secondary">Grid item 1</div>
  <div class="utilikit uk-pd--12 uk-bg--secondary">Grid item 2</div>
  <div class="utilikit uk-pd--12 uk-bg--secondary">Grid item 3</div>
</div>
```

### Class Naming Convention

UtiliKit uses a consistent naming pattern: `uk-{breakpoint}-{prefix}--{value}`

- `uk-`: UtiliKit namespace prefix
- `{breakpoint}`: Optional responsive breakpoint (sm, md, lg, xl, xxl)
- `{prefix}`: 2-letter property identifier
- `--`: Separator between prefix and value
- `{value}`: CSS value (supports units, keywords, and special notation)

## Rendering Modes

### Inline Mode (Development)

- JavaScript processes classes dynamically
- Immediate visual feedback
- Ideal for development and prototyping
- No CSS file generation required
- Real-time class validation and error reporting

### Static Mode (Production)

- Pre-generates CSS files for optimal performance
- Reduced runtime JavaScript overhead
- Better for production environments
- Supports CSS optimization and minification
- Requires manual or automatic CSS updates

## Utility Class Reference

### Box Model Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Padding | `uk-pd` | `uk-pd--16` | `uk-md-pd--20` | Supports all sides (t,r,b,l) and shorthand |
| Margin | `uk-mg` | `uk-mg--auto` | `uk-lg-mg--24` | Supports auto value and all sides |
| Border Width | `uk-bw` | `uk-bw--2` | `uk-sm-bw--1` | Individual sides supported |
| Border Radius | `uk-br` | `uk-br--8` | `uk-xl-br--12` | Individual corners supported |
| Border Style | `uk-bs` | `uk-bs--solid` | `uk-md-bs--dashed` | Keywords: solid, dashed, dotted |

### Sizing Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Width | `uk-wd` | `uk-wd--200` | `uk-md-wd--300` | Supports auto, %, px, rem, em |
| Height | `uk-ht` | `uk-ht--100` | `uk-lg-ht--50vh` | Viewport units allowed |
| Max Width | `uk-xw` | `uk-xw--400` | `uk-xl-xw--600` | Constraint utilities |
| Min Width | `uk-nw` | `uk-nw--100` | `uk-sm-nw--150` | Minimum size constraints |
| Max Height | `uk-xh` | `uk-xh--300` | `uk-md-xh--400` | Height constraints |
| Min Height | `uk-nh` | `uk-nh--50` | `uk-lg-nh--75` | Minimum height |

### Typography Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Font Size | `uk-fs` | `uk-fs--16` | `uk-md-fs--18` | px, rem, em units |
| Line Height | `uk-lh` | `uk-lh--1d5` | `uk-lg-lh--2` | Unitless or with units |
| Font Weight | `uk-fw` | `uk-fw--700` | `uk-md-fw--400` | Numeric values |
| Letter Spacing | `uk-ls` | `uk-ls--1` | `uk-sm-ls--2` | Spacing adjustments |
| Text Align | `uk-ta` | `uk-ta--center` | `uk-md-ta--left` | Keywords: center, left, right |

### Color Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Background Color | `uk-bg` | `uk-bg--primary` | `uk-md-bg--ff0000` | Named colors or hex values |
| Text Color | `uk-tc` | `uk-tc--white` | `uk-lg-tc--333333` | Supports alpha transparency |
| Border Color | `uk-bc` | `uk-bc--red` | `uk-sm-bc--00ff00-80` | Colors with optional alpha |

### Layout Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Display | `uk-dp` | `uk-dp--flex` | `uk-md-dp--grid` | All CSS display values |
| Position | `uk-ps` | `uk-ps--relative` | `uk-lg-ps--absolute` | Positioning contexts |
| Overflow | `uk-ov` | `uk-ov--hidden` | `uk-md-ov--scroll` | Overflow behaviors |
| Gap | `uk-gp` | `uk-gp--16` | `uk-xl-gp--24` | Grid and flex gap |
| Aspect Ratio | `uk-ar` | `uk-ar--16-9` | `uk-md-ar--4-3` | Ratio maintenance |

### Flexbox Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Flex Direction | `uk-fd` | `uk-fd--column` | `uk-md-fd--row` | Direction control |
| Justify Content | `uk-jc` | `uk-jc--center` | `uk-lg-jc--between` | Main axis alignment |
| Align Items | `uk-ai` | `uk-ai--center` | `uk-sm-ai--start` | Cross axis alignment |
| Flex Grow | `uk-fg` | `uk-fg--1` | `uk-md-fg--2` | Growth factors |
| Flex Shrink | `uk-fk` | `uk-fk--0` | `uk-lg-fk--1` | Shrink factors |
| Flex Basis | `uk-fb` | `uk-fb--200` | `uk-xl-fb--auto` | Basis sizing |

### Grid Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Grid Template Columns | `uk-gc` | `uk-gc--repeat-3-1fr` | `uk-md-gc--200px-1fr` | Complex grid definitions |
| Grid Template Rows | `uk-gr` | `uk-gr--auto-1fr` | `uk-lg-gr--repeat-4-1fr` | Row templates |
| Grid Column | `uk-gl` | `uk-gl--1-3` | `uk-md-gl--2-4` | Column span positioning |
| Grid Row | `uk-gw` | `uk-gw--1-2` | `uk-sm-gw--2-3` | Row span positioning |

### Transform Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Rotate | `uk-rt` | `uk-rt--45` | `uk-md-rt--90` | Degree rotation |
| Scale | `uk-sc` | `uk-sc--150` | `uk-lg-sc--200` | Scale transforms (percentage format)|

### Positioning Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Top | `uk-tp` | `uk-tp--10` | `uk-md-tp--20` | Offset positioning |
| Right | `uk-ri` | `uk-ri--15` | `uk-lg-ri--25` | Right positioning |
| Bottom | `uk-bt` | `uk-bt--5` | `uk-sm-bt--10` | Bottom positioning |
| Left | `uk-lt` | `uk-lt--12` | `uk-xl-lt--24` | Left positioning |

### Effects Utilities

| Property | Prefix | Example | Responsive | Notes |
|----------|--------|---------|------------|-------|
| Opacity | `uk-op` | `uk-op--50` | `uk-md-op--75` | 0-100 scale (converts to 0-1) |
| Z-Index | `uk-zi` | `uk-zi--10` | `uk-lg-zi--999` | Stacking context |

## Responsive Design

### Breakpoint System

UtiliKit uses a mobile-first responsive approach with the following breakpoints:

- `uk-sm`: ≥ 576px (Small devices and up)
- `uk-md`: ≥ 768px (Medium devices and up)
- `uk-lg`: ≥ 992px (Large devices and up)
- `uk-xl`: ≥ 1200px (Extra large devices and up)
- `uk-xxl`: ≥ 1400px (Extra extra large devices and up)

### Responsive Usage Examples

```html
<!-- Mobile-first responsive design -->
<div class="utilikit uk-pd--8 uk-md-pd--16 uk-lg-pd--24">
  Padding increases at larger breakpoints
</div>

<!-- Hide/show at different breakpoints -->
<div class="utilikit uk-dp--none uk-md-dp--block">
  Hidden on mobile, visible from medium screens up
</div>

<!-- Responsive layout changes -->
<div class="utilikit uk-dp--block uk-lg-dp--flex uk-jc--between">
  Stacked on mobile, flexbox layout on large screens
</div>
```

## Developer Tools

### Playground

Interactive testing environment accessible at 
`/admin/config/utilikit/playground`

- **Basic Mode**: Test individual utility classes
- **Advanced Mode**: Test complete HTML markup with UtiliKit classes
- Live preview with instant feedback
- Copy generated code for use in projects
- Responsive testing across all breakpoints

### Reference Documentation

Complete utility class reference at `/admin/config/utilikit/reference`

- Searchable class documentation
- Interactive examples with copy-to-clipboard
- Organized by utility categories
- Responsive preview capabilities

### Update Button

Development tool for CSS regeneration in static mode

- Appears automatically when UtiliKit elements are detected
- One-click CSS file regeneration
- Real-time progress feedback
- Automatic cache invalidation

## Configuration

### Admin Settings

Access configuration at **Configuration > UtiliKit Settings** 
(`/admin/config/utilikit`)

#### Rendering Mode
- **Inline Mode**: Dynamic JavaScript processing
- **Static Mode**: Pre-generated CSS files

#### Scope Settings
- **Global**: Enable on all pages
- **Content Types**: Limit to specific content types
- **Admin Pages**: Control admin interface inclusion

#### Performance Settings
- **Active Breakpoints**: Enable/disable specific responsive breakpoints
- **CSS Optimization**: Minify generated CSS in static mode
- **Cache Management**: Control CSS cache behavior

#### Developer Settings
- **Development Mode**: Enable detailed logging and debugging
- **Admin Preview**: Visual debugging with element outlines
- **Error Display**: Show validation errors on page
- **Console Logging**: Configure browser console output levels

### Permissions

- **Access UtiliKit tools**: View playground and reference pages
- **Administer UtiliKit**: Access settings and advanced tools

## Performance

### Optimization Features

- **Selective Loading**: Only includes utilities that are actually used
- **Automatic Detection**: Scans content for utility classes
- **Smart Caching**: Intelligent cache management with invalidation
- **Minimal Runtime**: Lightweight JavaScript with optimized execution

### Performance Best Practices

1. **Use Static Mode in Production**: Better performance with pre-generated CSS
2. **Disable Unused Breakpoints**: Reduce CSS size by disabling unnecessary 
breakpoints
3. **Enable CSS Optimization**: Minify and optimize generated CSS files
4. **Monitor Class Usage**: Use developer tools to track utility usage patterns

### Performance Monitoring

When development mode is enabled:
- Operation timing with microsecond precision
- CSS generation performance tracking
- DOM manipulation profiling
- Memory usage monitoring

## Browser Support

UtiliKit supports all modern browsers:

- **Chrome**: 88+
- **Firefox**: 85+
- **Safari**: 14+
- **Edge**: 88+
- **Mobile Browsers**: iOS Safari 14+, Chrome Mobile 88+

### Required JavaScript Features

- ES6 Modules support
- CSS.escape() API (with fallback)
- Performance API (with graceful degradation)
- Fetch API for AJAX requests

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
- Include unit tests for new features

### Submitting Issues

- Use the official Drupal.org issue queue
- Provide detailed reproduction steps
- Include browser and Drupal version information
- Attach relevant error logs or screenshots

### Feature Requests

Submit feature requests through the Drupal.org project page with:
- Clear use case description
- Expected behavior specification
- Potential implementation approach

## License

UtiliKit is distributed under the GPL-2.0-or-later license.

## Maintainers

This module is actively maintained by:

- **Primary Maintainer**: Alaa Haddad
- **Homepage**: https://alaahaddad.com
- **Documentation**: https://unitedstarsofamerica.com/utilikit/help
- **Reference**: https://unitedstarsofamerica.com/utilikit/reference
- **Demo**: https://unitedstarsofamerica.com/utilikit/examples

### Support Channels

- **Issues**: https://www.drupal.org/project/issues/utilikit
- **Documentation**: Module help pages and admin interface
- **Community**: Drupal Slack #utilikit channel

### Project Status

- **Lifecycle**: Stable
- **Drupal Compatibility**: ^10 || ^11
- **Version**: 1.0.0
- **Last Updated**: 2025

---

For detailed examples, advanced usage patterns, and troubleshooting guides, 
visit the complete documentation at the project homepage or use the built-in 
help system within your Drupal admin interface.
