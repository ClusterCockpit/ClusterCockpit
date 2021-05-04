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
    .enableSourceMaps(!Encore.isProduction())
    .addEntry('app', './assets/app.js')
;

let config = Encore.getWebpackConfig();
config.resolve.mainFields = ['svelte', 'browser', 'module', 'main'];
config.resolve.extensions = ['.mjs', '.js', '.svelte'];

let svelte = config.module.rules.pop();
config.module.rules.unshift(svelte);

module.exports = config;
