/**
 * @param Drupal
 * @param once
 * @file
 * UtiliKit Help Page JavaScript.
 *
 * Provides tab navigation, code copying, expandable sections,
 * and mobile-responsive navigation for the help documentation.
 */

(function (Drupal, once) {
  /**
   * UtiliKit Help - Force inline rendering for examples.
   */
  Drupal.behaviors.utilikitHelpEngine = {
    attach(context) {
      const elements = once('utilikit-examples', '.utilikit', context);
      if (elements.length > 0 && Drupal.utilikit?.applyClasses) {
        Drupal.utilikit.applyClasses(elements);
      }
    },
  };

  /**
   * UtiliKit Help Page Main Behavior.
   */
  Drupal.behaviors.utilikitHelp = {
    attach(context) {
      const helpPageList = once(
        'utilikit-help',
        '.utilikit-help-page',
        context,
      );
      for (let i = 0; i < helpPageList.length; i += 1) {
        const helpPage = helpPageList[i];
        initializeHelpPage(helpPage);
      }
    },
  };

  function initializeHelpPage(helpPage) {
    const tabsWrapper = helpPage.querySelector('.vertical-tabs-wrapper');
    if (!tabsWrapper) return;

    initializeMobileToggle(tabsWrapper);
    initializeTabSwitching(tabsWrapper);
    initializeResponsiveFeatures(helpPage);
    initializeCodeCopy(helpPage);
    initializeExpandableCode(helpPage);
    initializeClickToCopy(helpPage);
  }

  // ── Mobile navigation toggle ─────────────────────────────────────

  function initializeMobileToggle(tabsWrapper) {
    const btn = tabsWrapper.querySelector('.mobile-nav-toggle');
    const nav = tabsWrapper.querySelector('.vertical-tabs-nav');
    if (!btn || !nav) return;

    btn.addEventListener('click', () => {
      const isOpen = tabsWrapper.classList.toggle('nav-open');
      btn.setAttribute('aria-expanded', String(isOpen));
    });

    // Close nav when a tab is clicked on mobile.
    nav.addEventListener('click', (e) => {
      if (e.target.closest('.tab-button') && window.innerWidth <= 992) {
        tabsWrapper.classList.remove('nav-open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // ── Tab switching ─────────────────────────────────────────────────

  function initializeTabSwitching(tabsWrapper) {
    const tabButtons = tabsWrapper.querySelectorAll('.tab-button');
    const tabContents = tabsWrapper.querySelectorAll('.tab-content');

    const buttonList = tabButtons;
    for (let i = 0; i < buttonList.length; i += 1) {
      const button = buttonList[i];
      button.addEventListener('click', function () {
        switchToTab(this.dataset.tab, tabButtons, tabContents, tabsWrapper);
      });

      button.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          this.click();
        }
      });
    }

    // URL hash support.
    const initialHash = window.location.hash.substring(1);
    if (initialHash && document.getElementById(initialHash)) {
      switchToTab(initialHash, tabButtons, tabContents, tabsWrapper);
    }

    window.addEventListener('hashchange', () => {
      const hash = window.location.hash.substring(1);
      if (hash && document.getElementById(hash)) {
        switchToTab(hash, tabButtons, tabContents, tabsWrapper);
      }
    });
  }

  function switchToTab(targetTab, tabButtons, tabContents, tabsWrapper) {
    const btnList = tabButtons;
    for (let i = 0; i < btnList.length; i += 1) {
      const btn = btnList[i];
      btn.classList.remove('active');
      btn.setAttribute('aria-selected', 'false');
    }

    const contentList = tabContents;
    for (let i = 0; i < contentList.length; i += 1) {
      const content = contentList[i];
      content.classList.remove('active');
    }

    // Use dataset comparison instead of string-interpolated selector to prevent
    // CSS selector injection from URL hash values.
    const activeButton = Array.from(tabButtons).find(
      (btn) => btn.dataset.tab === targetTab,
    );
    const activeContent = document.getElementById(targetTab);

    if (activeButton && activeContent) {
      activeButton.classList.add('active');
      activeButton.setAttribute('aria-selected', 'true');
      activeContent.classList.add('active');

      window.history.replaceState?.(null, null, `#${targetTab}`);

      // Scroll to the top of the tab content area so the user sees it from the beginning.
      const scrollTarget =
        tabsWrapper.querySelector('.vertical-tabs-content') ?? tabsWrapper;
      setTimeout(() => {
        scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 50);
    }
  }

  // ── Responsive features ───────────────────────────────────────────

  function initializeResponsiveFeatures(helpPage) {
    const currentSizeSpan = helpPage.querySelector('#current-size');
    if (!currentSizeSpan) return;

    const updateSize = () => {
      const w = window.innerWidth;
      let size = 'Mobile (0px+)';
      if (w >= 1400) size = 'XXL (1400px+)';
      else if (w >= 1200) size = 'XL (1200px+)';
      else if (w >= 992) size = 'Large (992px+)';
      else if (w >= 768) size = 'Medium (768px+)';
      else if (w >= 576) size = 'Small (576px+)';
      currentSizeSpan.textContent = size;
    };

    updateSize();
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(updateSize, 150);
    });
  }

  // ── Code copy buttons ─────────────────────────────────────────────

  function initializeCodeCopy(helpPage) {
    const codeBlockList = helpPage.querySelectorAll('pre code');
    for (let i = 0; i < codeBlockList.length; i += 1) {
      const codeBlock = codeBlockList[i];
      const pre = codeBlock.parentElement;
      if (pre.querySelector('.code-copy-btn')) continue;

      pre.classList.add('has-copy-btn');

      const btn = document.createElement('button');
      btn.className = 'code-copy-btn';
      btn.textContent = 'Copy';
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Copy code to clipboard');
      pre.appendChild(btn);

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        copyText(codeBlock.textContent, btn);
      });
    }
  }

  // ── Expandable code details ───────────────────────────────────────

  function initializeExpandableCode(helpPage) {
    const detailList = helpPage.querySelectorAll('details.code-details');
    for (let i = 0; i < detailList.length; i += 1) {
      const detail = detailList[i];
      const summary = detail.querySelector('summary');
      if (!summary) continue;

      summary.addEventListener('click', () => {
        if (detail.open) {
          detail.style.maxHeight = `${detail.scrollHeight}px`;
          requestAnimationFrame(() => {
            detail.style.maxHeight = `${summary.scrollHeight}px`;
          });
        } else {
          setTimeout(() => {
            detail.style.maxHeight = `${detail.scrollHeight}px`;
            setTimeout(() => {
              detail.style.maxHeight = '';
            }, 300);
          }, 0);
        }
      });
    }
  }

  // ── Click-to-copy inline code ─────────────────────────────────────

  function initializeClickToCopy(helpPage) {
    const codeList = helpPage.querySelectorAll('code:not(pre code)');
    for (let i = 0; i < codeList.length; i += 1) {
      const code = codeList[i];
      code.classList.add('is-copyable');
      code.title = 'Click to copy';

      code.addEventListener('click', function () {
        copyText(this.textContent, this);
      });
    }
  }

  // ── Clipboard helper ──────────────────────────────────────────────

  function copyText(text, el) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(
        () => showFeedback(el, true),
        () => fallbackCopy(text, el),
      );
    } else {
      fallbackCopy(text, el);
    }
  }

  function fallbackCopy(text, el) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    ta.setAttribute('readonly', '');
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch {
      /* noop */
    }
    document.body.removeChild(ta);
    showFeedback(el, ok);
  }

  function showFeedback(el, success) {
    el.classList.add(success ? 'copy-success' : 'copy-error');
    setTimeout(() => {
      el.classList.remove('copy-success', 'copy-error');
    }, 1500);
  }
})(Drupal, once);
