/**
 * @file
 * Asks for confirmation before a settings-form button with data-utilikit-confirm submits.
 *
 * Replaces an inline onclick handler, which a strict Content Security Policy blocks.
 */
((Drupal, once) => {
  Drupal.behaviors.utilikitSettingsConfirm = {
    attach(context) {
      once('utilikit-confirm', '[data-utilikit-confirm]', context).forEach(
        (button) => {
          button.addEventListener('click', (event) => {
            // eslint-disable-next-line no-alert
            if (!window.confirm(button.dataset.utilikitConfirm)) {
              event.preventDefault();
              event.stopImmediatePropagation();
            }
          });
        },
      );
    },
  };
})(Drupal, once);
