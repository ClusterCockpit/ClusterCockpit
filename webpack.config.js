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
    .addEntry('joblist', './assets/joblist.js')
    .addEntry('jobview', './assets/jobview.js')
    .addEntry('userlist', './assets/userlist.js')
    .addEntry('userview', './assets/userview.js')
    .addEntry('analysisview', './assets/analysisview.js')
;

let config = Encore.getWebpackConfig();
config.resolve.mainFields = ['svelte', 'browser', 'module', 'main'];
config.resolve.extensions = ['.mjs', '.js', '.svelte'];

let svelte = config.module.rules.pop();
config.module.rules.unshift(svelte);

module.exports = config;
