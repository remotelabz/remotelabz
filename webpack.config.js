var Encore = require('@symfony/webpack-encore');
var path = require('path');

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if you JavaScript imports CSS.
     */
    .addEntry('react-app', './assets/js/App.jsx')
    .addEntry('app', './assets/js/app.js')
    .addEntry('user', './assets/js/user.js')
    .addEntry('profile', './assets/js/profile.jsx')
    .addEntry('flavor', './assets/js/flavor.js')
    .addEntry('network-settings', './assets/js/network-settings.js')
    .addEntry('network-interface', './assets/js/network-interface.js')
    .addEntry('activity', './assets/js/activity.js')
    .addEntry('vnc', './assets/js/vnc.js')
    .addEntry('editor-react', './assets/js/editor.jsx')
    .addEntry('timeago', './assets/js/timeago.js')
    .addEntry('users-select', './assets/js/SelectUser.jsx')
    .addEntry('groups', './assets/js/groups.js')

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    // .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables Sass/SCSS support
    .enableSassLoader((options) => {
        options.sourceMap = true;
        options.sassOptions = {
            outputStyle: 'compressed',
            sourceComments: !Encore.isProduction(),
        };
    }, {})

    // uncomment if you use TypeScript
    // .enableTypeScriptLoader()

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

    .configureBabel(function (babelConfig) {
        babelConfig.plugins.push('@babel/plugin-proposal-class-properties');
        babelConfig.plugins.push('@babel/plugin-transform-runtime');
    })

    .enableReactPreset()

    .copyFiles({
        from: './assets/images',
        // optional target path, relative to the output dir
        to: 'images/[path][name].[ext]',
        // only copy files matching this pattern
        //pattern: /\.(png|jpg|jpeg)$/
    })
    .copyFiles({
        from: './assets/svg',
        // optional target path, relative to the output dir
        to: 'svg/[path][name].[ext]',
        // only copy files matching this pattern
        //pattern: /\.(png|jpg|jpeg)$/
    })
    .addAliases({
        'fos-js-router': path.resolve(__dirname, 'vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.js'),
        'routes': path.resolve(__dirname, 'public/fos_js_routes.json'),
        'fos-jsrouting': path.resolve(__dirname, 'assets/js/routing.js'),
        // 'react': path.resolve(__dirname, './node_modules/react'),
        // 'react-dom': path.resolve(__dirname, './node_modules/react-dom')
    })
;

module.exports = Encore.getWebpackConfig();
