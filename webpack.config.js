var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .addEntry('js/app', './assets/js/app.js')
    .addStyleEntry('css/app', './assets/css/app.scss')
    .addEntry('js/table', './assets/js/table.js')
    .enableSassLoader(function(sassOptions) {}, {
        resolveUrlLoader: false
    })
    .autoProvidejQuery()
    .autoProvideVariables({ Popper: ['popper.js', 'default'] })
;

module.exports = Encore.getWebpackConfig();
