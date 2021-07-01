var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addLoader({
        test: /\.svelte$/,
        loader: 'svelte-loader',
    })
    .cleanupOutputBeforeBuild()
    .enableSingleRuntimeChunk()
    .configureBabel((config) => {
        config.plugins.push('@babel/plugin-transform-runtime');
    })
    .enableSourceMaps(!Encore.isProduction())
    .addEntry('joblist', './assets/JobList/entrypoint.js')
    .addEntry('jobview', './assets/JobView/entrypoint.js')
    .addEntry('userlist', './assets/UserList/entrypoint.js')
    .addEntry('userview', './assets/UserView/entrypoint.js')
    .addEntry('analysisview', './assets/AnalysisView/entrypoint.js')
;

let config = Encore.getWebpackConfig();
config.resolve.mainFields = ['svelte', 'browser', 'module', 'main'];
config.resolve.extensions = ['.mjs', '.js', '.svelte'];

let svelte = config.module.rules.pop();
config.module.rules.unshift(svelte);

module.exports = config;
