/**
 * @file
 * UtiliKit Debug Helper - Visual debugging tools and console utilities.
 *
 * Provides a debug panel with real-time breakpoint/viewport info,
 * manual style refresh, state management, and console utilities.
 * Only activates when drupalSettings.utilikit.devMode is enabled.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.utilikitDebugHelper = {
    attach(context) {
      if (!drupalSettings.utilikit?.devMode) {
        return;
      }

      once('utilikit-debug-helper', 'body', context).forEach(() => {
        const debugPanel = document.createElement('div');
        debugPanel.id = 'utilikit-debug-panel';
        debugPanel.innerHTML = `
          <style>
            #utilikit-debug-panel {
              position: fixed;
              bottom: 20px;
              right: 20px;
              background: rgba(0, 0, 0, 0.8);
              color: white;
              padding: 15px;
              border-radius: 5px;
              font-family: monospace;
              font-size: 12px;
              z-index: 9999;
              min-width: 200px;
            }
            #utilikit-debug-panel button {
              display: block;
              width: 100%;
              margin: 5px 0;
              padding: 5px 10px;
              background: #007bff;
              color: white;
              border: none;
              border-radius: 3px;
              cursor: pointer;
            }
            #utilikit-debug-panel button:hover {
              background: #005a8b;
            }
            #utilikit-debug-info {
              margin-top: 10px;
              padding-top: 10px;
              border-top: 1px solid #666;
            }
          </style>
          <strong>UtiliKit Debug</strong>
          <button id="utilikit-force-refresh">Force Refresh Styles</button>
          <button id="utilikit-clear-state">Clear State & Refresh</button>
          <button id="utilikit-log-state">Log State to Console</button>
          <div id="utilikit-debug-info">
            <div>Breakpoint: <span id="uk-current-bp">-</span></div>
            <div>Width: <span id="uk-current-width">-</span>px</div>
            <div>Elements: <span id="uk-element-count">-</span></div>
          </div>
        `;
        document.body.appendChild(debugPanel);

        const updateDebugInfo = () => {
          document.getElementById('uk-current-bp').textContent = Drupal.utilikit.getBreakpoint() || 'base';
          document.getElementById('uk-current-width').textContent = window.innerWidth;
          document.getElementById('uk-element-count').textContent = document.querySelectorAll('.utilikit').length;
        };

        updateDebugInfo();

        document.getElementById('utilikit-force-refresh').addEventListener('click', () => {
          Drupal.utilikit.utilikitLog('Force refreshing all elements...', null, 'log');
          const elements = document.querySelectorAll('.utilikit');
          Drupal.utilikit.applyClasses(elements);
          updateDebugInfo();
          Drupal.utilikit.utilikitLog('Force refresh complete', null, 'log');
        });

        document.getElementById('utilikit-clear-state').addEventListener('click', () => {
          Drupal.utilikit.utilikitLog('Clearing state and refreshing...', null, 'log');
          Drupal.utilikit.state = null;
          Drupal.utilikit.initEnvironment(document);
          const elements = document.querySelectorAll('.utilikit');
          Drupal.utilikit.applyClasses(elements);
          updateDebugInfo();
          Drupal.utilikit.utilikitLog('State cleared and refreshed', null, 'log');
        });

        document.getElementById('utilikit-log-state').addEventListener('click', () => {
          Drupal.utilikit.utilikitLog('Current UtiliKit State:', {
            state: Drupal.utilikit.state,
            breakpoint: Drupal.utilikit.getBreakpoint(),
            width: window.innerWidth,
            elementCount: document.querySelectorAll('.utilikit').length,
            settings: drupalSettings.utilikit,
          }, 'log');
        });

        window.addEventListener('resize', updateDebugInfo);
      });
    }
  };

  // Expose console debugging utilities in dev mode only.
  if (drupalSettings.utilikit?.devMode) {
    window.utilikitDebug = {
      refresh() {
        const elements = document.querySelectorAll('.utilikit');
        Drupal.utilikit.applyClasses(elements);
        return `Refreshed ${elements.length} elements`;
      },

      getState() {
        return {
          state: Drupal.utilikit.state,
          breakpoint: Drupal.utilikit.getBreakpoint(),
          width: window.innerWidth,
        };
      },

      testResize() {
        Drupal.utilikit.utilikitLog('Testing resize handler...', null, 'log');
        if (typeof Drupal.utilikit.handleResize === 'function') {
          Drupal.utilikit.handleResize(document);
        }
        return 'Resize handler called';
      },
    };
  } else {
    window.utilikitDebug = undefined;
  }

})(Drupal, once, drupalSettings);
