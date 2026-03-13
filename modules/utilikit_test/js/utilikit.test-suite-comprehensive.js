/**
 * @file
 * UtiliKit Test Suite Runner.
 *
 * Validates all UtiliKit utility classes via computed style checks.
 *
 * @see TestGenerator.php
 * @see utilikit-test-suite.html.twig
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.utilikitTest = {
    attach: function(context) {
      const elements = once('utilikit-test', '.utilikit', context);
      if (elements.length > 0 && Drupal.utilikit?.applyClasses) {
        Drupal.utilikit.applyClasses(elements);
      }
    }
  };

  Drupal.behaviors.utilikitComprehensiveTestRunner = {
    async attach(context, settings) {
      once('utilikit-test-runner', '#utilikit-test-suite', context).forEach(async (container) => {
        const runner = new UtilikitTestRunner(container);
        if (settings?.utilikit?.renderingMode === 'static') {
          await runner.waitForStylesheets();
          await new Promise(r => setTimeout(r, 50));
        }
        runner.init();
      });
    }
  };

  class UtilikitTestRunner {
    constructor(container) {
      this.container = container;
      this.totalTests = 0;
      this.passedTests = 0;
      this.failedTests = 0;
      this.currentTest = 0;
      this.testResults = new Map();
      this.isRunning = false;
      this.shouldStop = false;
      this.allExpanded = false;
      this.groupStats = {};
    }

    init() {
      this.setupElements();
      this.countTests();
      this.buildGroupStats();
      this.attachEventListeners();
      this.updateSummary();
      this.updateRunButtonLabel();
      this.log('Test suite initialized. Ready to run tests.', 'info');
    }

    setupElements() {
      this.el = {
        totalTests: document.getElementById('total-tests'),
        passedCount: document.getElementById('passed-count'),
        failedCount: document.getElementById('failed-count'),
        testStatus: document.getElementById('test-status'),
        logContent: document.getElementById('test-log-content'),
        runBtn: document.getElementById('run-tests'),
        stopBtn: document.getElementById('stop-tests'),
        toggleAllBtn: document.getElementById('toggle-all'),
        exportBtn: document.getElementById('export-results'),
        resetBtn: document.getElementById('reset-tests'),
        clearLogBtn: document.getElementById('clear-log'),
        statusFilter: document.getElementById('status-filter'),
        groupFilter: document.getElementById('group-filter'),
        progressBar: document.getElementById('test-progress-bar'),
        progressFill: document.getElementById('test-progress-fill'),
        progressText: document.getElementById('test-progress-text'),
      };
    }

    countTests() {
      this.testCases = this.container.querySelectorAll('.utilikit-test-case');
      this.totalTests = this.testCases.length;
      this.el.totalTests.textContent = this.totalTests;
    }

    buildGroupStats() {
      this.groupStats = {};
      this.container.querySelectorAll('.utilikit-test-group').forEach(group => {
        const name = group.dataset.group;
        const count = group.querySelectorAll('.utilikit-test-case').length;
        this.groupStats[name] = { passed: 0, failed: 0, total: count };
      });
    }

    attachEventListeners() {
      this.el.runBtn?.addEventListener('click', () => this.runTests());
      this.el.stopBtn?.addEventListener('click', () => this.stopTests());
      this.el.toggleAllBtn?.addEventListener('click', () => this.toggleAll());
      this.el.exportBtn?.addEventListener('click', () => this.exportResults());
      this.el.resetBtn?.addEventListener('click', () => this.resetAll());
      this.el.clearLogBtn?.addEventListener('click', () => this.clearLog());
      this.el.statusFilter?.addEventListener('change', () => this.applyFilters());
      this.el.groupFilter?.addEventListener('change', () => {
        this.applyFilters();
        this.updateRunButtonLabel();
      });

      this.container.querySelectorAll('.toggle-tests').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const body = e.target.closest('.utilikit-test-rule').querySelector('.utilikit-test-rule__body');
          const isVisible = body.style.display !== 'none';
          body.style.display = isVisible ? 'none' : 'block';
          e.target.textContent = isVisible ? 'Show' : 'Hide';
        });
      });
    }

    /**
     * Updates the run button label based on group filter selection.
     */
    updateRunButtonLabel() {
      const selected = this.el.groupFilter.value;
      if (selected === 'all') {
        this.el.runBtn.textContent = 'Test All Groups';
      } else {
        this.el.runBtn.textContent = 'Test ' + selected;
      }
    }

    setRunningUI(running) {
      this.el.runBtn.style.display = running ? 'none' : '';
      this.el.stopBtn.style.display = running ? '' : 'none';
      this.el.groupFilter.disabled = running;
      this.el.statusFilter.disabled = running;
      this.el.progressBar.style.display = running ? '' : 'none';
      if (running) {
        this.el.progressFill.style.width = '0%';
        this.el.progressFill.className = 'utilikit-test-progress__fill';
        this.el.progressText.textContent = '0%';
      }
    }

    /**
     * Runs tests for the currently selected group (or all).
     */
    async runTests() {
      if (this.isRunning) return;

      const selectedGroup = this.el.groupFilter.value;

      // Gather test cases matching the filter.
      let targets;
      if (selectedGroup === 'all') {
        targets = Array.from(this.testCases);
      } else {
        const groupEl = this.container.querySelector(`.utilikit-test-group[data-group="${selectedGroup}"]`);
        targets = groupEl ? Array.from(groupEl.querySelectorAll('.utilikit-test-case')) : [];
      }

      if (targets.length === 0) {
        this.log('No tests to run for the selected group.', 'warn');
        return;
      }

      this.resetResults();
      this.isRunning = true;
      this.shouldStop = false;
      this.totalTests = targets.length;
      this.el.totalTests.textContent = this.totalTests;
      this.setRunningUI(true);

      const label = selectedGroup === 'all' ? 'all groups' : selectedGroup;
      this.log(`Running ${targets.length} tests (${label})...`, 'info');
      const startTime = performance.now();

      const batchSize = 10;
      for (let i = 0; i < targets.length; i += batchSize) {
        if (this.shouldStop) break;
        const batch = targets.slice(i, i + batchSize);
        await Promise.all(batch.map(tc => this.runSingleTest(tc)));
        this.currentTest = Math.min(i + batchSize, targets.length);
        this.updateProgress();
        await new Promise(r => setTimeout(r, 10));
      }

      const duration = ((performance.now() - startTime) / 1000).toFixed(2);
      this.log(`Test run completed in ${duration}s`, 'info');
      this.finishTestRun();
    }

    async runSingleTest(testCase) {
      const testData = JSON.parse(testCase.dataset.test);
      const testId = testCase.dataset.testId;

      try {
        const previewEl = testCase.querySelector('.preview-element');
        if (!previewEl) throw new Error('Preview element not found');

        previewEl.removeAttribute('style');
        delete previewEl.dataset.utilikitProps;

        if (testData.className.includes('uk-bw--') || testData.className.includes('-bw--')) {
          if (!previewEl.classList.contains('uk-bs--solid')) {
            previewEl.classList.add('uk-bs--solid');
          }
        }

        Drupal.utilikit.applyClasses([previewEl]);
        await new Promise(r => requestAnimationFrame(r));
        await new Promise(r => requestAnimationFrame(r));

        const computed = window.getComputedStyle(previewEl);
        if (testData.className.includes('rt--') || testData.className.includes('sc--')) {
          void previewEl.offsetHeight;
        }

        const results = this.validateTest(testData, computed, previewEl);
        this.updateTestRow(testCase, results);
        this.testResults.set(testId, { ...testData, ...results, timestamp: new Date().toISOString() });

        const groupEl = testCase.closest('.utilikit-test-group');
        if (groupEl) {
          const gn = groupEl.dataset.group;
          if (this.groupStats[gn]) {
            results.passed ? this.groupStats[gn].passed++ : this.groupStats[gn].failed++;
          }
        }

        results.passed ? this.passedTests++ : this.failedTests++;
        if (!results.passed) {
          this.log(`Failed: ${testData.className} - Expected: ${results.expected}, Got: ${results.actual}`, 'error');
        }
      } catch (error) {
        this.failedTests++;
        this.updateTestRow(testCase, { passed: false, actual: 'Error: ' + error.message, expected: '-' });
        this.log(`Error: ${testData.className}: ${error.message}`, 'error');
      }

      this.updateSummary();
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    validateTest(testData, computed, element) {
      if (testData.cssProperty === 'transform') return this.validateTransform(testData, computed, element);
      if (testData.testType === 'grid-template' || testData.testType === 'grid-template-responsive' ||
          testData.cssProperty === 'gridTemplateColumns' || testData.cssProperty === 'gridTemplateRows') {
        return this.validateGridTemplate(testData, computed, element);
      }
      if (testData.testType && testData.testType.includes('shorthand')) return this.validateShorthand(testData, computed);

      let passed = false;
      let actual = '';
      let expected = '';

      if (testData.cssProperty === 'gap') {
        const rg = computed.rowGap || computed.gridRowGap || '0px';
        const cg = computed.columnGap || computed.gridColumnGap || '0px';
        actual = `${rg} ${cg}`;
        expected = testData.expectedValue;
        if (expected.indexOf(' ') === -1) {
          passed = actual === `${expected} ${expected}` || actual === expected;
        } else {
          passed = actual === expected;
        }
        return { passed, actual, expected };
      }

      const cssProp = testData.cssProperty;
      actual = computed[cssProp] || '';
      expected = testData.expectedValue;

      if (cssProp && cssProp.includes('borderStartStartRadius')) actual = computed[cssProp] || '0px';

      actual = this.norm(actual);
      expected = this.norm(expected);

      if ((actual === '0px' && expected === '0') || (actual === '0' && expected === '0px')) passed = true;
      else if (testData.testType === 'color' || testData.testType === 'color-alpha') passed = this.colorsEqual(actual, expected);
      else if (expected.includes('vh') || expected.includes('vw')) passed = actual.includes('px') && parseFloat(actual) > 0;
      else if (cssProp === 'letterSpacing' && (expected === '0px' || expected === '0')) passed = actual === '0px' || actual === 'normal';
      else if (expected === 'auto' && (actual === '0px' || actual === '0')) {
        if (['margin', 'marginBlockStart', 'marginInlineEnd', 'marginBlockEnd', 'marginInlineStart'].some(p => cssProp.includes(p))) {
          return { passed: true, actual: actual + ' (auto)', expected };
        }
      }
      else passed = actual === expected;

      return { passed, actual, expected };
    }

    validateGridTemplate(testData, computed, element) {
      const expected = testData.expectedValue;
      const cssProp = testData.cssProperty;
      const inlineVal = element.style[cssProp] || '';
      const computedVal = computed[cssProp] || '';
      let actual = inlineVal || computedVal || 'none';

      if (expected.includes('/* missing */')) {
        const passed = actual === 'none' || actual === '';
        return { passed, actual: actual || 'none', expected: 'Invalid pattern (should not apply)' };
      }

      const ng = v => v.replace(/\s+/g, ' ').replace(/\s*,\s*/g, ', ').replace(/\s*\(\s*/g, '(').replace(/\s*\)\s*/g, ')').trim();
      let passed = ng(actual) === ng(expected);
      if (!passed && actual && actual !== 'none') {
        passed = ng(actual).includes('repeat') && ng(actual).includes('minmax');
      }
      return { passed, actual: actual || 'none', expected };
    }

    validateShorthand(testData, computed) {
      const result = { passed: false, actual: '', expected: testData.expectedValue };
      const base = testData.className.includes('mg') ? 'margin' :
                   testData.className.includes('pd') ? 'padding' :
                   testData.className.includes('bw') ? 'borderWidth' :
                   testData.className.includes('br') ? 'borderRadius' : 'padding';
      const sv = computed[base];
      if (sv && sv !== '') {
        result.actual = sv;
        result.passed = this.norm(sv) === this.norm(testData.expectedValue);
      } else {
        const vals = ['Top', 'Right', 'Bottom', 'Left'].map(s => computed[base + s] || '0px');
        if (vals[0] === vals[2] && vals[1] === vals[3]) {
          result.actual = vals[0] === vals[1] ? vals[0] : `${vals[0]} ${vals[1]}`;
        } else if (vals[1] === vals[3]) {
          result.actual = `${vals[0]} ${vals[1]} ${vals[2]}`;
        } else {
          result.actual = vals.join(' ');
        }
        result.passed = this.norm(result.actual) === this.norm(testData.expectedValue);
      }
      return result;
    }

    validateTransform(testData, computed, element) {
      const expected = testData.expectedValue;
      const inline = element.style.transform || '';
      const comp = computed.transform || 'none';

      if (inline && inline !== 'none' && inline === expected) return { passed: true, actual: inline, expected };

      let passed = false;
      let actual = inline || comp;

      if (!inline && (comp === 'none' || !comp)) return { passed: false, actual: 'none', expected };

      if (expected === 'rotate(0deg)' && (actual === 'none' || actual === '')) { passed = true; actual = 'rotate(0deg)'; }
      else if (expected === 'scale(1)' && (actual === 'none' || actual === '')) { passed = true; actual = 'scale(1)'; }
      else if (expected.includes('rotate')) {
        const em = expected.match(/rotate\((-?\d+(?:\.\d+)?)deg\)/);
        if (em) {
          const deg = parseFloat(em[1]);
          if (inline && inline.includes(`rotate(${deg}deg)`)) { passed = true; actual = expected; }
          else if (comp.includes('rotate')) {
            const cm = comp.match(/rotate\((-?\d+(?:\.\d+)?)deg\)/);
            if (cm) { passed = Math.abs(parseFloat(cm[1]) - deg) < 0.1; actual = `rotate(${cm[1]}deg)`; }
          }
          else if (comp.includes('matrix') && inline === expected) { passed = true; actual = expected; }
        }
      }
      else if (expected.includes('scale')) {
        const em = expected.match(/scale\((\d+(?:\.\d+)?)\)/);
        if (em) {
          const es = parseFloat(em[1]);
          if (inline && new RegExp(`scale\\(${es}(?:\\.0+)?\\)`).test(inline)) { passed = true; actual = expected; }
          if (!passed && comp.includes('scale')) {
            const cm = comp.match(/scale\((\d+(?:\.\d+)?)\)/);
            if (cm) { passed = Math.abs(parseFloat(cm[1]) - es) < 0.01; actual = `scale(${cm[1]})`; }
          }
          else if (!passed && comp.includes('matrix')) {
            const mm = comp.match(/matrix\(([^,]+),/);
            if (mm) { const sv = parseFloat(mm[1]); passed = Math.abs(sv - es) < 0.01; actual = `scale(${sv})`; }
          }
        }
      }

      return { passed, actual: actual || 'none', expected };
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    norm(v) {
      if (typeof v !== 'string') return String(v);
      v = v.trim();
      if (v === '0' || v === '0px') return '0px';
      if (v.startsWith('rgb')) return this.rgbHex(v);
      return v;
    }

    colorsEqual(a, b) {
      const ah = a.startsWith('#') ? a : this.rgbHex(a);
      const bh = b.startsWith('#') ? b : this.rgbHex(b);
      return ah.toLowerCase() === bh.toLowerCase();
    }

    rgbHex(rgb) {
      const m = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
      if (!m) return rgb;
      return '#' + [m[1], m[2], m[3]].map(c => parseInt(c).toString(16).padStart(2, '0')).join('');
    }

    // ---------------------------------------------------------------
    // UI updates
    // ---------------------------------------------------------------

    updateTestRow(testCase, results) {
      testCase.querySelector('.test-actual').textContent = results.actual;
      const badge = testCase.querySelector('.utilikit-test-badge');
      badge.textContent = results.passed ? 'Passed' : 'Failed';
      badge.className = `utilikit-test-badge ${results.passed ? 'utilikit-test-badge--passed' : 'utilikit-test-badge--failed'}`;
      testCase.classList.add(results.passed ? 'is-passed' : 'is-failed');
    }

    toggleAll() {
      this.allExpanded = !this.allExpanded;
      this.container.querySelectorAll('.utilikit-test-rule__body').forEach(body => {
        body.style.display = this.allExpanded ? 'block' : 'none';
      });
      this.container.querySelectorAll('.toggle-tests').forEach(btn => {
        btn.textContent = this.allExpanded ? 'Hide' : 'Show';
      });
      this.el.toggleAllBtn.textContent = this.allExpanded ? 'Collapse All' : 'Expand All';
    }

    applyFilters() {
      const sf = this.el.statusFilter.value;
      const gf = this.el.groupFilter.value;

      this.container.querySelectorAll('.utilikit-test-group').forEach(g => {
        g.style.display = (gf === 'all' || g.dataset.group === gf) ? '' : 'none';
      });

      if (sf !== 'all') {
        this.testCases.forEach(tc => {
          let show = true;
          if (sf === 'passed' && !tc.classList.contains('is-passed')) show = false;
          else if (sf === 'failed' && !tc.classList.contains('is-failed')) show = false;
          else if (sf === 'pending' && (tc.classList.contains('is-passed') || tc.classList.contains('is-failed'))) show = false;
          tc.style.display = show ? '' : 'none';
        });
      } else {
        this.testCases.forEach(tc => { tc.style.display = ''; });
      }
    }

    updateProgress() {
      const pct = ((this.currentTest / this.totalTests) * 100).toFixed(0);
      this.el.testStatus.textContent = `Testing... ${pct}%`;
      this.el.progressFill.style.width = pct + '%';
      this.el.progressText.textContent = `${pct}% — ${this.passedTests} passed, ${this.failedTests} failed`;
      if (this.failedTests > 0) this.el.progressFill.classList.add('has-failures');
      this.updateGroupBadges();
    }

    updateGroupBadges() {
      for (const [name, s] of Object.entries(this.groupStats)) {
        const tested = s.passed + s.failed;
        if (tested === 0) continue;
        const badge = this.container.querySelector(`[data-group-badge="${name}"]`);
        if (!badge) continue;
        badge.textContent = `${s.passed}/${tested}`;
        badge.className = 'utilikit-test-group__badge ' + (s.failed > 0 ? 'is-fail' : 'is-pass');
      }
    }

    updateSummary() {
      this.el.passedCount.textContent = this.passedTests;
      this.el.failedCount.textContent = this.failedTests;
      this.el.passedCount.className = 'utilikit-test-stat__value' + (this.passedTests > 0 ? ' is-success' : '');
      this.el.failedCount.className = 'utilikit-test-stat__value' + (this.failedTests > 0 ? ' is-error' : '');

      if (!this.isRunning && this.currentTest > 0) {
        const rate = ((this.passedTests / this.currentTest) * 100).toFixed(1);
        this.el.testStatus.textContent = `${rate}% passed`;
        this.el.testStatus.className = 'utilikit-test-stat__value utilikit-test-stat__value--status ' +
          (this.failedTests === 0 ? 'is-success' : 'is-error');
      }
    }

    // ---------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------

    resetResults() {
      this.passedTests = 0;
      this.failedTests = 0;
      this.currentTest = 0;
      this.testResults.clear();
      this.buildGroupStats();

      this.testCases.forEach(tc => {
        tc.classList.remove('is-passed', 'is-failed');
        const badge = tc.querySelector('.utilikit-test-badge');
        badge.textContent = 'Pending';
        badge.className = 'utilikit-test-badge utilikit-test-badge--pending';
        tc.querySelector('.test-actual').textContent = '-';
      });

      this.container.querySelectorAll('.utilikit-test-group__badge').forEach(b => {
        b.textContent = '';
        b.className = 'utilikit-test-group__badge';
      });

      this.updateSummary();
    }

    /**
     * Full reset: clears results, log, filters, and collapses everything.
     */
    resetAll() {
      this.resetResults();
      this.clearLog();

      // Reset filters.
      this.el.statusFilter.value = 'all';
      this.el.groupFilter.value = 'all';
      this.applyFilters();
      this.updateRunButtonLabel();

      // Collapse all.
      this.allExpanded = false;
      this.container.querySelectorAll('.utilikit-test-rule__body').forEach(b => { b.style.display = 'none'; });
      this.container.querySelectorAll('.toggle-tests').forEach(b => { b.textContent = 'Show'; });
      this.el.toggleAllBtn.textContent = 'Expand All';

      // Reset counts.
      this.totalTests = this.testCases.length;
      this.el.totalTests.textContent = this.totalTests;
      this.el.testStatus.textContent = 'Ready';
      this.el.testStatus.className = 'utilikit-test-stat__value utilikit-test-stat__value--status';

      // Hide progress bar.
      this.el.progressBar.style.display = 'none';

      this.log('Test suite reset.', 'info');
    }

    finishTestRun() {
      this.isRunning = false;
      this.setRunningUI(false);
      this.updateSummary();
      this.updateGroupBadges();

      // Keep progress bar visible with final state.
      this.el.progressBar.style.display = '';
      const pct = ((this.currentTest / this.totalTests) * 100).toFixed(0);
      this.el.progressFill.style.width = pct + '%';
      this.el.progressFill.className = 'utilikit-test-progress__fill ' +
        (this.failedTests === 0 ? 'is-done-pass' : 'is-done-fail');
      this.el.progressText.textContent = `${this.passedTests} passed, ${this.failedTests} failed of ${this.currentTest}`;

      this.log(`Completed: ${this.passedTests} passed, ${this.failedTests} failed out of ${this.currentTest}`,
        this.failedTests === 0 ? 'success' : 'error');
    }

    stopTests() {
      this.shouldStop = true;
      this.log('Stopping tests...', 'warn');
    }

    waitForStylesheets() {
      return new Promise(resolve => {
        const links = document.querySelectorAll('link[rel="stylesheet"]');
        let pending = links.length;
        if (!pending) { resolve(); return; }
        links.forEach(link => {
          if (link.sheet) { pending--; if (!pending) resolve(); }
          else {
            const done = () => { pending--; if (!pending) resolve(); };
            link.addEventListener('load', done);
            link.addEventListener('error', done);
          }
        });
        setTimeout(resolve, 5000);
      });
    }

    exportResults() {
      const data = {
        summary: {
          totalTests: this.totalTests, passed: this.passedTests, failed: this.failedTests,
          passRate: ((this.passedTests / this.totalTests) * 100).toFixed(2) + '%',
          timestamp: new Date().toISOString(),
          userAgent: navigator.userAgent,
          viewport: `${window.innerWidth}x${window.innerHeight}`,
          breakpoint: Drupal.utilikit.getBreakpoint()
        },
        tests: Array.from(this.testResults.entries()).map(([id, r]) => ({ id, ...r })),
        failedTests: Array.from(this.testResults.entries())
          .filter(([_, r]) => !r.passed)
          .map(([id, r]) => ({ id, class: r.className, expected: r.expected, actual: r.actual }))
      };
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `utilikit-test-results-${Date.now()}.json`;
      a.click();
      URL.revokeObjectURL(url);
      this.log('Results exported.', 'success');
    }

    log(message, type = 'info') {
      const ts = new Date().toLocaleTimeString();
      const entry = document.createElement('div');
      entry.className = `log-entry ${type}`;
      entry.textContent = `[${ts}] ${message}`;
      this.el.logContent.appendChild(entry);
      this.el.logContent.scrollTop = this.el.logContent.scrollHeight;
    }

    clearLog() {
      this.el.logContent.innerHTML = '';
      this.log('Log cleared.', 'info');
    }
  }

})(Drupal, once);
