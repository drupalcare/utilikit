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
   * Preset examples configuration object.
   *
   * Contains predefined HTML and class combinations for common UI patterns
   * like cards, hero sections, forms, grids, and pricing components.
   *
   * @type {Object.<string, Object>}
   */
  const presetExamples = {
    card: {
      basic: 'uk-pd--32 uk-bg--ffffff uk-br--16 uk-bs--solid uk-bw--1 uk-bc--e9ecef',
      html: `<div class="utilikit uk-pd--32 uk-bg--ffffff uk-br--16 uk-bs--solid uk-bw--1 uk-bc--e9ecef">
  <h3 class="utilikit uk-fs--28 uk-fw--700 uk-tc--1a202c uk-mg--b-16">Premium Features</h3>
  <p class="utilikit uk-tc--64748b uk-lh--1d8 uk-mg--b-24">Get access to advanced tools and analytics to supercharge your workflow.</p>
  <div class="utilikit uk-dp--flex uk-gp--12">
    <button class="utilikit uk-pd--12-24 uk-bg--3b82f6 uk-tc--ffffff uk-br--8 uk-bw--0 uk-fw--600 uk-cu--pointer">Get Started</button>
    <button class="utilikit uk-pd--12-24 uk-bg--ffffff uk-tc--3b82f6 uk-br--8 uk-bs--solid uk-bw--2 uk-bc--3b82f6 uk-fw--600 uk-cu--pointer">Learn More</button>
  </div>
</div>`
    },

    hero: {
      basic: 'uk-pd--80-20 uk-bg--1e40af uk-tc--ffffff uk-ta--center',
      html: `<section class="utilikit uk-pd--80-20 uk-bg--1e40af uk-tc--ffffff uk-ta--center">
  <h1 class="utilikit uk-fs--56 uk-fw--800 uk-mg--b-24">Welcome to UtiliKit</h1>
  <p class="utilikit uk-fs--24 uk-mg--b-32 uk-xw--800 uk-mg--r-auto uk-mg--l-auto uk-op--90">Build beautiful, responsive interfaces with utility-first CSS classes</p>
  <button class="utilikit uk-pd--16-40 uk-bg--ffffff uk-tc--1e40af uk-br--32 uk-fw--700 uk-fs--18 uk-cu--pointer">Start Building →</button>
</section>`
    },

    flex: {
      basic: 'uk-dp--flex uk-gp--20 uk-fx--wrap',
      html: `<div class="utilikit uk-dp--flex uk-gp--20 uk-fx--wrap">
  <div class="utilikit uk-fg--1 uk-nw--300 uk-pd--20 uk-bg--f8f9fa uk-br--8">
    <h3 class="utilikit uk-fs--18 uk-fw--600 uk-mg--b-12">Column 1</h3>
    <p class="utilikit uk-tc--6c757d">Flexible column with minimum width</p>
  </div>
  <div class="utilikit uk-fg--1 uk-nw--300 uk-pd--20 uk-bg--e9ecef uk-br--8">
    <h3 class="utilikit uk-fs--18 uk-fw--600 uk-mg--b-12">Column 2</h3>
    <p class="utilikit uk-tc--6c757d">Equal width columns that wrap</p>
  </div>
  <div class="utilikit uk-fg--1 uk-nw--300 uk-pd--20 uk-bg--dee2e6 uk-br--8">
    <h3 class="utilikit uk-fs--18 uk-fw--600 uk-mg--b-12">Column 3</h3>
    <p class="utilikit uk-tc--6c757d">Responsive flex layout</p>
  </div>
</div>`
    },

    grid: {
      basic: 'uk-dp--grid uk-gc--repeat-auto-fit-minmax-280px-1fr uk-gp--24',
      html: `<div class="utilikit uk-dp--grid uk-gc--repeat-auto-fit-minmax-280px-1fr uk-gp--24">
  <!-- Card 1 -->
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--3b82f6 uk-mg--b-16">01</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Design System</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Consistent components and patterns for scalable interfaces.</p>
  </div>

  <!-- Card 2 -->
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--10b981 uk-mg--b-16">02</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Responsive Grid</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Auto-adjusting layouts that adapt to any screen size.</p>
  </div>

  <!-- Card 3 -->
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--f59e0b uk-mg--b-16">03</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Utility Classes</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Build complex layouts with simple, composable classes.</p>
  </div>

  <!-- Card 4 -->
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--8b5cf6 uk-mg--b-16">04</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Fast Development</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Ship faster with pre-built utilities and instant preview.</p>
  </div>

  <!-- Card 5 -->
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--ef4444 uk-mg--b-16">05</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Modern Stack</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Built for Drupal 11 with the latest web standards.</p>
  </div>

  <!-- Card 6 -->
  <div class="utilikit uk-pd--24 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
    <div class="utilikit uk-fs--48 uk-fw--700 uk-tc--06b6d4 uk-mg--b-16">06</div>
    <h3 class="utilikit uk-fs--20 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Zero Config</h3>
    <p class="utilikit uk-tc--64748b uk-lh--1d6">Works out of the box with smart defaults and easy setup.</p>
  </div>
</div>`
    },

    form: {
      basic: 'uk-dp--flex uk-fd--column uk-gp--20 uk-xw--480 uk-mg--0-auto',
      html: `<form class="utilikit uk-dp--flex uk-fd--column uk-gp--20 uk-xw--480 uk-mg--0-auto uk-pd--32 uk-bg--ffffff uk-br--12 uk-bs--solid uk-bw--1 uk-bc--e2e8f0">
  <h2 class="utilikit uk-fs--28 uk-fw--700 uk-tc--1a202c uk-mg--b-8">Create Account</h2>

  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Full Name</label>
    <input type="text" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-fs--16" placeholder="John Doe">
  </div>

  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Email Address</label>
    <input type="email" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-fs--16" placeholder="john@example.com">
  </div>

  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Phone Number</label>
    <input type="tel" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-fs--16" placeholder="+1 (555) 123-4567">
  </div>

  <div class="utilikit uk-dp--grid">
    <label class="utilikit uk-dp--inline-block uk-mg--b-8 uk-fw--600 uk-fs--14 uk-tc--475569">Password</label>
    <input type="password" class="utilikit uk-wd--100pr uk-pd--12-16 uk-br--8 uk-bs--solid uk-bw--1 uk-bc--cbd5e1 uk-fs--16" placeholder="••••••••">
  </div>

  <button type="submit" class="utilikit uk-pd--14-28 uk-bg--3b82f6 uk-tc--ffffff uk-br--8 uk-bw--0 uk-fw--600 uk-fs--16 uk-cu--pointer uk-mg--t-8">Create Account</button>
</form>`
    },

    pricing: {
      basic: 'uk-pd--32 uk-sm-pd--48 uk-bg--ffffff uk-dp--grid uk-br--16 uk-ta--center uk-ps--relative uk-ov--hidden',
      html: `<div class="utilikit uk-dp--grid uk-pd--32 uk-sm-pd--48 uk-bg--ffffff uk-br--16 uk-ta--center uk-ps--relative uk-ov--hidden">
  <div class="utilikit uk-ps--absolute uk-tp--20 uk-ri--20 uk-pd--8-16 uk-bg--ff6348 uk-tc--ffffff uk-br--20 uk-fs--12 uk-fw--700 uk-rt--25">POPULAR</div>

  <h3 class="utilikit uk-fs--16 uk-fw--600 uk-tc--6c757d uk-mg--b-16 uk-ls--2" style="text-transform: uppercase;">Professional</h3>

  <div class="utilikit uk-mg--b-24">
    <span class="utilikit uk-fs--24 uk-fw--600 uk-tc--6c757d uk-va--top">$</span>
    <span class="utilikit uk-fs--56 uk-sm-fs--72 uk-fw--900 uk-tc--1a1a2e uk-lh--1">49</span>
    <span class="utilikit uk-fs--18 uk-tc--6c757d">/month</span>
  </div>

  <ul class="utilikit uk-dp--grid uk-mg--b-32 uk-ta--left uk-pd--0" style="list-style: none;">
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-fs--16">✓ Unlimited projects</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-fs--16">✓ Advanced analytics</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-fs--16">✓ 24/7 Priority support</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-fs--16">✓ Custom integrations</li>
    <li class="utilikit uk-pd--12-0 uk-tc--495057 uk-fs--16">✓ White-label options</li>
  </ul>

  <button class="utilikit uk-wd--100pr uk-pd--16-32 uk-tc--ffffff uk-bg--007bff uk-bw--2 uk-br--8 uk-fw--700 uk-fs--16 uk-cu--pointer uk-pd--20 uk-bs--solid">
    Start Free Trial
  </button>

  <p class="utilikit uk-fs--14 uk-tc--6c757d uk-mg--t-16">No credit card required</p>
</div>`
    }
  };

  /**
   * Enhanced UtiliKit Playground Drupal behavior.
   *
   * Initializes all interactive playground features including preset examples,
   * visual class builder, color palette, viewport controls, and more.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches enhanced playground functionality to playground wrapper elements.
   */
  Drupal.behaviors.utilikitPlaygroundEnhanced = {
    attach: function(context, settings) {
      once('utilikit-playground-enhanced', '.utilikit-playground-wrapper', context).forEach(wrapper => {
        // Initialize all enhancements
        initPresetExamples(wrapper);
        initClassBuilder(wrapper);
        initColorPalette(wrapper);
        initViewportControls(wrapper);
        initQuickActions(wrapper);
        initTutorial();
        enhanceFormSubmit(wrapper);
        initResetEnhancements(wrapper);
      });
    }
  };

  /**
   * Initializes preset example functionality.
   *
   * Sets up click handlers for preset buttons that load predefined HTML
   * and class combinations into the playground form.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing preset buttons.
   */
  function initPresetExamples(wrapper) {
    wrapper.querySelectorAll('.preset-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const preset = presetExamples[this.dataset.preset];
        if (!preset) return;

        // Remove active class from all preset buttons
        wrapper.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));

        // Add active class to clicked button
        this.classList.add('active');

        const modeSelect = document.getElementById('utilikit-mode');
        const classInput = document.getElementById('utilikit-class-input');
        const htmlInput = document.getElementById('utilikit-html-input');

        if (preset.html && htmlInput) {
          modeSelect.value = 'advanced';
          modeSelect.dispatchEvent(new Event('change'));
          htmlInput.value = preset.html;
        } else if (preset.basic && classInput) {
          modeSelect.value = 'basic';
          modeSelect.dispatchEvent(new Event('change'));
          classInput.value = preset.basic;
        }

        // Submit form
        document.getElementById('utilikit-preview-form').dispatchEvent(new Event('submit'));

        // Keep the success flash for visual feedback
        this.classList.add('success-flash');
        setTimeout(() => this.classList.remove('success-flash'), 500);
      });
    });
  }

  /**
   * Initializes the visual class builder interface.
   *
   * Sets up toggle functionality for the class builder panel and handles
   * clicking on class chips to build utility class combinations visually.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing builder controls.
   */
  function initClassBuilder(wrapper) {
    const builder = document.getElementById('class-builder');
    const builderBtn = wrapper.querySelector('[data-action="builder"]');
    const classInput = document.getElementById('utilikit-class-input');
    const selectedClasses = window.utilikitPlaygroundState?.selectedClasses || new Set();

    builderBtn?.addEventListener('click', () => {
      const isVisible = builder.style.display !== 'none';
      builder.style.display = isVisible ? 'none' : 'block';
      builderBtn.classList.toggle('active', !isVisible);
    });

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
   * Initializes the color palette functionality.
   *
   * Sets up click handlers for color swatches that update color inputs
   * and copy hex values to clipboard with visual feedback.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing color controls.
   */
  function initColorPalette(wrapper) {
    const colorPicker = document.getElementById('utilikit-color-picker');
    const colorValue = document.getElementById('utilikit-color-value');

    wrapper.querySelectorAll('.color-swatch').forEach(swatch => {
      swatch.addEventListener('click', function() {
        const color = this.dataset.color;
        colorPicker.value = color;
        colorValue.value = color;

        // Visual feedback
        wrapper.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
        this.classList.add('selected');

        // Copy hex without #
        const hexValue = color.replace('#', '');
        navigator.clipboard.writeText(hexValue).catch(() => {
          // Fallback
          colorValue.select();
          document.execCommand('copy');
        });

        // Animate
        this.style.transform = 'scale(1.3)';
        setTimeout(() => this.style.transform = '', 200);
      });
    });
  }

  /**
   * Initializes viewport control functionality.
   *
   * Sets up viewport simulation buttons (mobile, tablet, desktop) that
   * resize the preview frame and update size indicators.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing viewport controls.
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

        const sizes = {
          mobile: '375px',
          tablet: '768px',
          desktop: '1200px'
        };
        sizeIndicator.textContent = sizes[viewport];
      });
    });
  }

  /**
   * Initializes quick action buttons and responsive helper tools.
   *
   * Sets up tutorial launch, responsive helper toggle, and responsive
   * class converter with click-to-copy functionality.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing quick action controls.
   */
  function initQuickActions(wrapper) {
    // Tutorial button
    const tutorialBtn = wrapper.querySelector('[data-action="tutorial"]');
    tutorialBtn?.addEventListener('click', () => {
      document.getElementById('tutorial-overlay').classList.add('active');
    });

    // Responsive helper button
    const responsiveBtn = wrapper.querySelector('[data-action="responsive"]');
    const responsiveHelper = document.getElementById('responsive-helper');

    responsiveBtn?.addEventListener('click', () => {
      const isVisible = responsiveHelper.style.display !== 'none';
      responsiveHelper.style.display = isVisible ? 'none' : 'block';
      responsiveBtn.classList.toggle('active', !isVisible);
    });

    // Responsive converter
    const responsiveInput = document.getElementById('responsive-input');
    const responsiveOutput = document.getElementById('responsive-output');

    responsiveInput?.addEventListener('input', (e) => {
      const value = e.target.value.trim();

      if (value.startsWith('uk-') && value.includes('--')) {
        const breakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];
        let html = '<div class="responsive-variant" data-class="' + value + '">';
        html += '<span class="prefix">base:</span>';
        html += '<span class="class-name">' + value + '</span>';
        html += '</div>';

        breakpoints.forEach(bp => {
          const responsiveClass = value.replace('uk-', `uk-${bp}-`);
          html += '<div class="responsive-variant" data-class="' + responsiveClass + '">';
          html += '<span class="prefix">' + bp + ':</span>';
          html += '<span class="class-name">' + responsiveClass + '</span>';
          html += '</div>';
        });

        responsiveOutput.innerHTML = html;

        // Add click handlers to copy classes
        responsiveOutput.querySelectorAll('.responsive-variant').forEach(variant => {
          variant.addEventListener('click', function() {
            const className = this.dataset.class;
            const classInput = document.getElementById('utilikit-class-input');

            // Add to existing classes
            const currentClasses = classInput.value.trim();
            classInput.value = currentClasses ? currentClasses + ' ' + className : className;

            // Visual feedback
            this.style.background = '#28a745';
            this.style.color = 'white';
            setTimeout(() => {
              this.style.background = '';
              this.style.color = '';
            }, 300);

            // Copy to clipboard
            navigator.clipboard.writeText(className).catch(() => {});
          });
        });
      } else {
        responsiveOutput.innerHTML = '<p style="color: #6c757d; font-size: 13px;">Enter a valid UtiliKit class (e.g., uk-pd--20)</p>';
      }
    });
  }

  /**
   * Initializes the tutorial overlay and welcome experience.
   *
   * Shows tutorial on first visit and sets up close handlers for
   * tutorial overlay with local storage tracking.
   */
  function initTutorial() {
    const overlay = document.getElementById('tutorial-overlay');
    const closeBtn = document.getElementById('tutorial-close');
    const startBtn = document.getElementById('tutorial-start');

    // Show tutorial on first visit
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
   * Enhances form submission with style inspector updates.
   *
   * Adds post-submit handlers to update the style inspector and
   * rendered HTML display after form submission.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing the form.
   */
  function enhanceFormSubmit(wrapper) {
    const form = document.getElementById('utilikit-preview-form');
    const renderedHtml = document.getElementById('utilikit-rendered-html');

    // Add style inspector update after form submits
    if (form) {
      form.addEventListener('submit', () => {
        setTimeout(() => {
          updateStyleInspector();

          // Update rendered HTML section
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
   * Initializes enhanced reset functionality with state management.
   *
   * Sets up reset button to clear all playground state including
   * visual builder selections, autocomplete, and visibility states.
   *
   * @param {Element} wrapper
   *   The playground wrapper element containing reset controls.
   */
  function initResetEnhancements(wrapper) {
    const resetBtn = document.getElementById('utilikit-reset-btn');
    if (!resetBtn) return;

    // Store references to stateful components
    const stateManager = {
      selectedClasses: new Set(), // For visual builder
      autocompleteIndex: -1,      // For autocomplete
      responsiveHelperVisible: false,
      builderVisible: false,
    };

    // Make state manager accessible to other functions
    window.utilikitPlaygroundState = stateManager;

    // Listen for reset events
    resetBtn.addEventListener('click', function() {
      // Clear preset button active states
      wrapper.querySelectorAll('.preset-btn').forEach(btn => {
        btn.classList.remove('active');
      });

      // Clear visual builder active states
      wrapper.querySelectorAll('.builder-chip').forEach(chip => {
        chip.classList.remove('active');
      });

      // Clear color palette selections
      wrapper.querySelectorAll('.color-swatch').forEach(swatch => {
        swatch.classList.remove('selected');
      });

      // Clear quick action button states
      wrapper.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.classList.remove('active');
      });

      // Clear visual builder state
      stateManager.selectedClasses.clear();

      // Reset autocomplete state
      stateManager.autocompleteIndex = -1;

      // Reset visibility states
      stateManager.responsiveHelperVisible = false;
      stateManager.builderVisible = false;

      // Clear any temporary animations
      clearAllAnimations();

      // Reset any modified DOM elements
      resetDOMElements();

      // Clear any event listeners that might interfere
      cleanupEventListeners();
    });
  }

  /**
   * Clears all ongoing CSS animations and transitions.
   *
   * Removes any temporary animation or transition styles that might
   * interfere with the reset state.
   */
  function clearAllAnimations() {
    // Clear any ongoing animations
    document.querySelectorAll('*').forEach(el => {
      if (el.style.transition) {
        el.style.transition = '';
      }
      if (el.style.animation) {
        el.style.animation = '';
      }
    });
  }

  /**
   * Resets DOM elements to their default state.
   *
   * Removes temporary classes and data attributes that were added
   * during user interactions.
   */
  function resetDOMElements() {
    // Reset any dynamically added classes
    document.querySelectorAll('[class*="success-"], [class*="error-"], [class*="active-"]').forEach(el => {
      const classes = Array.from(el.classList);
      classes.forEach(cls => {
        if (cls.includes('success-') || cls.includes('error-') || cls.includes('active-')) {
          el.classList.remove(cls);
        }
      });
    });

    // Clear any data attributes used for state
    document.querySelectorAll('[data-state], [data-active], [data-selected]').forEach(el => {
      delete el.dataset.state;
      delete el.dataset.active;
      delete el.dataset.selected;
    });
  }

  /**
   * Updates the style inspector panel with current element styles.
   *
   * Analyzes the preview element's computed styles and applied classes,
   * then displays them in organized groups for debugging.
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
          <span class="class-arrow">→</span>
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
   * Converts a UtiliKit class name to a readable CSS property description.
   *
   * Parses UtiliKit utility class syntax to determine the CSS property
   * and value being applied, including handling directional and
   * responsive variants.
   *
   * @param {string} className
   *   The UtiliKit class name to analyze (e.g., 'uk-pd--20').
   *
   * @returns {string}
   *   Human-readable description of the CSS effect.
   */
  function getClassEffect(className) {
    const parts = className.split('--');
    if (parts.length !== 2) return 'Custom style';

    const [prefix, value] = parts;
    const prop = getPropertyName(className);

    if (value === 'auto') return `${prop}: auto`;
    if (value.includes('-')) {
      const subParts = value.split('-');
      if (['t', 'r', 'b', 'l'].includes(subParts[0])) {
        const sideMap = { t: 'top', r: 'right', b: 'bottom', l: 'left' };
        return `${prop}-${sideMap[subParts[0]]}: ${subParts[1]}px`;
      }
    }

    const unit = value.endsWith('pr') ? '%' : 'px';
    const numValue = value.replace('pr', '');
    return `${prop}: ${numValue}${unit}`;
  }

})(Drupal, once, drupalSettings);
