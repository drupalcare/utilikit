/**
 * @file
 * Builds the CKEditor 5 plugin into js/build.
 *
 * This is core's own configuration for the same job
 * (core/modules/ckeditor5/webpack.config.js), reduced to this module's single
 * plugin. The parts that look arbitrary are not:
 *
 * - DllReferencePlugin with scope `ckeditor5/src` is how Drupal shares ONE
 *   CKEditor 5 runtime between core and every contributed plugin. Without it
 *   the plugin bundles its own copy of CKEditor and the two instances do not
 *   recognise each other's classes, which fails at runtime rather than here.
 * - `library: ['CKEditor5', 'utilikit']` with a umd target is the global the
 *   Drupal integration looks the plugin up under; the name must match the
 *   `utilikit.Utilikit` reference in utilikit_ckeditor.ckeditor5.yml.
 *
 * The manifest is resolved from wherever `ckeditor5` is installed, so this
 * works both from a local node_modules and against Drupal core's.
 */

const path = require('node:path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');

const manifest = require.resolve('ckeditor5/build/ckeditor5-dll.manifest.json');

module.exports = (env, argv) => {
  const development = argv && argv.mode === 'development';

  return {
    mode: development ? 'development' : 'production',
    devtool: false,
    optimization: {
      minimize: !development,
      minimizer: [
        new TerserPlugin({
          terserOptions: { format: { comments: false } },
          test: /\.js(\?.*)?$/i,
          extractComments: false,
        }),
      ],
      moduleIds: 'named',
    },
    entry: {
      path: path.resolve(
        __dirname,
        'js/ckeditor5_plugins/utilikit/src/index.js',
      ),
    },
    output: {
      path: path.resolve(__dirname, './js/build'),
      filename: 'utilikit.js',
      library: ['CKEditor5', 'utilikit'],
      libraryTarget: 'umd',
      libraryExport: 'default',
    },
    plugins: [
      new webpack.BannerPlugin('cspell:disable'),
      new webpack.DllReferencePlugin({
        manifest: require(manifest),
        scope: 'ckeditor5/src',
        name: 'CKEditor5.dll',
      }),
    ],
    module: {
      rules: [{ test: /\.svg$/, type: 'asset/source' }],
    },
  };
};
