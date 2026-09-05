/**
 * @file
 * drupalSettings mock for Jest tests
 */

module.exports = {
  utilikit: {
    renderingMode: 'inline',
    devMode: false,
    showPageErrors: false,
    enableTransitions: false,
    debounce: 50,
    logLevel: 'off',
    activeBreakpoints: ['sm', 'md', 'lg', 'xl', 'xxl'],
    breakpoints: {
      sm: 576,
      md: 768,
      lg: 992,
      xl: 1200,
      xxl: 1400,
    },
  },
};
