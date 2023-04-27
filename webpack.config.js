const Encore = require('@symfony/webpack-encore');
const path = require('path');


if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

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
    .addEntry('dashboard', './assets/js/dashboard.js')
    .addEntry('editor', './assets/js/editor.js')
    .addEntry('editor-functions', './assets/js/editor-functions.js')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

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
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

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
    /*.addLoader({ test: /\.ejs$/, loader: 'ejs-render-loader' })    

    .addPlugin(new HtmlWebpackPlugin({
        template: 'Editor2/themes/default/ejs/action_configsget.ejs',
        filename: 'Editor2/themes/default/ejs/action_configsget.html'
    }))*/
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

const defaultConfig = Encore.getWebpackConfig();
defaultConfig.output.libraryTarget = "umd";
defaultConfig.externals = [
    "add",
    {
        "ace/lib/regexp": {
            root: "ace/lib/regexp",
            commonjs2: "ace/lib/regexp",
            //commonjs: ["./math", "subtract"],
            amd: "ace/lib/regexp"
        },
        "ace/lib/es5-shim": {
            root: "ace/lib/es5-shim",
            commonjs2: "ace/lib/es5-shim",
            //commonjs: ["./math", "subtract"],
            amd: "ace/lib/es5-shim"
        },
        "ace/lib/fixoldbrowsers": {
            root: "ace/lib/fixoldbrowsers",
            commonjs2: "ace/lib/fixoldbrowsers",
            //commonjs: ["./math", "subtract"],
            amd: "ace/lib/fixoldbrowsers"
        },
        "ace/lib/dom": {
            root: "ace/lib/dom",
            commonjs2: "ace/lib/dom",
            //commonjs: ["./math", "subtract"],
            amd: "ace/lib/dom"
        },
        "ace/lib/oop": {
            root: "ace/lib/oop",
            commonjs2: "ace/lib/oop",
            //commonjs: ["./math", "subtract"],
            amd: "ace/lib/oop"
        },
        "ace/lib/keys": {
            root: "ace/lib/keys",
            commonjs2: "ace/lib/keys",
            //commonjs: ["./math", "subtract"],
            amd: "ace/lib/keys"
        },
        "ace/lib/useragent": {
            root: "ace/lib/useragent",
            commonjs2: "ace/lib/useragent",
            amd: "ace/lib/useragent"
        },
        "ace/lib/event": {
            root: "ace/lib/event",
            commonjs2: "ace/lib/event",
            amd: "ace/lib/event"
        },
        "ace/lib/lang": {
            root: "ace/lib/lang",
            commonjs2: "ace/lib/lang",
            amd: "ace/lib/lang"
        },
        "ace/keyboard/textinput": {
            root: "ace/keyboard/textinput",
            commonjs2: "ace/keyboard/textinput",
            amd: "ace/keyboard/textinput"
        },
        "ace/mouse/default_handlers": {
            root: "ace/mouse/default_handlers",
            commonjs2: "ace/mouse/default_handlers",
            amd: "ace/mouse/default_handlers"
        },
        "ace/tooltip": {
            root: "ace/tooltip",
            commonjs2: "ace/tooltip",
            amd: "ace/tooltip"
        },
        "ace/mouse/default_gutter_handler": {
            root: "ace/mouse/default_gutter_handler",
            commonjs2: "ace/mouse/default_gutter_handler",
            amd: "ace/mouse/default_gutter_handler"
        },
        "ace/mouse/mouse_event": {
            root: "ace/mouse/mouse_event",
            commonjs2: "ace/mouse/mouse_event",
            amd: "ace/mouse/mouse_event"
        },
        "ace/mouse/dragdrop_handler": {
            root: "ace/mouse/dragdrop_handler",
            commonjs2: "ace/mouse/dragdrop_handler",
            amd: "ace/mouse/dragdrop_handler"
        },
        "ace/lib/net": {
            root: "ace/lib/net",
            commonjs2: "ace/lib/net",
            amd: "ace/lib/net"
        },
        "ace/lib/event_emitter": {
            root: "ace/lib/event_emitter",
            commonjs2: "ace/lib/event_emitter",
            amd: "ace/lib/event_emitter"
        },
        "ace/lib/app_config": {
            root: "ace/lib/app_config",
            commonjs2: "ace/lib/app_config",
            amd: "ace/lib/app_config"
        },
        "ace/config": {
            root: "ace/config",
            commonjs2: "ace/config",
            amd: "ace/config"
        },
        "ace/mouse/mouse_handler": {
            root: "ace/mouse/mouse_handler",
            commonjs2: "ace/mouse/mouse_handler",
            amd: "ace/mouse/mouse_handler"
        },
        "ace/mouse/fold_handler": {
            root: "ace/mouse/fold_handler",
            commonjs2: "ace/mouse/fold_handler",
            amd: "ace/mouse/fold_handler"
        },
        "ace/keyboard/keybinding": {
            root: "ace/keyboard/keybinding",
            commonjs2: "ace/keyboard/keybinding",
            amd: "ace/keyboard/keybinding"
        },
        "ace/range": {
            root: "ace/range",
            commonjs2: "ace/range",
            amd: "ace/range"
        },
        "ace/selection": {
            root: "ace/selection",
            commonjs2: "ace/selection",
            amd: "ace/selection"
        },
        "ace/tokenizer": {
            root: "ace/tokenizer",
            commonjs2: "ace/tokenizer",
            amd: "ace/tokenizer"
        },
        "ace/mode/text_highlight_rules": {
            root: "ace/mode/text_highlight_rules",
            commonjs2: "ace/mode/text_highlight_rules",
            amd: "ace/mode/text_highlight_rules"
        },
        "ace/mode/behaviour": {
            root: "ace/mode/behaviour",
            commonjs2: "ace/mode/behaviour",
            amd: "ace/mode/behaviour"
        },
        "ace/token_iterator": {
            root: "ace/token_iterator",
            commonjs2: "ace/token_iterator",
            amd: "ace/token_iterator"
        },
        "ace/mode/behaviour/cstyle": {
            root: "ace/mode/behaviour/cstyle",
            commonjs2: "ace/mode/behaviour/cstyle",
            amd: "ace/mode/behaviour/cstyle"
        },
        "ace/unicode": {
            root: "ace/unicode",
            amd: "ace/unicode"
        },
        "ace/mode/text": {
            root: "ace/mode/text",
            commonjs2: "ace/mode/text",
            amd: "ace/mode/text"
        },
        "ace/apply_delta": {
            root: "ace/apply_delta",
            commonjs2: "ace/apply_delta",
            amd: "ace/apply_delta"
        },
        "ace/anchor": {
            root: "ace/anchor",
            commonjs2: "ace/anchor",
            amd: "ace/anchor"
        },
        "ace/document": {
            root: "ace/document",
            commonjs2: "ace/document",
            amd: "ace/document"
        },
        "ace/background_tokenizer": {
            root: "ace/background_tokenizer",
            commonjs2: "ace/background_tokenizer",
            amd: "ace/background_tokenizer"
        },
        "ace/search_highlight": {
            root: "ace/search_highlight",
            commonjs2: "ace/search_highlight",
            amd: "ace/search_highlight"
        },
        "ace/edit_session/fold_line": {
            root: "ace/edit_session/fold_line",
            commonjs2: "ace/edit_session/fold_line",
            amd: "ace/edit_session/fold_line"
        },
        "ace/range_list": {
            root: "ace/range_list",
            commonjs2: "ace/range_list",
            amd: "ace/range_list"
        },
        "ace/edit_session/fold": {
            root: "ace/edit_session/fold",
            commonjs2: "ace/edit_session/fold",
            amd: "ace/edit_session/fold"
        },
        "ace/edit_session/folding": {
            root: "ace/edit_session/folding",
            commonjs2: "ace/edit_session/folding",
            amd: "ace/edit_session/folding"
        },
        "ace/edit_session/bracket_match": {
            root: "ace/edit_session/bracket_match",
            commonjs2: "ace/edit_session/bracket_match",
            amd: "ace/edit_session/bracket_match"
        },
        "ace/edit_session": {
            root: "ace/edit_session",
            commonjs2: "ace/edit_session",
            amd: "ace/edit_session"
        },
        "ace/search": {
            root: "ace/search",
            commonjs2: "ace/search",
            amd: "ace/search"
        },
        "ace/keyboard/hash_handler": {
            root: "ace/keyboard/hash_handler",
            commonjs2: "ace/keyboard/hash_handler",
            amd: "ace/keyboard/hash_handler"
        },
        "ace/commands/command_manager": {
            root: "ace/commands/command_manager",
            commonjs2: "ace/commands/command_manager",
            amd: "ace/commands/command_manager"
        },
        "ace/commands/default_commands": {
            root: "ace/commands/default_commands",
            commonjs2: "ace/commands/default_commands",
            amd: "ace/commands/default_commands"
        },
        "ace/editor": {
            root: "ace/editor",
            commonjs2: "ace/editor",
            amd: "ace/editor"
        },
        "ace/undomanager": {
            root: "ace/undomanager",
            commonjs2: "ace/undomanager",
            amd: "ace/undomanager"
        },
        "ace/layer/gutter": {
            root: "ace/layer/gutter",
            commonjs2: "ace/layer/gutter",
            amd: "ace/layer/gutter"
        },
        "ace/layer/marker": {
            root: "ace/layer/marker",
            commonjs2: "ace/layer/marker",
            amd: "ace/layer/marker"
        },
        "ace/layer/text": {
            root: "ace/layer/text",
            commonjs2: "ace/layer/text",
            amd: "ace/layer/text"
        },
        "ace/layer/cursor": {
            root: "ace/layer/cursor",
            commonjs2: "ace/layer/cursor",
            amd: "ace/layer/cursor"
        },
        "ace/scrollbar": {
            root: "ace/scrollbar",
            commonjs2: "ace/scrollbar",
            amd: "ace/scrollbar"
        },
        "ace/renderloop": {
            root: "ace/renderloop",
            commonjs2: "ace/renderloop",
            amd: "ace/renderloop"
        },
        "ace/layer/font_metrics": {
            root: "ace/layer/font_metrics",
            commonjs2: "ace/layer/font_metrics",
            amd: "ace/layer/font_metrics"
        },
        "ace/virtual_renderer": {
            root: "ace/virtual_renderer",
            commonjs2: "ace/virtual_renderer",
            amd: "ace/virtual_renderer"
        },
        "ace/worker/worker_client": {
            root: "ace/worker/worker_client",
            commonjs2: "ace/worker/worker_client",
            amd: "ace/worker/worker_client"
        },
        "ace/placeholder": {
            root: "ace/placeholder",
            commonjs2: "ace/placeholder",
            amd: "ace/placeholder"
        },
        "ace/mouse/multi_select_handler": {
            root: "ace/mouse/multi_select_handler",
            commonjs2: "ace/mouse/multi_select_handler",
            amd: "ace/mouse/multi_select_handler"
        },
        "ace/commands/multi_select_commands": {
            root: "ace/commands/multi_select_commands",
            commonjs2: "ace/commands/multi_select_commands",
            amd: "ace/commands/multi_select_commands"
        },
        "ace/multi_select": {
            root: "ace/multi_select",
            commonjs2: "ace/multi_select",
            amd: "ace/multi_select"
        },
        "ace/mode/folding/fold_mode": {
            root: "ace/mode/folding/fold_mode",
            commonjs2: "ace/mode/folding/fold_mode",
            amd: "ace/mode/folding/fold_mode"
        },
        "ace/theme/textmate": {
            root: "ace/theme/textmate",
            commonjs2: "ace/theme/textmate",
            amd: "ace/theme/textmate"
        },
        "ace/line_widgets": {
            root: "ace/line_widgets",
            commonjs2: "ace/line_widgets",
            amd: "ace/line_widgets"
        },"ace/ext/error_marker": {
            root: "ace/ext/error_marker",
            commonjs2: "ace/ext/error_marker",
            amd: "ace/ext/error_marker"
        },
        "ace/ace": {
            root: "ace/ace",
            commonjs2: "ace/ace",
            amd: "ace/ace"
        },
    }
    
]


module.exports = defaultConfig;