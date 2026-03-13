/**
 * @file
 * Interactive features for UtiliKit Reference page.
 *
 * Provides copy-to-clipboard, live utility class preview playground,
 * and click-to-expand usage panels with security validation throughout.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.utilikitReference = {
    attach(context, settings, retryCount = 0) {
      const maxRetries = 50;
      if (!Drupal.utilikit?.applyClasses) {
        if (retryCount < maxRetries) {
          setTimeout(() => {
            Drupal.behaviors.utilikitReference.attach(context, settings, retryCount + 1);
          }, 100);
        }
        return;
      }

      for (const element of once('utilikit-reference', '.utilikit-reference-page', context)) {
        try {
          initializeInteractiveExample(element);
          addHoverEffects(element);
        } catch (error) {
          console.error('UtiliKit Reference: Initialization failed', error);
        }
      }
    }
  };

  // ── Clipboard helpers ────────────────────────────────────────────

  function safeCopyToClipboard(text, button) {
    if (typeof text !== 'string' || !text.trim()) {
      showCopyError(button);
      return;
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(
        () => showCopySuccess(button),
        (err) => {
          console.error('Clipboard API failed:', err);
          fallbackCopy(text, button);
        },
      );
    } else {
      fallbackCopy(text, button);
    }
  }

  function fallbackCopy(text, button) {
    try {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.top = '0';
      textarea.style.left = '-9999px';
      textarea.style.opacity = '0';
      textarea.setAttribute('readonly', '');
      textarea.setAttribute('aria-hidden', 'true');

      document.body.appendChild(textarea);
      textarea.select();
      textarea.setSelectionRange(0, 99999);

      const successful = document.execCommand('copy');
      document.body.removeChild(textarea);

      if (successful) {
        showCopySuccess(button);
      } else {
        showCopyError(button);
      }
    } catch (err) {
      console.error('Fallback copy failed:', err);
      showCopyError(button);
    }
  }

  function showCopySuccess(button) {
    const originalText = button.textContent;
    button.textContent = 'Copied!';
    button.classList.add('copied', 'success-pulse');
    button.setAttribute('aria-label', 'Copied to clipboard');

    setTimeout(() => {
      button.textContent = originalText;
      button.classList.remove('copied', 'success-pulse');
      button.setAttribute('aria-label', 'Copy utility class example');
    }, 2000);
  }

  function showCopyError(button) {
    const originalText = button.textContent;
    const originalBg = button.style.background;

    button.textContent = 'Failed';
    button.style.background = '#dc3545';
    button.setAttribute('aria-label', 'Copy failed');

    setTimeout(() => {
      button.textContent = originalText;
      button.style.background = originalBg;
      button.setAttribute('aria-label', 'Copy utility class example');
    }, 2000);
  }

  // ── Interactive example playground ────────────────────────────────

  function initializeInteractiveExample(container) {
    const input = container.querySelector('#utilikit-example-input');
    const preview = container.querySelector('#utilikit-example-preview');

    if (!input || !preview) return;

    // Make example code blocks clickable for quick testing.
    for (const codeBlock of container.querySelectorAll('.interactive-example code')) {
      codeBlock.style.cursor = 'pointer';
      codeBlock.title = 'Click to try this example';

      codeBlock.addEventListener('click', () => {
        try {
          const content = Drupal.utilikit.security.sanitizeText(codeBlock.textContent);
          const classes = content.split(/\s+/).filter(
            (cls) => Drupal.utilikit.security.isValidUtilityClass(cls),
          );

          if (classes.length > 0) {
            input.value = classes.join(' ');
            updatePreview();

            codeBlock.style.background = '#28a745';
            codeBlock.style.color = 'white';
            setTimeout(() => {
              codeBlock.style.background = '';
              codeBlock.style.color = '';
            }, 300);
          }
        } catch (error) {
          console.error('UtiliKit: Example click failed', error);
        }
      });
    }

    // Initialize preview element with safe default state.
    preview.textContent = 'Preview Text';
    preview.className = 'example-preview utilikit';

    const initialClasses = 'uk-pd--20 uk-bg--007bff uk-tc--ffffff uk-br--8 uk-fs--18';
    input.value = initialClasses;
    updatePreview();

    let timeout;
    input.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(updatePreview, 300);
    });

    input.addEventListener('paste', () => {
      setTimeout(updatePreview, 10);
    });

    function updatePreview() {
      try {
        const inputValue = Drupal.utilikit.security.sanitizeText(input.value);
        const classes = inputValue.trim().split(/\s+/);

        preview.className = 'example-preview utilikit';
        preview.removeAttribute('style');

        for (const cls of classes) {
          if (cls && Drupal.utilikit.security.isValidUtilityClass(cls)) {
            preview.classList.add(cls);
          }
        }

        if (Drupal.utilikit?.applyClasses) {
          delete preview.dataset.utilikitProps;
          Drupal.utilikit.applyClasses([preview]);
        }
      } catch (error) {
        console.error('UtiliKit: Preview update failed', error);
        preview.className = 'example-preview utilikit';
        preview.removeAttribute('style');
      }
    }
  }

  // ── Click-to-expand usage panels ──────────────────────────────────

  function addHoverEffects(container) {
    let activePanel = null;
    let activeItem = null;

    for (const item of container.querySelectorAll('.syntax-item')) {
      if (item.dataset.expandInit) continue;
      item.dataset.expandInit = '1';
      item.style.cursor = 'pointer';

      item.addEventListener('click', (e) => {
        e.stopPropagation();

        if (activeItem === item) {
          closePanel();
          return;
        }

        closePanel();

        try {
          const prefix = item.dataset.prefix || '';
          if (!prefix) return;

          const scenarios = getScenariosForPrefix(prefix, item);
          if (!scenarios.length) return;

          const panel = document.createElement('div');
          panel.className = 'expand-panel';

          for (const s of scenarios) {
            const row = document.createElement('div');
            row.className = 'expand-row';

            const label = document.createElement('span');
            label.className = 'expand-label';
            label.textContent = s.label;
            row.appendChild(label);

            const code = document.createElement('code');
            code.className = 'expand-class';
            code.textContent = s.cls;
            code.title = 'Click to copy';
            code.addEventListener('click', (ev) => {
              ev.stopPropagation();
              safeCopyToClipboard(s.cls, code);
            });
            row.appendChild(code);

            panel.appendChild(row);
          }

          const allClasses = scenarios.map((s) => s.cls).join(' ');
          const copyAllBtn = document.createElement('button');
          copyAllBtn.className = 'expand-copy-all';
          copyAllBtn.textContent = 'Copy All';
          copyAllBtn.type = 'button';
          copyAllBtn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            safeCopyToClipboard(allClasses, copyAllBtn);
          });
          panel.appendChild(copyAllBtn);

          item.closest('.syntax-items').appendChild(panel);
          item.classList.add('is-expanded');

          activePanel = panel;
          activeItem = item;
        } catch (error) {
          console.error('UtiliKit: Expand panel failed', error);
        }
      });
    }

    function closePanel() {
      activePanel?.remove();
      activePanel = null;
      activeItem?.classList.remove('is-expanded');
      activeItem = null;
    }
  }

  // ── Scenario data for expand panels ───────────────────────────────

  function getScenariosForPrefix(prefix, item) {
    const hasSides = item.dataset.sides === '1';
    const hasPseudo = item.dataset.pseudo === '1';
    const s = [];
    const p = `uk-${prefix}--`;

    // Color prefixes.
    if (['bg', 'tc', 'bc'].includes(prefix)) {
      s.push({label: '6-digit hex', cls: `${p}ff0000`});
      s.push({label: '3-digit hex', cls: `${p}f00`});
      s.push({label: 'With 50% opacity', cls: `${p}ff0000-50`});
      if (hasPseudo) {
        s.push({label: 'Hover state', cls: `${p}007bff-h`});
        s.push({label: 'Focus state', cls: `${p}007bff-f`});
        s.push({label: 'Active state', cls: `${p}007bff-a`});
        s.push({label: 'Opacity + hover', cls: `${p}007bff-50-h`});
      }
      s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--ff0000`});
      return s;
    }

    // Sides prefixes (pd, mg, bw, br, bs, bc).
    if (hasSides) {
      s.push({label: 'All sides', cls: `${p}20`});
      s.push({label: 'Top only', cls: `${p}t-20`});
      s.push({label: 'Right only', cls: `${p}r-20`});
      s.push({label: 'Bottom only', cls: `${p}b-20`});
      s.push({label: 'Left only', cls: `${p}l-20`});
      s.push({label: '2-value (top/bottom, left/right)', cls: `${p}10-20`});
      s.push({label: '4-value (t r b l)', cls: `${p}10-20-10-20`});
      if (prefix === 'mg') {
        s.push({label: 'Auto', cls: `${p}auto`});
        s.push({label: 'Top auto', cls: `${p}t-auto`});
      }
      if (prefix === 'pd' || prefix === 'mg') {
        s.push({label: 'In rem', cls: `${p}1d5rem`});
        s.push({label: 'In %', cls: `${p}50pr`});
      }
      if (prefix === 'br') {
        s.push({label: 'In %', cls: `${p}50pr`});
      }
      s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--20`});
      return s;
    }

    // Keyword prefixes.
    const keywords = {
      dp: ['flex', 'grid', 'block', 'inline-block', 'inline-flex', 'none'],
      ps: ['relative', 'absolute', 'fixed', 'sticky', 'static'],
      ta: ['center', 'start', 'end', 'justify'],
      ov: ['hidden', 'auto', 'scroll', 'visible'],
      cu: ['pointer', 'default', 'move', 'text', 'not-allowed', 'grab'],
      fl: ['inline-start', 'inline-end', 'none'],
      cl: ['both', 'inline-start', 'inline-end', 'none'],
      us: ['none', 'text', 'all', 'auto'],
      fd: ['row', 'column', 'row-reverse', 'column-reverse'],
      jc: ['center', 'flex-start', 'flex-end', 'space-between', 'space-around', 'space-evenly'],
      ai: ['center', 'flex-start', 'flex-end', 'stretch', 'baseline'],
      ac: ['center', 'flex-start', 'flex-end', 'stretch', 'space-between', 'space-around'],
      fx: ['wrap', 'nowrap', 'wrap-reverse'],
      bz: ['cover', 'contain', 'auto'],
    };
    if (keywords[prefix]) {
      for (const kw of keywords[prefix]) {
        s.push({label: kw, cls: `${p}${kw}`});
      }
      s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--${keywords[prefix][0]}`});
      return s;
    }

    // Sizing (numericFlexible, no sides).
    if (['wd', 'ht', 'xw', 'xh', 'nw', 'nh'].includes(prefix)) {
      s.push({label: 'Pixels', cls: `${p}200`});
      s.push({label: 'Percentage', cls: `${p}100pr`});
      s.push({label: 'Viewport width', cls: `${p}50vw`});
      s.push({label: 'Viewport height', cls: `${p}100vh`});
      s.push({label: 'In rem', cls: `${p}20rem`});
      s.push({label: 'In em', cls: `${p}10em`});
      if (['wd', 'ht'].includes(prefix)) {
        s.push({label: 'Auto', cls: `${p}auto`});
      }
      s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--200`});
      return s;
    }

    // Font size / line height.
    if (prefix === 'fs' || prefix === 'lh') {
      s.push({label: 'Pixels', cls: `${p}16`});
      s.push({label: 'In rem', cls: `${p}1d5rem`});
      s.push({label: 'In em', cls: `${p}1d25em`});
      s.push({label: 'Decimal (1.6)', cls: `${p}1d6`});
      s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--16`});
      return s;
    }

    // Letter spacing.
    if (prefix === 'ls') {
      s.push({label: 'Pixels', cls: `${p}2`});
      s.push({label: 'In em', cls: `${p}0d05em`});
      s.push({label: 'Negative', cls: `${p}-1`});
      s.push({label: 'Responsive (md)', cls: 'uk-md-ls--2'});
      return s;
    }

    // Gap.
    if (prefix === 'gp') {
      s.push({label: 'Single value', cls: `${p}20`});
      s.push({label: 'Row & column', cls: `${p}16-32`});
      s.push({label: 'In rem', cls: `${p}1d5rem`});
      s.push({label: 'Responsive (md)', cls: 'uk-md-gp--20'});
      return s;
    }

    // Position offsets (tp=top, ri=right, bt=bottom, lt=left; do not use 'rt' here — rt is rotate).
    if (['tp', 'ri', 'bt', 'lt'].includes(prefix)) {
      s.push({label: 'Pixels', cls: `${p}20`});
      s.push({label: 'Zero', cls: `${p}0`});
      s.push({label: 'Percentage', cls: `${p}50pr`});
      s.push({label: 'Negative', cls: `${p}-20`});
      s.push({label: 'In rem', cls: `${p}2rem`});
      s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--20`});
      return s;
    }

    // Opacity.
    if (prefix === 'op') {
      s.push({label: 'Fully visible', cls: `${p}100`});
      s.push({label: '75%', cls: `${p}75`});
      s.push({label: '50%', cls: `${p}50`});
      s.push({label: '25%', cls: `${p}25`});
      s.push({label: 'Hidden', cls: `${p}0`});
      s.push({label: 'Responsive (md)', cls: 'uk-md-op--50'});
      return s;
    }

    // Z-index.
    if (prefix === 'zi') {
      s.push({label: 'Low', cls: `${p}1`});
      s.push({label: 'Medium', cls: `${p}10`});
      s.push({label: 'High', cls: `${p}100`});
      s.push({label: 'Max', cls: `${p}9999`});
      s.push({label: 'Negative', cls: `${p}-1`});
      s.push({label: 'Responsive (md)', cls: 'uk-md-zi--10'});
      return s;
    }

    // Font weight.
    if (prefix === 'fw') {
      s.push({label: 'Thin', cls: `${p}100`});
      s.push({label: 'Light', cls: `${p}300`});
      s.push({label: 'Normal', cls: `${p}400`});
      s.push({label: 'Semi-bold', cls: `${p}600`});
      s.push({label: 'Bold', cls: `${p}700`});
      s.push({label: 'Black', cls: `${p}900`});
      s.push({label: 'Responsive (md)', cls: 'uk-md-fw--700'});
      return s;
    }

    // Order.
    if (prefix === 'or') {
      s.push({label: 'First', cls: `${p}-1`});
      s.push({label: 'Default', cls: `${p}0`});
      s.push({label: 'Second', cls: `${p}1`});
      s.push({label: 'Third', cls: `${p}2`});
      s.push({label: 'Last', cls: `${p}99`});
      return s;
    }

    // Flex grow / shrink.
    if (prefix === 'fg' || prefix === 'fk') {
      s.push({label: 'None', cls: `${p}0`});
      s.push({label: 'Default', cls: `${p}1`});
      s.push({label: '1.5x', cls: `${p}1d5`});
      s.push({label: 'Double', cls: `${p}2`});
      return s;
    }

    // Flex basis.
    if (prefix === 'fb') {
      s.push({label: 'Auto', cls: `${p}auto`});
      s.push({label: 'Pixels', cls: `${p}200`});
      s.push({label: 'Percentage', cls: `${p}50pr`});
      s.push({label: 'Zero', cls: `${p}0`});
      return s;
    }

    // Aspect ratio.
    if (prefix === 'ar') {
      s.push({label: 'Widescreen 16:9', cls: `${p}16-9`});
      s.push({label: 'Standard 4:3', cls: `${p}4-3`});
      s.push({label: 'Square 1:1', cls: `${p}1-1`});
      s.push({label: 'Ultra-wide 21:9', cls: `${p}21-9`});
      return s;
    }

    // Grid template columns / rows.
    if (prefix === 'gc') {
      s.push({label: '3 equal columns', cls: `${p}repeat-3-1fr`});
      s.push({label: '4 equal columns', cls: `${p}repeat-4-1fr`});
      s.push({label: 'Fixed + flexible', cls: `${p}200-1fr`});
      s.push({label: 'Auto-fill', cls: `${p}auto-fill-minmax-200-1fr`});
      return s;
    }
    if (prefix === 'gr') {
      s.push({label: '2 equal rows', cls: `${p}repeat-2-1fr`});
      s.push({label: 'Fixed + auto', cls: `${p}100-auto-1fr`});
      return s;
    }

    // Grid column / row span.
    if (prefix === 'gl') {
      s.push({label: 'Span 2 columns', cls: `${p}1-3`});
      s.push({label: 'Span 3 columns', cls: `${p}1-4`});
      s.push({label: 'Start at col 2', cls: `${p}2-4`});
      return s;
    }
    if (prefix === 'gw') {
      s.push({label: 'Span 2 rows', cls: `${p}1-3`});
      s.push({label: 'Start at row 2', cls: `${p}2-4`});
      return s;
    }

    // Rotate (rt only; right position is 'ri' above).
    if (prefix === 'rt') {
      s.push({label: '45 degrees', cls: `${p}45`});
      s.push({label: '90 degrees', cls: `${p}90`});
      s.push({label: '180 degrees', cls: `${p}180`});
      s.push({label: 'Negative 45', cls: `${p}-45`});
      return s;
    }

    // Scale.
    if (prefix === 'sc') {
      s.push({label: 'Half (50%)', cls: `${p}50`});
      s.push({label: 'Normal (100%)', cls: `${p}100`});
      s.push({label: '1.5x (150%)', cls: `${p}150`});
      s.push({label: 'Double (200%)', cls: `${p}200`});
      return s;
    }

    // Fallback.
    s.push({label: 'Example', cls: `${p}20`});
    s.push({label: 'Responsive (md)', cls: `uk-md-${prefix}--20`});
    return s;
  }

})(Drupal, once);
