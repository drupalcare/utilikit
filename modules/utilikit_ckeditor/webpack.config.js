/**
 * @file
 * Builds the CKEditor 5 plugin into js/build — twice, from one source.
 *
 * WHY TWO BUILDS. Drupal 11 ships CKEditor 5 as a DLL (`CKEditor5.dll`);
 * Drupal 12 ships it as a UMD bundle with a flat `CKEDITOR` global and keeps
 * `CKEditor5.dll()` only as a deprecated shim (change record
 * https://www.drupal.org/node/3581531). Neither runtime can load the other's
 * plugin build without that shim, so this config exports an array, as core's
 * own does:
 *
 * - js/build/utilikit.js — the DLL build, core 11's configuration
 *   (core/modules/ckeditor5/webpack.config.js on 11.x). DllReferencePlugin
 *   with scope `ckeditor5/src` is how Drupal 11 shares ONE CKEditor 5 runtime
 *   between core and every contributed plugin.
 * - js/build/utilikit.externals.js — the externals build, core 12's
 *   configuration: every `ckeditor5/*` and `@ckeditor/*` import resolves to
 *   the `CKEDITOR` global, and nothing from CKEditor is bundled. It is not
 *   named `*.umd.js` on purpose: core's translation discovery keys on that
 *   suffix.
 *
 * Which one a site receives is decided in PHP, by
 * Drupal\utilikit_ckeditor\Hook\UtilikitCkeditorHooks::libraryInfoAlter(),
 * from the file core's own `core/ckeditor5` library loads.
 *
 * Both keep `library: ['CKEditor5', 'utilikit']`, the global the Drupal
 * integration looks the plugin up under on both majors; the name must match
 * the `utilikit.Utilikit` reference in utilikit_ckeditor.ckeditor5.yml.
 *
 * THE BANNER. Each build starts with `utilikit-src-sha256:<hash>`, a sha256
 * over the plugin source, its icon and this config: src/*.js in sorted order,
 * then icons/handyman.svg, then this file. UtilikitCkeditorToolbarIconTest
 * recomputes it, so a source or build-config change committed without
 * rebuilding BOTH files fails the suite. The dependency ranges in
 * package.json are NOT covered: a different ckeditor5 or webpack resolved
 * within those ranges can change the output without changing the hash. It is added after minification (the
 * `stage` option), because Terser strips every comment — which is also why no
 * other banner is set here: it would never reach the output.
 */

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const webpack = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');

/**
 * The hash the PHP test recomputes. Keep the two definitions identical.
 */
function sourceHash() {
  const srcDir = path.resolve(__dirname, 'js/ckeditor5_plugins/utilikit/src');
  const files = fs
    .readdirSync(srcDir)
    .filter((name) => name.endsWith('.js'))
    .sort()
    .map((name) => path.join(srcDir, name));
  files.push(path.resolve(__dirname, 'icons/handyman.svg'));
  files.push(__filename);

  const hash = crypto.createHash('sha256');
  files.forEach((file) => hash.update(fs.readFileSync(file)));
  return hash.digest('hex');
}

/**
 * Maps every CKEditor 5 import to the CKEDITOR global — core 12's function.
 */
function ckeditorExternals({ request }, callback) {
  // Map all ckeditor5 imports to the CKEDITOR UMD global.
  if (request === 'ckeditor5' || request.startsWith('ckeditor5/')) {
    return callback(null, 'CKEDITOR');
  }
  // Map @ckeditor/* package imports to the CKEDITOR UMD global.
  if (request.startsWith('@ckeditor/')) {
    return callback(null, 'CKEDITOR');
  }
  return callback();
}

module.exports = (env, argv) => {
  const development = argv && argv.mode === 'development';
  const hash = sourceHash();
  const banner = () =>
    new webpack.BannerPlugin({
      banner: `utilikit-src-sha256:${hash}`,
      stage: webpack.Compilation.PROCESS_ASSETS_STAGE_SUMMARIZE,
    });

  const base = {
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
    module: {
      rules: [{ test: /\.svg$/, type: 'asset/source' }],
    },
  };

  const output = (filename) => ({
    path: path.resolve(__dirname, './js/build'),
    filename,
    library: ['CKEditor5', 'utilikit'],
    libraryTarget: 'umd',
    libraryExport: 'default',
  });

  // Resolved only here, so a missing DLL manifest names the DLL build.
  const manifest = require.resolve(
    'ckeditor5/build/ckeditor5-dll.manifest.json',
  );

  return [
    {
      ...base,
      name: 'dll',
      output: output('utilikit.js'),
      plugins: [
        new webpack.DllReferencePlugin({
          manifest: require(manifest),
          scope: 'ckeditor5/src',
          name: 'CKEditor5.dll',
        }),
        banner(),
      ],
    },
    {
      ...base,
      name: 'externals',
      output: output('utilikit.externals.js'),
      plugins: [banner()],
      externals: [ckeditorExternals],
    },
  ];
};
