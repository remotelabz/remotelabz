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
    
    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

    .configureBabel(function (babelConfig) {
        babelConfig.plugins.push('@babel/plugin-transform-class-properties');
        babelConfig.plugins.push('@babel/plugin-transform-runtime');
    })

    .enableReactPreset()

    .copyFiles({
        from: './assets/images',
        // optional target path, relative to the output dir
        to: 'images/[path][name].[ext]',
        // only copy files matching this pattern
        //pattern: /\.(png|jpg|jpeg|gif)$/
    })
    .copyFiles({
        from: './assets/svg',
        // optional target path, relative to the output dir
        to: 'svg/[path][name].[ext]',
        // only copy files matching this pattern
        //pattern: /\.(png|jpg|jpeg)$/
    })
    .copyFiles({
        from: './assets/js/components/Editor2/themes/default/ejs',
        // optional target path, relative to the output dir
        to: 'editor/ejs/[path][name].[ext]',
        // only copy files matching this pattern
        pattern: /\.(ejs)$/
    })
    .copyFiles({
        from: './assets/js/components/Editor2/themes/default/bootstrap/js',
        // optional target path, relative to the output dir
        to: 'editor/bootstrap/js/[path][name].[ext]',
        // only copy files matching this pattern
        pattern: /\.(js)$/
    })
    .copyFiles({
        from: './assets/js/components/Editor2/themes/default/images',
        // optional target path, relative to the output dir
        to: 'editor/images/[path][name].[ext]',
        // only copy files matching this pattern
        pattern: /\.(png|jpg|jpeg|gif)$/
    })
    .copyFiles({
        from: './assets/js/plugins',
        // optional target path, relative to the output dir
        to: 'js/[path][name].[ext]',
        // only copy files matching this pattern
        pattern: /\.(js)$/
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
            commonjs: ["ace/lib/regexp", "subtract"],
            amd: "ace/lib/regexp"
        },
        "ace/lib/es5-shim": {
            root: "ace/lib/es5-shim",
            commonjs2: "ace/lib/es5-shim",
            commonjs: ["ace/lib/es5-shim", "subtract"],
            amd: "ace/lib/es5-shim"
        },
        "ace/lib/fixoldbrowsers": {
            root: "ace/lib/fixoldbrowsers",
            commonjs2: "ace/lib/fixoldbrowsers",
            commonjs: ["ace/lib/fixoldbrowsers", "subtract"],
            amd: "ace/lib/fixoldbrowsers"
        },
        "ace/lib/dom": {
            root: "ace/lib/dom",
            commonjs2: "ace/lib/dom",
            commonjs: ["ace/lib/dom", "subtract"],
            amd: "ace/lib/dom"
        },
        "ace/lib/oop": {
            root: "ace/lib/oop",
            commonjs2: "ace/lib/oop",
            commonjs: ["ace/lib/oop", "subtract"],
            amd: "ace/lib/oop"
        },
        "ace/lib/keys": {
            root: "ace/lib/keys",
            commonjs2: "ace/lib/keys",
            commonjs: ["ace/lib/keys", "subtract"],
            amd: "ace/lib/keys"
        },
        "ace/lib/useragent": {
            root: "ace/lib/useragent",
            commonjs2: "ace/lib/useragent",
            commonjs: ["ace/lib/useragent", "subtract"],
            amd: "ace/lib/useragent"
        },
        "ace/lib/event": {
            root: "ace/lib/event",
            commonjs2: "ace/lib/event",
            commonjs: ["ace/lib/event", "subtract"],
            amd: "ace/lib/event"
        },
        "ace/lib/lang": {
            root: "ace/lib/lang",
            commonjs2: "ace/lib/lang",
            commonjs: ["ace/lib/lang", "subtract"],
            amd: "ace/lib/lang"
        },
        "ace/keyboard/textinput": {
            root: "ace/keyboard/textinput",
            commonjs2: "ace/keyboard/textinput",
            commonjs: ["ace/keyboard/textinput", "subtract"],
            amd: "ace/keyboard/textinput"
        },
        "ace/mouse/default_handlers": {
            root: "ace/mouse/default_handlers",
            commonjs2: "ace/mouse/default_handlers",
            commonjs: ["ace/mouse/default_handlers", "subtract"],
            amd: "ace/mouse/default_handlers"
        },
        "ace/tooltip": {
            root: "ace/tooltip",
            commonjs2: "ace/tooltip",
            commonjs: ["ace/tooltip", "subtract"],
            amd: "ace/tooltip"
        },
        "ace/mouse/default_gutter_handler": {
            root: "ace/mouse/default_gutter_handler",
            commonjs2: "ace/mouse/default_gutter_handler",
            commonjs: ["ace/mouse/default_gutter_handler", "subtract"],
            amd: "ace/mouse/default_gutter_handler"
        },
        "ace/mouse/mouse_event": {
            root: "ace/mouse/mouse_event",
            commonjs2: "ace/mouse/mouse_event",
            commonjs: ["ace/mouse/mouse_event", "subtract"],
            amd: "ace/mouse/mouse_event"
        },
        "ace/mouse/dragdrop_handler": {
            root: "ace/mouse/dragdrop_handler",
            commonjs2: "ace/mouse/dragdrop_handler",
            commonjs: ["ace/mouse/dragdrop_handler", "subtract"],
            amd: "ace/mouse/dragdrop_handler"
        },
        "ace/lib/net": {
            root: "ace/lib/net",
            commonjs2: "ace/lib/net",
            commonjs: ["ace/lib/net", "subtract"],
            amd: "ace/lib/net"
        },
        "ace/lib/event_emitter": {
            root: "ace/lib/event_emitter",
            commonjs2: "ace/lib/event_emitter",
            commonjs: ["ace/lib/event_emitter", "subtract"],
            amd: "ace/lib/event_emitter"
        },
        "ace/lib/app_config": {
            root: "ace/lib/app_config",
            commonjs2: "ace/lib/app_config",
            commonjs: ["ace/lib/app_config", "subtract"],
            amd: "ace/lib/app_config"
        },
        "ace/config": {
            root: "ace/config",
            commonjs2: "ace/config",
            commonjs: ["ace/config", "subtract"],
            amd: "ace/config"
        },
        "ace/mouse/mouse_handler": {
            root: "ace/mouse/mouse_handler",
            commonjs2: "ace/mouse/mouse_handler",
            commonjs: ["ace/mouse/mouse_handler", "subtract"],
            amd: "ace/mouse/mouse_handler"
        },
        "ace/mouse/fold_handler": {
            root: "ace/mouse/fold_handler",
            commonjs2: "ace/mouse/fold_handler",
            commonjs: ["ace/mouse/fold_handler", "subtract"],
            amd: "ace/mouse/fold_handler"
        },
        "ace/keyboard/keybinding": {
            root: "ace/keyboard/keybinding",
            commonjs2: "ace/keyboard/keybinding",
            commonjs: ["ace/keyboard/keybinding", "subtract"],
            amd: "ace/keyboard/keybinding"
        },
        "ace/range": {
            root: "ace/range",
            commonjs2: "ace/range",
            commonjs: ["ace/range", "subtract"],
            amd: "ace/range"
        },
        "ace/selection": {
            root: "ace/selection",
            commonjs2: "ace/selection",
            commonjs: ["ace/selection", "subtract"],
            amd: "ace/selection"
        },
        "ace/tokenizer": {
            root: "ace/tokenizer",
            commonjs2: "ace/tokenizer",
            commonjs: ["ace/tokenizer", "subtract"],
            amd: "ace/tokenizer"
        },
        "ace/mode/text_highlight_rules": {
            root: "ace/mode/text_highlight_rules",
            commonjs2: "ace/mode/text_highlight_rules",
            commonjs: ["ace/mode/text_highlight_rules", "subtract"],
            amd: "ace/mode/text_highlight_rules"
        },
        "ace/mode/behaviour": {
            root: "ace/mode/behaviour",
            commonjs2: "ace/mode/behaviour",
            commonjs: ["ace/mode/behaviour", "subtract"],
            amd: "ace/mode/behaviour"
        },
        "ace/token_iterator": {
            root: "ace/token_iterator",
            commonjs2: "ace/token_iterator",
            commonjs: ["ace/token_iterator", "subtract"],
            amd: "ace/token_iterator"
        },
        "ace/mode/behaviour/cstyle": {
            root: "ace/mode/behaviour/cstyle",
            commonjs2: "ace/mode/behaviour/cstyle",
            commonjs: ["ace/mode/behaviour/cstyle", "subtract"],
            amd: "ace/mode/behaviour/cstyle"
        },
        "ace/unicode": {
            root: "ace/unicode",
            commonjs2: "ace/unicode",
            commonjs: ["ace/unicode", "subtract"],
            amd: "ace/unicode"
        },
        "ace/mode/text": {
            root: "ace/mode/text",
            commonjs2: "ace/mode/text",
            commonjs: ["ace/mode/text", "subtract"],
            amd: "ace/mode/text"
        },
        "ace/apply_delta": {
            root: "ace/apply_delta",
            commonjs2: "ace/apply_delta",
            commonjs: ["ace/apply_delta", "subtract"],
            amd: "ace/apply_delta"
        },
        "ace/anchor": {
            root: "ace/anchor",
            commonjs2: "ace/anchor",
            commonjs: ["ace/anchor", "subtract"],
            amd: "ace/anchor"
        },
        "ace/document": {
            root: "ace/document",
            commonjs2: "ace/document",
            commonjs: ["ace/document", "subtract"],
            amd: "ace/document"
        },
        "ace/background_tokenizer": {
            root: "ace/background_tokenizer",
            commonjs2: "ace/background_tokenizer",
            commonjs: ["ace/background_tokenizer", "subtract"],
            amd: "ace/background_tokenizer"
        },
        "ace/search_highlight": {
            root: "ace/search_highlight",
            commonjs2: "ace/search_highlight",
            commonjs: ["ace/search_highlight", "subtract"],
            amd: "ace/search_highlight"
        },
        "ace/edit_session/fold_line": {
            root: "ace/edit_session/fold_line",
            commonjs2: "ace/edit_session/fold_line",
            commonjs: ["ace/edit_session/fold_line", "subtract"],
            amd: "ace/edit_session/fold_line"
        },
        "ace/range_list": {
            root: "ace/range_list",
            commonjs2: "ace/range_list",
            commonjs: ["ace/range_list", "subtract"],
            amd: "ace/range_list"
        },
        "ace/edit_session/fold": {
            root: "ace/edit_session/fold",
            commonjs2: "ace/edit_session/fold",
            commonjs: ["ace/edit_session/fold", "subtract"],
            amd: "ace/edit_session/fold"
        },
        "ace/edit_session/folding": {
            root: "ace/edit_session/folding",
            commonjs2: "ace/edit_session/folding",
            commonjs: ["ace/edit_session/folding", "subtract"],
            amd: "ace/edit_session/folding"
        },
        "ace/edit_session/bracket_match": {
            root: "ace/edit_session/bracket_match",
            commonjs2: "ace/edit_session/bracket_match",
            commonjs: ["ace/edit_session/bracket_match", "subtract"],
            amd: "ace/edit_session/bracket_match"
        },
        "ace/edit_session": {
            root: "ace/edit_session",
            commonjs2: "ace/edit_session",
            commonjs: ["ace/edit_session", "subtract"],
            amd: "ace/edit_session"
        },
        "ace/search": {
            root: "ace/search",
            commonjs2: "ace/search",
            commonjs: ["ace/search", "subtract"],
            amd: "ace/search"
        },
        "ace/keyboard/hash_handler": {
            root: "ace/keyboard/hash_handler",
            commonjs2: "ace/keyboard/hash_handler",
            commonjs: ["ace/keyboard/hash_handler", "subtract"],
            amd: "ace/keyboard/hash_handler"
        },
        "ace/commands/command_manager": {
            root: "ace/commands/command_manager",
            commonjs2: "ace/commands/command_manager",
            commonjs: ["ace/commands/command_manager", "subtract"],
            amd: "ace/commands/command_manager"
        },
        "ace/commands/default_commands": {
            root: "ace/commands/default_commands",
            commonjs2: "ace/commands/default_commands",
            commonjs: ["ace/commands/default_commands", "subtract"],
            amd: "ace/commands/default_commands"
        },
        "ace/editor": {
            root: "ace/editor",
            commonjs2: "ace/editor",
            commonjs: ["ace/editor", "subtract"],
            amd: "ace/editor"
        },
        "ace/undomanager": {
            root: "ace/undomanager",
            commonjs2: "ace/undomanager",
            commonjs: ["ace/undomanager", "subtract"],
            amd: "ace/undomanager"
        },
        "ace/layer/gutter": {
            root: "ace/layer/gutter",
            commonjs2: "ace/layer/gutter",
            commonjs: ["ace/layer/gutter", "subtract"],
            amd: "ace/layer/gutter"
        },
        "ace/layer/marker": {
            root: "ace/layer/marker",
            commonjs2: "ace/layer/marker",
            commonjs: ["ace/layer/marker", "subtract"],
            amd: "ace/layer/marker"
        },
        "ace/layer/text": {
            root: "ace/layer/text",
            commonjs2: "ace/layer/text",
            commonjs: ["ace/layer/text", "subtract"],
            amd: "ace/layer/text"
        },
        "ace/layer/cursor": {
            root: "ace/layer/cursor",
            commonjs2: "ace/layer/cursor",
            commonjs: ["ace/layer/cursor", "subtract"],
            amd: "ace/layer/cursor"
        },
        "ace/scrollbar": {
            root: "ace/scrollbar",
            commonjs2: "ace/scrollbar",
            commonjs: ["ace/scrollbar", "subtract"],
            amd: "ace/scrollbar"
        },
        "ace/renderloop": {
            root: "ace/renderloop",
            commonjs2: "ace/renderloop",
            commonjs: ["ace/renderloop", "subtract"],
            amd: "ace/renderloop"
        },
        "ace/layer/font_metrics": {
            root: "ace/layer/font_metrics",
            commonjs2: "ace/layer/font_metrics",
            commonjs: ["ace/layer/font_metrics", "subtract"],
            amd: "ace/layer/font_metrics"
        },
        "ace/virtual_renderer": {
            root: "ace/virtual_renderer",
            commonjs2: "ace/virtual_renderer",
            commonjs: ["ace/virtual_renderer", "subtract"],
            amd: "ace/virtual_renderer"
        },
        "ace/worker/worker_client": {
            root: "ace/worker/worker_client",
            commonjs2: "ace/worker/worker_client",
            commonjs: ["ace/worker/worker_client", "subtract"],
            amd: "ace/worker/worker_client"
        },
        "ace/placeholder": {
            root: "ace/placeholder",
            commonjs2: "ace/placeholder",
            commonjs: ["ace/placeholder", "subtract"],
            amd: "ace/placeholder"
        },
        "ace/mouse/multi_select_handler": {
            root: "ace/mouse/multi_select_handler",
            commonjs2: "ace/mouse/multi_select_handler",
            commonjs: ["ace/mouse/multi_select_handler", "subtract"],
            amd: "ace/mouse/multi_select_handler"
        },
        "ace/commands/multi_select_commands": {
            root: "ace/commands/multi_select_commands",
            commonjs2: "ace/commands/multi_select_commands",
            commonjs: ["ace/commands/multi_select_commands", "subtract"],
            amd: "ace/commands/multi_select_commands"
        },
        "ace/multi_select": {
            root: "ace/multi_select",
            commonjs2: "ace/multi_select",
            commonjs: ["ace/multi_select", "subtract"],
            amd: "ace/multi_select"
        },
        "ace/mode/folding/fold_mode": {
            root: "ace/mode/folding/fold_mode",
            commonjs2: "ace/mode/folding/fold_mode",
            commonjs: ["ace/mode/folding/fold_mode", "subtract"],
            amd: "ace/mode/folding/fold_mode"
        },
        "ace/theme/textmate": {
            root: "ace/theme/textmate",
            commonjs2: "ace/theme/textmate",
            commonjs: ["ace/theme/textmate", "subtract"],
            amd: "ace/theme/textmate"
        },
        "ace/line_widgets": {
            root: "ace/line_widgets",
            commonjs2: "ace/line_widgets",
            commonjs: ["ace/line_widgets", "subtract"],
            amd: "ace/line_widgets"
        },
        "ace/ext/error_marker": {
            root: "ace/ext/error_marker",
            commonjs2: "ace/ext/error_marker",
            commonjs: ["ace/ext/error_marker", "subtract"],
            amd: "ace/ext/error_marker"
        },
        "ace/ace": {
            root: "ace/ace",
            commonjs2: "ace/ace",
            commonjs: ["ace/ace", "subtract"],
            amd: "ace/ace"
        },
    }
    
]


module.exports = defaultConfig;

const TerserPlugin = require('terser-webpack-plugin');

if (defaultConfig.optimization && defaultConfig.optimization.minimizer) {
    const terser = defaultConfig.optimization.minimizer.find(
        (plugin) => plugin instanceof TerserPlugin
    );
    if (terser) {
        terser.options.exclude = /\.min\.js$/;
    }
}

