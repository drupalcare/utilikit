/**
 * @file
 * Enhanced UtiliKit Playground functionality.
 *
 * Provides interactive playground features including preset examples,
 * visual class builder, color palette, viewport controls, responsive
 * helpers, and comprehensive style inspection tools.
 */

(function(Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Preset examples configuration.
   */
  const presetExamples = {
    card: {
      basic: 'uk-pd--32 uk-bg--ffffff uk-bg--f8fafc-h uk-br--16 uk-bs--solid uk-bw--1 uk-bc--e9ecef',
      html: `<div class="utilikit uk-pd--32 uk-bg--ffffff uk-bg--f8fafc-h uk-br--16 uk-bs--solid uk-bw--1 uk-bc--e9ecef">
  <h3 class="utilikit uk-fs--28 uk-fw--700 uk-tc--1a202c uk-tc--3b82f6-h uk-mg--b-16">Premium Features</h3>
  <p class="utilikit uk-tc--64748b uk-lh--1d8 uk-mg--b-24">Get access to advanced tools and analytics to supercharge your workflow.</p>
  <div class="utilikit uk-dp--flex uk-gp--12">
    <button class="utilikit uk-pd--12-24 uk-bg--3b82f6 uk-bg--2563eb-h uk-bg--1d4ed8-a uk-tc--ffffff uk-br--8 uk-bw--0 uk-fw--600 uk-cu--pointer">Get Started</button>
    <button class="utilikit uk-pd--12-24 uk-bg--ffffff uk-bg--eff6ff-h uk-tc--3b82f6 uk-tc--2563eb-h uk-br--8 uk-bs--solid uk-bw--2 uk-bc--3b82f6 uk-fw--600 uk-cu--pointer">Learn More</button>
  </div>
</div>`
    },

    hero: {
      basic: 'uk-pd--80-20 uk-bg--1e40af uk-bg--1e3a8a-h uk-tc--ffffff uk-ta--center',
      html: `<section class="utilikit uk-pd--80-20 uk-bg--1e40af uk-bg--1e3a8a-h uk-tc--ffffff uk-ta--center">
  <h1 class="utilikit uk-fs--56 uk-fw--800 uk-mg--b-24 uk-tc--ffffff uk-tc--e0e7ff-h">Welcome to UtiliKit</h1>
  <p class="utilikit uk-fs--24 uk-mg--b-32 uk-xw--800 uk-mg--r-auto uk-mg--l-auto uk-op--90">Build beautiful, responsive interfaces with utility-first CSS classes</p>
  <button class="utilikit uk-pd--16-40 uk-bg--ffffff uk-bg--e0e7ff-h uk-bg--c7d2fe-a uk-tc--1e40af uk-br--32 uk-fw--700 uk-fs--18 uk-cu--pointer">Start Building</button>
</section>`
    },

    flex: {
      basic: 'uk-dp--flex uk-gp--20 uk-fx--wrap',
      html: `<div class="utilikit uk-dp--flex uk-gp--20 uk-fx--wrap">
  <div class="utilikit uk-fg--1 uk-nw--300 uk-pd--20 uk-bg--f8f9fa uk-bg--e9ecef-h uk-br--8">
    <h3 class="utilikit uk-fs--18 uk-fw--600 uk-mg--b-12 uk-tc--212529 uk-tc--3b82f6-h">Column 1</h3>
    <p class="utilikit uk-tc--6c757d">Flexible column with minimum width</p>
  </div>
  <div class="utilikit uk-fg--1 uk-nw--300 uk-pd--20 uk-bg--e9ecef uk-bg--dee2e6-h uk-br--8">
    <h3 class="utilikit uk-fs--18 uk-fw--600 uk-mg--b-12 uk-tc--212529 uk-tc--10b981-h">Column 2</h3>
    <p class="utilikit uk-tc--6c757d">Equal width columns that wrap</p>
  </div>
  <div class="utilikit uk-fg--1 uk-nw--300 uk-pd--20 uk-bg--dee2e6 uk-bg--ced4da-h uk-br--8">
    <h3 class="utilikit uk-fs--18 uk-fw--600 uk-mg--b-12 uk-tc--212529 uk-tc--8b5cf6-h">Column 3</h3>
    <p class="utilikit uk-tc--6c757d">Responsive flex layout</p>
  </div>
</div>`
    },

    grid: {
      basic: 'uk-dp--grid uk-gc--repeat-auto-fit-minmax-280px-1fr uk-gp--24',
      html: `<div class="utilikit uk-dp--grid uk-gc--repeat-auto-fit-minmax-280px-1fr uk-gp--24">
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-bg--f8fafc-h uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--3b82f6 uk-tc--2563eb-h uk-mg--b-16">01</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Design System</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Consistent components and patterns for scalable interfaces.</p>
  </div>
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-bg--f0fdf4-h uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--10b981 uk-tc--059669-h uk-mg--b-16">02</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Responsive Grid</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Auto-adjusting layouts that adapt to any screen size.</p>
  </div>
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-bg--fffbeb-h uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--f59e0b uk-tc--d97706-h uk-mg--b-16">03</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Utility Classes</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Build complex layouts with simple, composable classes.</p>
  </div>
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-bg--f5f3ff-h uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--8b5cf6 uk-tc--7c3aed-h uk-mg--b-16">04</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Fast Development</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Ship faster with pre-built utilities and instant preview.</p>
  </div>
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-bg--fef2f2-h uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--ef4444 uk-tc--dc2626-h uk-mg--b-16">05</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Modern Stack</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Built for Drupal 11 with the latest web standards.</p>
  </div>
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-bg--ecfeff-h uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--06b6d4 uk-tc--0891b2-h uk-mg--b-16">06</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Zero Config</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Works out of the box with smart defaults and easy setup.</p>
  </div>
</div>`
    },

    form: {
      basic: 'uk-dp--flex uk-fd--column uk-gp--20 uk-xw--480 uk-mg--0-auto',
      html: `<div class="utilikit uk-dp--flex uk-fd--column uk-gp--20 uk-xw--480 uk-mg--0-auto uk-pd--32 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
  <h2 class="utilikit uk-fs--28 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Create Account</h2>
  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Full Name</label>
    <input type="text" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-bc--3b82f6-f uk-fs--16" placeholder="John Doe">
  </div>
  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Email Address</label>
    <input type="email" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-bc--3b82f6-f uk-fs--16" placeholder="john@example.com">
  </div>
  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Password</label>
    <input type="password" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-bc--3b82f6-f uk-fs--16" placeholder="********">
  </div>
  <button type="button" class="utilikit uk-pd--14-28 uk-bg--3b82f6 uk-bg--2563eb-h uk-bg--1d4ed8-a uk-tc--ffffff uk-br--8 uk-bw--0 uk-fw--600 uk-fs--16 uk-cu--pointer uk-mg--t-8">Create Account</button>
</div>`
    },

    pricing: {
      basic: 'uk-pd--32 uk-sm-pd--48 uk-bg--ffffff uk-bg--fafbfc-h uk-dp--grid uk-br--16 uk-ta--center uk-ps--relative uk-ov--hidden',
      html: `<div class="utilikit uk-dp--grid uk-pd--32 uk-sm-pd--48 uk-bg--ffffff uk-bg--fafbfc-h uk-br--16 uk-ta--center uk-ps--relative uk-ov--hidden">
  <div class="utilikit uk-ps--absolute uk-tp--20 uk-ri--20 uk-pd--8-16 uk-bg--ff6348 uk-bg--e74c3c-h uk-tc--ffffff uk-br--20 uk-fs--12 uk-fw--700 uk-rt--25">POPULAR</div>
  <h3 class="utilikit uk-fs--16 uk-fw--600 uk-tc--6c757d uk-mg--b-16 uk-ls--2" style="text-transform: uppercase;">Professional</h3>
  <div class="utilikit uk-mg--b-24">
    <span class="utilikit uk-fs--24 uk-fw--600 uk-tc--6c757d uk-va--top">$</span>
    <span class="utilikit uk-fs--56 uk-sm-fs--72 uk-fw--900 uk-tc--1a1a2e uk-lh--1">49</span>
    <span class="utilikit uk-fs--18 uk-tc--6c757d">/month</span>
  </div>
  <ul class="utilikit uk-dp--grid uk-mg--b-32 uk-ta--left uk-pd--0" style="list-style: none;">
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-tc--1a202c-h uk-fs--16">Unlimited projects</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-tc--1a202c-h uk-fs--16">Advanced analytics</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-tc--1a202c-h uk-fs--16">24/7 Priority support</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-tc--1a202c-h uk-fs--16">Custom integrations</li>
  </ul>
  <button class="utilikit uk-wd--100pr uk-pd--16-32 uk-tc--ffffff uk-bg--007bff uk-bg--0056b3-h uk-bg--003d80-a uk-bw--2 uk-br--8 uk-fw--700 uk-fs--16 uk-cu--pointer uk-pd--20 uk-bs--solid">Start Free Trial</button>
  <p class="utilikit uk-fs--14 uk-tc--6c757d uk-mg--t-16">No credit card required</p>
</div>`
    }
  };

  Drupal.behaviors.utilikitPlaygroundEnhanced = {
    attach: function(context, settings) {
      once('utilikit-playground-enhanced', '.utilikit-playground-wrapper', context).forEach(wrapper => {
        initPresetExamples(wrapper);
        initClassBuilder(wrapper);
        initColorPalette(wrapper);
        initViewportControls(wrapper);
        initResponsiveConverter(wrapper);
        initTutorial(wrapper);
        enhanceFormSubmit(wrapper);
        initResetEnhancements(wrapper);
      });
    }
  };

  /**
   * Switches to a specific tab programmatically.
   */
  function switchToTab(tabName) {
    const form = document.getElementById('utilikit-preview-form');
    if (!form) return;

    const tabs = form.querySelectorAll('.playground-tab[data-tab]');
    const tabContents = form.querySelectorAll('.playground-tab-content');

    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
    tabContents.forEach(c => {
      const isTarget = c.dataset.tabContent === tabName;
      c.style.display = isTarget ? 'block' : 'none';
      c.classList.toggle('active', isTarget);
    });
  }

  /**
   * Preset examples — auto-switch to correct tab.
   */
  function initPresetExamples(wrapper) {
    wrapper.querySelectorAll('.preset-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const preset = presetExamples[this.dataset.preset];
        if (!preset) return;

        wrapper.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const classInput = document.getElementById('utilikit-class-input');
        const htmlInput = document.getElementById('utilikit-html-input');

        if (preset.html && htmlInput) {
          switchToTab('html');
          htmlInput.value = preset.html;
        } else if (preset.basic && classInput) {
          switchToTab('classes');
          classInput.value = preset.basic;
        }

        document.getElementById('utilikit-preview-form').dispatchEvent(new Event('submit'));

        this.classList.add('success-flash');
        setTimeout(() => this.classList.remove('success-flash'), 500);
      });
    });
  }

  /**
   * Visual class builder chip handlers.
   */
  function initClassBuilder(wrapper) {
    const classInput = document.getElementById('utilikit-class-input');
    const selectedClasses = window.utilikitPlaygroundState?.selectedClasses || new Set();

    wrapper.querySelectorAll('.builder-chip').forEach(chip => {
      chip.addEventListener('click', function() {
        const className = this.dataset.class;

        if (this.classList.contains('active')) {
          this.classList.remove('active');
          selectedClasses.delete(className);
        } else {
          this.classList.add('active');
          selectedClasses.add(className);
        }

        classInput.value = Array.from(selectedClasses).join(' ');
        document.getElementById('utilikit-preview-form').dispatchEvent(new Event('submit'));
      });
    });
  }

  /**
   * Color palette swatch handlers.
   */
  function initColorPalette(wrapper) {
    const colorPicker = document.getElementById('utilikit-color-picker');
    const colorValue = document.getElementById('utilikit-color-value');

    wrapper.querySelectorAll('.color-swatch').forEach(swatch => {
      swatch.addEventListener('click', function() {
        const color = this.dataset.color;
        colorPicker.value = color;
        colorValue.value = color;

        wrapper.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
        this.classList.add('selected');

        const hexValue = color.replace('#', '');
        navigator.clipboard.writeText(hexValue).catch(() => {
          colorValue.select();
          document.execCommand('copy');
        });

        this.style.transform = 'scale(1.3)';
        setTimeout(() => this.style.transform = '', 200);
      });
    });
  }

  /**
   * Viewport control buttons.
   */
  function initViewportControls(wrapper) {
    const viewportBtns = wrapper.querySelectorAll('.viewport-btn');
    const previewFrame = document.getElementById('utilikit-preview-box');
    const sizeIndicator = wrapper.querySelector('.viewport-size-indicator');

    viewportBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const viewport = this.dataset.viewport;

        viewportBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        previewFrame.className = `preview-frame ${viewport} utilikit-preview-output`;

        const sizes = { mobile: '375px', tablet: '768px', desktop: '1200px' };
        sizeIndicator.textContent = sizes[viewport];
      });
    });
  }

  /**
   * Responsive class converter input handler.
   */
  function initResponsiveConverter(wrapper) {
    const responsiveInput = document.getElementById('responsive-input');
    const responsiveOutput = document.getElementById('responsive-output');

    responsiveInput?.addEventListener('input', (e) => {
      const value = e.target.value.trim();

      // Validate: only allow valid utility class characters.
      if (value.startsWith('uk-') && value.includes('--') && /^[a-zA-Z0-9_\-]+$/.test(value)) {
        const breakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];
        responsiveOutput.textContent = '';

        function createVariantRow(prefix, className) {
          const row = document.createElement('div');
          row.className = 'responsive-variant';
          row.dataset.class = className;
          const prefixSpan = document.createElement('span');
          prefixSpan.className = 'prefix';
          prefixSpan.textContent = prefix + ':';
          const classSpan = document.createElement('span');
          classSpan.className = 'class-name';
          classSpan.textContent = className;
          row.appendChild(prefixSpan);
          row.appendChild(classSpan);
          return row;
        }

        responsiveOutput.appendChild(createVariantRow('base', value));

        breakpoints.forEach(bp => {
          const responsiveClass = value.replace('uk-', 'uk-' + bp + '-');
          responsiveOutput.appendChild(createVariantRow(bp, responsiveClass));
        });

        responsiveOutput.querySelectorAll('.responsive-variant').forEach(variant => {
          variant.addEventListener('click', function() {
            const className = this.dataset.class;
            const classInput = document.getElementById('utilikit-class-input');

            const currentClasses = classInput.value.trim();
            classInput.value = currentClasses ? currentClasses + ' ' + className : className;

            // Switch to Classes tab so user sees the result
            switchToTab('classes');

            this.style.background = '#28a745';
            this.style.color = 'white';
            setTimeout(() => {
              this.style.background = '';
              this.style.color = '';
            }, 300);

            navigator.clipboard.writeText(className).catch(() => {});
          });
        });
      } else {
        responsiveOutput.innerHTML = '<p class="utilikit-description">Enter a valid UtiliKit class (e.g., uk-pd--20)</p>';
      }
    });
  }

  /**
   * Tutorial overlay.
   */
  function initTutorial(wrapper) {
    const overlay = document.getElementById('tutorial-overlay');
    const closeBtn = document.getElementById('tutorial-close');
    const startBtn = document.getElementById('tutorial-start');

    // Tutorial button in tab bar
    const tutorialBtn = wrapper.querySelector('[data-action="tutorial"]');
    tutorialBtn?.addEventListener('click', () => {
      overlay.classList.add('active');
    });

    // Show on first visit
    if (!localStorage.getItem('utilikit-tutorial-seen')) {
      setTimeout(() => overlay.classList.add('active'), 500);
    }

    const closeTutorial = () => {
      overlay.classList.remove('active');
      localStorage.setItem('utilikit-tutorial-seen', 'true');
    };

    closeBtn?.addEventListener('click', closeTutorial);
    startBtn?.addEventListener('click', closeTutorial);
    overlay?.addEventListener('click', (e) => {
      if (e.target === overlay) closeTutorial();
    });
  }

  /**
   * Style inspector update on form submit.
   */
  function enhanceFormSubmit(wrapper) {
    const form = document.getElementById('utilikit-preview-form');
    const renderedHtml = document.getElementById('utilikit-rendered-html');

    if (form) {
      form.addEventListener('submit', () => {
        setTimeout(() => {
          updateStyleInspector();

          const previewBox = document.getElementById('utilikit-preview-box');
          if (previewBox && renderedHtml) {
            const element = previewBox.querySelector('.utilikit, [class*="uk-"]');
            if (element) {
              renderedHtml.textContent = element.outerHTML;
            }
          }
        }, 100);
      });
    }
  }

  /**
   * Reset enhancements — clear builder state.
   */
  function initResetEnhancements(wrapper) {
    const resetBtn = document.getElementById('utilikit-reset-btn');
    if (!resetBtn) return;

    const stateManager = {
      selectedClasses: new Set(),
      autocompleteIndex: -1,
    };

    window.utilikitPlaygroundState = stateManager;

    resetBtn.addEventListener('click', function() {
      wrapper.querySelectorAll('.preset-btn').forEach(btn => {
        btn.classList.remove('active');
      });

      wrapper.querySelectorAll('.builder-chip').forEach(chip => {
        chip.classList.remove('active');
      });

      wrapper.querySelectorAll('.color-swatch').forEach(swatch => {
        swatch.classList.remove('selected');
      });

      stateManager.selectedClasses.clear();
      stateManager.autocompleteIndex = -1;
    });
  }

  /**
   * Updates the style inspector with computed styles.
   */
  function updateStyleInspector() {
    const preview = document.getElementById('utilikit-preview-box');
    const breakdown = document.getElementById('style-breakdown');

    if (!preview || !breakdown) return;

    const element = preview.querySelector('.utilikit, [class*="uk-"]');
    if (!element) return;

    const computed = window.getComputedStyle(element);
    const classes = Array.from(element.classList).filter(c => c.startsWith('uk-'));

    const styleGroups = {
      'Box Model': ['padding', 'margin', 'width', 'height'],
      'Typography': ['font-size', 'font-weight', 'line-height', 'color', 'text-align'],
      'Background': ['background-color', 'opacity'],
      'Border': ['border', 'border-radius'],
      'Layout': ['display', 'flex-direction', 'justify-content', 'align-items', 'gap']
    };

    let html = '<div class="class-breakdown">';
    html += '<div class="style-group-title">Applied Classes</div>';

    classes.forEach(cls => {
      const effect = getClassEffect(cls);
      html += `
        <div class="class-item">
          <span class="class-name">${cls}</span>
          <span class="class-arrow">&rarr;</span>
          <span class="class-effect">${effect}</span>
        </div>
      `;
    });
    html += '</div>';

    Object.entries(styleGroups).forEach(([group, properties]) => {
      const values = properties.map(prop => {
        const value = computed[prop];
        return value && value !== 'none' && value !== '0px' ? { prop, value } : null;
      }).filter(Boolean);

      if (values.length > 0) {
        html += `
          <div class="style-group">
            <div class="style-group-title">${group}</div>
            ${values.map(({ prop, value }) => `
              <div class="style-item">
                <span class="style-property">${prop}:</span>
                <span class="style-value">${value}</span>
              </div>
            `).join('')}
          </div>
        `;
      }
    });

    breakdown.innerHTML = html;
  }

  /**
   * Converts a class name to a readable CSS effect description.
   */
  function getClassEffect(className) {
    const parts = className.split('--');
    if (parts.length !== 2) return 'Custom style';

    const [prefix, value] = parts;
    const prop = getPropertyName(className);

    // Detect pseudo-state suffix (-h, -f, -a) on color values.
    const pseudoStateMap = { h: 'on hover', f: 'on focus', a: 'on active' };
    const stateMatch = value.match(/-(h|f|a)$/);
    const stateSuffix = stateMatch ? ` (${pseudoStateMap[stateMatch[1]]})` : '';

    if (value === 'auto') return `${prop}: auto`;

    // Which value grammar applies is decided by the prefix, not the value.
    // Without this, every value fell through to the numeric branch below and
    // was given a 'px' suffix — so a colour read as "1e40afpx" and a keyword
    // as "gridpx".
    const prefixMatch = className.match(/^uk-(?:[a-z]+-)?([a-z]+)--/);
    const key = prefixMatch ? prefixMatch[1] : '';
    const COLOR_PREFIXES = ['bg', 'tc', 'bc'];
    const KEYWORD_PREFIXES = [
      'ac', 'ai', 'bs', 'bz', 'cl', 'cu', 'dp', 'fd',
      'fl', 'fx', 'jc', 'ov', 'ps', 'ta', 'us',
    ];
    const UNITLESS_PREFIXES = [
      'ar', 'fg', 'fk', 'fw', 'gc', 'gl', 'gr', 'gw', 'or', 'sc', 'zi',
    ];

    // Colours: "<hex>[-<alpha 0-100>][-h|f|a]".
    if (COLOR_PREFIXES.includes(key)) {
      const bare = value.replace(/-(h|f|a)$/, '');
      const colorMatch = bare.match(/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6})(?:-(\d{1,3}))?$/);
      if (colorMatch) {
        const hex = `#${colorMatch[1]}`;
        const alpha = colorMatch[2] !== undefined
          ? ` at ${Math.min(parseInt(colorMatch[2], 10), 100)}% opacity`
          : '';
        return `${prop}: ${hex}${alpha}${stateSuffix}`;
      }
      return `${prop}: ${bare}${stateSuffix}`;
    }

    // Keywords take the value verbatim — no unit.
    if (KEYWORD_PREFIXES.includes(key)) {
      return `${prop}: ${value.replace(/-/g, ' ')}`;
    }

    // Opacity is authored 0-100 and emitted as a 0-1 decimal.
    if (key === 'op') {
      const n = parseInt(value, 10);
      return Number.isNaN(n) ? `${prop}: ${value}` : `${prop}: ${Math.min(n, 100) / 100}`;
    }

    // Per-side values: "t|r|b|l-<value>".
    if (value.includes('-')) {
      const subParts = value.split('-');
      if (['t', 'r', 'b', 'l'].includes(subParts[0])) {
        const sideMap = { t: 'block-start', r: 'inline-end', b: 'block-end', l: 'inline-start' };
        const sideValue = subParts.slice(1).join('-');
        return `${prop}-${sideMap[subParts[0]]}: ${formatNumeric(sideValue, key)}`;
      }
      // Otherwise it is CSS shorthand: "10-24" means "10px 24px".
      const shorthand = subParts.map((part) => formatNumeric(part, key));
      if (shorthand.every((part) => part !== null)) {
        return `${prop}: ${shorthand.join(' ')}`;
      }
    }

    // Unitless numbers.
    if (UNITLESS_PREFIXES.includes(key)) {
      return `${prop}: ${value.replace(/d/g, '.')}`;
    }

    return `${prop}: ${formatNumeric(value.replace(/-(h|f|a)$/, ''), key)}${stateSuffix}`;
  }

  /**
   * Formats a numeric utility value the way the CSS generator does.
   *
   * 'd' is the decimal separator, 'pr' means percent, and px is the default
   * unit when none is given.
   */
  function formatNumeric(raw, prefix) {
    if (raw === 'auto') return 'auto';
    const match = raw.match(/^(\d+(?:d\d+)?)(px|pr|em|rem|vh|vw)?$/);
    if (!match) return raw;
    const number = match[1].replace(/d/g, '.');

    // line-height with no explicit unit and a value of 10 or less is emitted
    // unitless by the CSS generator.
    if (prefix === 'lh' && !match[2] && parseFloat(number) <= 10) {
      return number;
    }

    const unit = match[2] === 'pr' ? '%' : (match[2] || 'px');
    return `${number}${unit}`;
  }

  /**
   * Gets property name from class name.
   */
  function getPropertyName(className) {
    const prefixMap = {
      pd: 'padding', mg: 'margin', bw: 'border-width', br: 'border-radius',
      bs: 'border-style', bc: 'border-color', bg: 'background-color',
      tc: 'color', wd: 'inline-size', ht: 'block-size',
      xw: 'max-inline-size', nw: 'min-inline-size',
      xh: 'max-block-size', nh: 'min-block-size',
      tp: 'inset-block-start', lt: 'inset-inline-start',
      ri: 'inset-inline-end', bt: 'inset-block-end', ps: 'position',
      fs: 'font-size', lh: 'line-height', fw: 'font-weight',
      ls: 'letter-spacing', ta: 'text-align', op: 'opacity', zi: 'z-index',
      dp: 'display', ov: 'overflow', cu: 'cursor', fl: 'float',
      cl: 'clear', us: 'user-select', fd: 'flex-direction',
      jc: 'justify-content', ai: 'align-items', ac: 'align-content',
      fx: 'flex-wrap', fg: 'flex-grow', fk: 'flex-shrink',
      fb: 'flex-basis', or: 'order', gc: 'grid-columns',
      gr: 'grid-rows', gl: 'grid-column', gw: 'grid-row',
      gp: 'gap', rt: 'rotate', sc: 'scale', ar: 'aspect-ratio',
      bz: 'background-size', va: 'vertical-align',
    };

    const match = className.match(/^uk-(?:[a-z]+-)?([a-z]+)--/);
    if (!match) return '';
    return prefixMap[match[1]] || match[1];
  }

})(Drupal, once, drupalSettings);
